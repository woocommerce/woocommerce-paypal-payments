<?php

/**
 * Register and configure assets for the void button.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

use Exception;
use WC_AJAX;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\VoidOrderEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WP_Screen;
/**
 * Class VoidButtonAssets
 */
class VoidButtonAssets
{
    /**
     * The URL of this module.
     *
     * @var string
     */
    private $module_url;
    /**
     * The assets version.
     *
     * @var string
     */
    private $version;
    /**
     * The order endpoint.
     *
     * @var OrderEndpoint
     */
    private $order_endpoint;
    /**
     * The Refund Processor.
     *
     * @var RefundProcessor
     */
    private $refund_processor;
    /**
     * VoidButtonAssets constructor.
     *
     * @param string          $module_url The url of this module.
     * @param string          $version The assets version.
     * @param OrderEndpoint   $order_endpoint The order endpoint.
     * @param RefundProcessor $refund_processor The Refund Processor.
     */
    public function __construct(string $module_url, string $version, OrderEndpoint $order_endpoint, RefundProcessor $refund_processor)
    {
        $this->module_url = $module_url;
        $this->version = $version;
        $this->order_endpoint = $order_endpoint;
        $this->refund_processor = $refund_processor;
    }
    /**
     * Checks if should register assets on the current page.
     */
    public function should_register(): bool
    {
        if (!is_admin() || wp_doing_ajax()) {
            return \false;
        }
        global $theorder;
        if (!$theorder instanceof WC_Order) {
            return \false;
        }
        $current_screen = get_current_screen();
        if (!$current_screen instanceof WP_Screen) {
            return \false;
        }
        if ($current_screen->post_type !== 'shop_order') {
            return \false;
        }
        $payment_gateways = WC()->payment_gateways()->payment_gateways();
        if (!isset($payment_gateways[$theorder->get_payment_method()]) || !$payment_gateways[$theorder->get_payment_method()]->supports('refunds')) {
            return \false;
        }
        // Skip if there are refunds already, it is probably not voidable anymore + void cannot be partial.
        if ($theorder->get_remaining_refund_amount() !== $theorder->get_total()) {
            return \false;
        }
        $order_id = $theorder->get_meta(PayPalGateway::ORDER_ID_META_KEY);
        if (!$order_id) {
            return \false;
        }
        try {
            $order = $this->order_endpoint->order($order_id);
            if ($this->refund_processor->determine_refund_mode($order) !== RefundProcessor::REFUND_MODE_VOID) {
                return \false;
            }
        } catch (Exception $exception) {
            return \false;
        }
        return \true;
    }
    /**
     * Enqueues the assets.
     */
    public function register(): void
    {
        global $theorder;
        assert($theorder instanceof WC_Order);
        wp_enqueue_script('ppcp-void-button', trailingslashit($this->module_url) . 'assets/js/void-button.js', array(), $this->version, \true);
        wp_localize_script('ppcp-void-button', 'PcpVoidButton', array('button_text' => __('Void authorization', 'woocommerce-paypal-payments'), 'popup_text' => __('After voiding an authorized transaction, you cannot capture any funds associated with that transaction, and the funds are returned to the customer. Voiding an authorization cancels the entire open amount.', 'woocommerce-paypal-payments'), 'error_text' => __('The operation failed. Use the Refund button if the funds were already captured.', 'woocommerce-paypal-payments'), 'wc_order_id' => $theorder->get_id(), 'ajax' => array('void' => array('endpoint' => WC_AJAX::get_endpoint(VoidOrderEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(VoidOrderEndpoint::nonce())))));
    }
}
