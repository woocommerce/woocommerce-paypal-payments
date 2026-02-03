<?php

/**
 * Processes refunds started in the WooCommerce environment.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\RefundCapture;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\RefundFeesUpdater;
/**
 * Class RefundProcessor
 */
class RefundProcessor
{
    use \WooCommerce\PayPalCommerce\WcGateway\Processor\RefundMetaTrait;
    public const REFUND_MODE_REFUND = 'refund';
    public const REFUND_MODE_VOID = 'void';
    public const REFUND_MODE_UNKNOWN = 'unknown';
    /**
     * The order endpoint.
     *
     * @var OrderEndpoint
     */
    private $order_endpoint;
    /**
     * The payments endpoint.
     *
     * @var PaymentsEndpoint
     */
    private $payments_endpoint;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * The prefix.
     *
     * @var string
     */
    private $prefix;
    /**
     * The refund fees updater.
     *
     * @var RefundFeesUpdater
     */
    private $refund_fees_updater;
    /**
     * RefundProcessor constructor.
     *
     * @param OrderEndpoint     $order_endpoint The order endpoint.
     * @param PaymentsEndpoint  $payments_endpoint The payments endpoint.
     * @param RefundFeesUpdater $refund_fees_updater The refund fees updater.
     * @param string            $prefix The prefix.
     * @param LoggerInterface   $logger The logger.
     */
    public function __construct(OrderEndpoint $order_endpoint, PaymentsEndpoint $payments_endpoint, RefundFeesUpdater $refund_fees_updater, string $prefix, LoggerInterface $logger)
    {
        $this->order_endpoint = $order_endpoint;
        $this->payments_endpoint = $payments_endpoint;
        $this->refund_fees_updater = $refund_fees_updater;
        $this->prefix = $prefix;
        $this->logger = $logger;
    }
    /**
     * Processes a refund.
     *
     * @param WC_Order   $wc_order The WooCommerce order.
     * @param float|null $amount The refund amount.
     * @param string     $reason The reason for the refund.
     *
     * @return bool
     *
     * @phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing
     */
    public function process(WC_Order $wc_order, ?float $amount = null, string $reason = ''): bool
    {
        try {
            $payment_gateways = WC()->payment_gateways()->payment_gateways();
            if (!isset($payment_gateways[$wc_order->get_payment_method()]) || !$payment_gateways[$wc_order->get_payment_method()]->supports('refunds')) {
                return \true;
            }
            $order_id = $wc_order->get_meta(PayPalGateway::ORDER_ID_META_KEY);
            if (!$order_id) {
                throw new RuntimeException('PayPal order ID not found in meta.');
            }
            $order = $this->order_endpoint->order($order_id);
            $payments = $this->get_payments($order);
            $this->logger->debug(sprintf('Trying to refund/void order %1$s, payments: %2$s.', $order->id(), wp_json_encode($payments->to_array())));
            $mode = $this->determine_refund_mode($order);
            switch ($mode) {
                case self::REFUND_MODE_REFUND:
                    $refund_id = $this->refund($order, $wc_order, $amount, $reason);
                    $this->add_refund_to_meta($wc_order, $refund_id);
                    $this->refund_fees_updater->update($wc_order);
                    break;
                case self::REFUND_MODE_VOID:
                    $this->void($order);
                    $wc_order->set_status('refunded');
                    $wc_order->save();
                    break;
                default:
                    throw new RuntimeException('Nothing to refund/void.');
            }
            return \true;
        } catch (Exception $error) {
            $this->logger->error('Refund failed: ' . $error->getMessage());
            return \false;
        }
    }
    /**
     * Adds a refund to the PayPal order.
     *
     * @param Order    $order The PayPal order.
     * @param WC_Order $wc_order The WooCommerce order.
     * @param float    $amount The refund amount.
     * @param string   $reason The reason for the refund.
     *
     * @throws RuntimeException When operation fails.
     * @return string The PayPal refund ID.
     */
    public function refund(Order $order, WC_Order $wc_order, float $amount, string $reason = ''): string
    {
        $payments = $this->get_payments($order);
        $captures = $payments->captures();
        if (!$captures) {
            throw new RuntimeException('No capture.');
        }
        $capture = $captures[0];
        $refund = new RefundCapture($capture, $capture->invoice_id() ?: $this->prefix . $wc_order->get_order_number(), $reason, new Amount(new Money($amount, $wc_order->get_currency())));
        return $this->payments_endpoint->refund($refund);
    }
    /**
     * Voids the authorization.
     *
     * @param Order $order The PayPal order.
     * @throws RuntimeException When operation fails.
     */
    public function void(Order $order): void
    {
        $payments = $this->get_payments($order);
        $voidable_authorizations = array_filter($payments->authorizations(), function (Authorization $authorization): bool {
            return $authorization->is_voidable();
        });
        if (!$voidable_authorizations) {
            throw new RuntimeException('No voidable authorizations.');
        }
        foreach ($voidable_authorizations as $authorization) {
            $this->payments_endpoint->void($authorization);
        }
    }
    /**
     * Determines the refunding mode.
     *
     * @param Order $order The order.
     *
     * @return string One of the REFUND_MODE_ constants.
     */
    public function determine_refund_mode(Order $order): string
    {
        $payments = $this->get_payments($order);
        $authorizations = $payments->authorizations();
        if ($authorizations) {
            foreach ($authorizations as $authorization) {
                if ($authorization->is_voidable()) {
                    return self::REFUND_MODE_VOID;
                }
            }
        }
        if ($payments->captures()) {
            return self::REFUND_MODE_REFUND;
        }
        return self::REFUND_MODE_UNKNOWN;
    }
    /**
     * Returns the payments object or throws.
     *
     * @param Order $order The order.
     * @throws RuntimeException When payment not available.
     */
    protected function get_payments(Order $order): Payments
    {
        $purchase_units = $order->purchase_units();
        if (!$purchase_units) {
            throw new RuntimeException('No purchase units.');
        }
        $payments = $purchase_units[0]->payments();
        if (!$payments) {
            throw new RuntimeException('No payments.');
        }
        return $payments;
    }
}
