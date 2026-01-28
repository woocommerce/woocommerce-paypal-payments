<?php

/**
 * Handles the Early Order logic, when we need to create the WC_Order by ourselves.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
/**
 * Class EarlyOrderHandler
 */
class EarlyOrderHandler
{
    /**
     * Whether the merchant is connected to PayPal (onboarding completed).
     *
     * @var bool
     */
    private bool $is_connected;
    /**
     * The Order Processor.
     *
     * @var OrderProcessor
     */
    private $order_processor;
    /**
     * The Session Handler.
     *
     * @var SessionHandler
     */
    private $session_handler;
    /**
     * EarlyOrderHandler constructor.
     *
     * @param bool           $is_connected Whether onboarding was completed.
     * @param OrderProcessor $order_processor The Order Processor.
     * @param SessionHandler $session_handler The Session Handler.
     */
    public function __construct(bool $is_connected, OrderProcessor $order_processor, SessionHandler $session_handler)
    {
        $this->is_connected = $is_connected;
        $this->order_processor = $order_processor;
        $this->session_handler = $session_handler;
    }
    /**
     * Whether early orders should be created at all.
     *
     * @return bool
     */
    public function should_create_early_order(): bool
    {
        return $this->is_connected;
    }
    //phpcs:disable WordPress.Security.NonceVerification.Recommended
    /**
     * Tries to determine the current WC Order Id based on the PayPal order
     * and the current order in session.
     *
     * @param int|null $value The initial value.
     *
     * @return int|null
     */
    public function determine_wc_order_id(?int $value = null)
    {
        if (!isset($_REQUEST['ppcp-resume-order'])) {
            return $value;
        }
        $resume_order_id = (int) WC()->session->get('order_awaiting_payment');
        $order = $this->session_handler->order();
        if (!$order) {
            return $value;
        }
        $order_id = \false;
        foreach ($order->purchase_units() as $purchase_unit) {
            if ($purchase_unit->custom_id() === sanitize_text_field(wp_unslash($_REQUEST['ppcp-resume-order']))) {
                $order_id = (int) $purchase_unit->custom_id();
            }
        }
        if ($order_id === $resume_order_id) {
            $value = $order_id;
        }
        return $value;
    }
    //phpcs:enable WordPress.Security.NonceVerification.Recommended
    /**
     * Registers the necessary checkout actions for a given order.
     *
     * @param Order $order The PayPal order.
     *
     * @return bool
     */
    public function register_for_order(Order $order): bool
    {
        $success = (bool) add_action('woocommerce_checkout_order_processed', function ($order_id) use ($order) {
            try {
                $order = $this->configure_session_and_order((int) $order_id, $order);
                wp_send_json_success($order->to_array());
            } catch (\RuntimeException $error) {
                wp_send_json_error(array('name' => is_a($error, PayPalApiException::class) ? $error->name() : '', 'message' => $error->getMessage(), 'code' => $error->getCode(), 'details' => is_a($error, PayPalApiException::class) ? $error->details() : array()));
            }
        });
        return $success;
    }
    /**
     * Configures the session, so we can pick up the order, once we pass through the checkout.
     *
     * @param int   $order_id The WooCommerce order id.
     * @param Order $order The PayPal order.
     *
     * @return Order
     */
    public function configure_session_and_order(int $order_id, Order $order): Order
    {
        /**
         * Set the order id in our session in order for
         * us to resume this order in checkout.
         */
        WC()->session->set('order_awaiting_payment', $order_id);
        $wc_order = wc_get_order($order_id);
        $wc_order->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, $order->id());
        $wc_order->update_meta_data(PayPalGateway::INTENT_META_KEY, $order->intent());
        $payment_source = $order->payment_source();
        $payment_source_name = $payment_source ? $payment_source->name() : null;
        $payer = $order->payer();
        if ($payer && $payment_source_name && in_array($payment_source_name, PayPalGateway::PAYMENT_SOURCES_WITH_PAYER_EMAIL, \true) && $wc_order instanceof \WC_Order) {
            $payer_email = $payer->email_address();
            if ($payer_email) {
                $wc_order->update_meta_data(PayPalGateway::ORDER_PAYER_EMAIL_META_KEY, $payer_email);
            }
        }
        $wc_order->save_meta_data();
        /**
         * Patch Order so we have the \WC_Order id added.
         */
        $order = $this->order_processor->patch_order($wc_order, $order);
        do_action('woocommerce_paypal_payments_woocommerce_order_created', $wc_order, $order);
        return $order;
    }
}
