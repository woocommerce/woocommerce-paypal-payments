<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\WcGateway;

use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use WC_Order;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Integration tests for the "woocommerce_before_order_object_save" listener registered by
 * WCGatewayModule::register_block_express_payment_method_handler().
 *
 * The listener fires on *every* order save, and the rewrite to PayPalGateway::ID is scoped to a
 * request-scoped marker ($marked_order_id) set by the
 * "woocommerce_rest_checkout_process_payment_with_context" handler.
 *
 * Why the marker is scoped to the request: the _ppcp_paypal_order_id meta only records that a
 * PayPal order was created at some point in the order's life, typically during an earlier or
 * abandoned checkout attempt. It does not prove PayPal took the payment, so it must never on its
 * own authorize a payment method rewrite.
 *
 * Why a single ID is enough: the block checkout produces one order per request. If that ever
 * changes, the marker must become a set of IDs — otherwise every order but the last one silently
 * loses the guard for the rest of the request. No test covers that scenario, because the current
 * flow cannot produce it.
 *
 * The tests split along that marker:
 *
 * - Unmarked orders (the production bug): stale "_ppcp_paypal_order_id" meta left over from an
 *   abandoned express-checkout attempt must never cause a rewrite. Reproducing this needs TWO
 *   saves — the first save, which persists the meta, already exposes the order to the listener,
 *   so only a later save reproduces "order paid through another gateway, saved again afterwards".
 *   See trigger_second_save().
 * - Marked orders (the feature PR #4349 added): after the context handler has routed the order
 *   through PayPal in this request, the listener re-asserts PayPalGateway::ID if something resets
 *   the payment method, but leaves a deliberate 'ppcp-' method such as ACDC alone.
 *
 * @covers \WooCommerce\PayPalCommerce\WcGateway\WCGatewayModule::register_block_express_payment_method_handler
 */
class BlockExpressPaymentMethodHandlerTest extends IntegrationMockedTestCase
{
    /**
     * @var int[]
     */
    private array $order_ids_to_cleanup = [];

    /**
     * @var callable|null
     */
    private $available_gateways_filter = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->order_ids_to_cleanup = [];
        $this->available_gateways_filter = null;
    }

    public function tearDown(): void
    {
        if (null !== $this->available_gateways_filter) {
            remove_filter('woocommerce_available_payment_gateways', $this->available_gateways_filter);
            $this->available_gateways_filter = null;
        }

        foreach ($this->order_ids_to_cleanup as $order_id) {
            wp_delete_post($order_id, true);
        }
        $this->order_ids_to_cleanup = [];

        parent::tearDown();
    }

    /**
     * @scenario An order whose payment method is 'bacs' and which carries a non-empty
     * _ppcp_paypal_order_id meta keeps payment_method 'bacs' after
     * 'woocommerce_before_order_object_save' fires.
     */
    public function test_bacs_order_with_stale_paypal_order_id_keeps_bacs(): void
    {
        // Arrange
        $order = $this->create_order_with_payment_method('bacs', true);

        // When
        $this->trigger_second_save($order->get_id());

        // Then
        $reloaded = wc_get_order($order->get_id());
        $this->assertSame('bacs', $reloaded->get_payment_method());
    }

    /**
     * @scenario An order whose payment method is 'woocommerce_payments' and which carries a
     * non-empty _ppcp_paypal_order_id meta keeps payment_method 'woocommerce_payments' (and its
     * transaction id) after 'woocommerce_before_order_object_save' fires. This mirrors the
     * reported production order.
     */
    public function test_woocommerce_payments_order_with_stale_paypal_order_id_keeps_its_gateway(): void
    {
        // Arrange
        $order = $this->create_order_with_payment_method('woocommerce_payments', true);
        $order->set_transaction_id('pi_1234567890');
        $order->save();

        // When
        $this->trigger_second_save($order->get_id());

        // Then
        $reloaded = wc_get_order($order->get_id());
        $this->assertSame('woocommerce_payments', $reloaded->get_payment_method());
        $this->assertSame('pi_1234567890', $reloaded->get_transaction_id());
    }

    /**
     * @scenario In a request where the 'woocommerce_rest_checkout_process_payment_with_context'
     * handler switched the context to PayPalGateway::ID for a given order, that order is still
     * saved with payment_method 'ppcp-gateway'.
     */
    public function test_express_checkout_context_switch_still_stores_paypal_gateway(): void
    {
        if (!class_exists(PaymentContext::class)) {
            $this->markTestSkipped('Automattic\WooCommerce\StoreApi\Payments\PaymentContext is not available in this environment.');
        }

        // Arrange
        $this->force_paypal_gateway_available();
        $order = $this->create_order_with_payment_method('some-other-gateway', false);

        // When
        $context = $this->mark_order_via_express_checkout_context($order);
        $order->save();

        // Then
        $reloaded = wc_get_order($order->get_id());
        $this->assertSame(PayPalGateway::ID, $reloaded->get_payment_method());
        // The context itself must be switched too, otherwise Store API routes the payment to the
        // gateway the shopper's request named instead of PayPal.
        $this->assertSame(PayPalGateway::ID, $context->payment_method);
    }

    /**
     * @scenario An order marked by the express-checkout context handler that is reset to a
     * non-PayPal payment method later in the same request is restored to 'ppcp-gateway' by the
     * save listener. This is the behaviour PR #4349 added, and the only test that exercises the
     * listener's positive path in isolation from the context handler.
     */
    public function test_marked_order_reset_to_another_gateway_is_restored_to_paypal(): void
    {
        if (!class_exists(PaymentContext::class)) {
            $this->markTestSkipped('Automattic\WooCommerce\StoreApi\Payments\PaymentContext is not available in this environment.');
        }

        // Arrange
        $this->force_paypal_gateway_available();
        $order = $this->create_order_with_payment_method('some-other-gateway', false);
        $this->mark_order_via_express_checkout_context($order);

        // When: another gateway sorted first in WC Settings resets the method after the context ran
        $reloaded = wc_get_order($order->get_id());
        $reloaded->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, 'PAYPAL-ORDER-EXPRESS-1');
        $reloaded->set_payment_method('some-other-gateway');
        $reloaded->save();

        // Then
        $this->assertSame(PayPalGateway::ID, wc_get_order($order->get_id())->get_payment_method());
    }

    /**
     * @scenario A marked order that carries a deliberate 'ppcp-' payment method (ACDC) is not
     * rewritten to 'ppcp-gateway' by the save listener.
     */
    public function test_marked_order_with_acdc_payment_method_is_not_rewritten(): void
    {
        if (!class_exists(PaymentContext::class)) {
            $this->markTestSkipped('Automattic\WooCommerce\StoreApi\Payments\PaymentContext is not available in this environment.');
        }

        // Arrange
        $this->force_paypal_gateway_available();
        $order = $this->create_order_with_payment_method('some-other-gateway', false);
        $this->mark_order_via_express_checkout_context($order);

        // When
        $reloaded = wc_get_order($order->get_id());
        $reloaded->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, 'PAYPAL-ORDER-EXPRESS-1');
        $reloaded->set_payment_method(CreditCardGateway::ID);
        $reloaded->save();

        // Then
        $this->assertSame(CreditCardGateway::ID, wc_get_order($order->get_id())->get_payment_method());
    }

    /**
     * @scenario A marked order without the _ppcp_paypal_order_id meta is not rewritten by the
     * save listener.
     */
    public function test_marked_order_without_paypal_order_meta_is_not_rewritten(): void
    {
        if (!class_exists(PaymentContext::class)) {
            $this->markTestSkipped('Automattic\WooCommerce\StoreApi\Payments\PaymentContext is not available in this environment.');
        }

        // Arrange
        $this->force_paypal_gateway_available();
        $order = $this->create_order_with_payment_method('some-other-gateway', false);
        $this->mark_order_via_express_checkout_context($order);

        // When: no PayPal order id meta is ever written to this order
        $reloaded = wc_get_order($order->get_id());
        $reloaded->set_payment_method('some-other-gateway');
        $reloaded->save();

        // Then
        $this->assertSame('some-other-gateway', wc_get_order($order->get_id())->get_payment_method());
    }

    /**
     * @scenario An order that was never marked by the express-checkout context handler in the
     * current request is not modified by the save listener, regardless of its current payment
     * method and regardless of whether it carries a stale _ppcp_paypal_order_id meta.
     *
     * @dataProvider dataForUnmarkedOrder
     */
    public function test_unmarked_order_is_never_touched(string $payment_method, bool $with_paypal_meta): void
    {
        // Arrange
        $order = $this->create_order_with_payment_method($payment_method, $with_paypal_meta);

        // When
        $this->trigger_second_save($order->get_id());

        // Then
        $reloaded = wc_get_order($order->get_id());
        $this->assertSame($payment_method, $reloaded->get_payment_method());
    }

    public function dataForUnmarkedOrder(): array
    {
        return [
            'cheque payment method is preserved' => ['cheque', true],
            'cash on delivery payment method is preserved' => ['cod', true],
            'empty payment method is preserved' => ['', true],
            'ppcp- prefixed payment method is preserved' => [CreditCardGateway::ID, true],
            'order without PayPal order meta is preserved' => ['bacs', false],
        ];
    }

    /**
     * The express handler returns early unless PayPalGateway::ID is among the *available*
     * gateways. In the integration environment there is no cart or customer session, so
     * WC_Payment_Gateways::get_available_payment_gateways() returns an empty list and the
     * handler would never run. Inject the real, already registered PayPal gateway object into
     * the availability list for the duration of the test.
     */
    private function force_paypal_gateway_available(): void
    {
        $registered = WC()->payment_gateways->payment_gateways();
        if (!isset($registered[PayPalGateway::ID])) {
            $this->markTestSkipped('PayPalGateway is not registered in this environment.');
        }

        $paypal_gateway = $registered[PayPalGateway::ID];

        $this->available_gateways_filter = function ($gateways) use ($paypal_gateway) {
            $gateways[PayPalGateway::ID] = $paypal_gateway;

            return $gateways;
        };

        add_filter('woocommerce_available_payment_gateways', $this->available_gateways_filter);
    }

    /**
     * Runs the express block-checkout entry point for the given order: the
     * 'woocommerce_rest_checkout_process_payment_with_context' handler, which is what marks the
     * order as routed through PayPal for the rest of the request.
     */
    private function mark_order_via_express_checkout_context(WC_Order $order): PaymentContext
    {
        $context = new PaymentContext();
        $context->set_order($order);
        $context->set_payment_data(
            [
                'paypal_order_id' => 'PAYPAL-ORDER-EXPRESS-1',
                // Required: the handler reads this key unconditionally.
                'funding_source' => 'paypal',
            ]
        );
        $context->set_payment_method('some-other-gateway');

        do_action('woocommerce_rest_checkout_process_payment_with_context', $context);

        return $context;
    }

    /**
     * Creates a plain WC_Order with a given payment method, optionally carrying a stale
     * PayPal order id meta, and persists it with a first save().
     */
    private function create_order_with_payment_method(string $payment_method, bool $with_paypal_meta): WC_Order
    {
        $order = wc_create_order(['customer_id' => $this->customer_id]);
        $order->set_payment_method($payment_method);

        if ($with_paypal_meta) {
            $order->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, 'PAYPAL-ORDER-STALE-1');
        }

        $order->save();

        $this->order_ids_to_cleanup[] = $order->get_id();

        return $order;
    }

    /**
     * Reloads the order and forces a second save() (via a harmless customer note change) to
     * reproduce the real-world scenario where an order carrying stale PayPal meta gets saved
     * again by another part of checkout/admin.
     */
    private function trigger_second_save(int $order_id): WC_Order
    {
        $order = wc_get_order($order_id);
        $order->set_customer_note('trigger second save ' . uniqid());
        $order->save();

        return wc_get_order($order_id);
    }
}
