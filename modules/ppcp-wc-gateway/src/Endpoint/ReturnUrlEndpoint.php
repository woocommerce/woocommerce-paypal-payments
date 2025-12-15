<?php

/**
 * Controls the endpoint for customers returning from PayPal.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use DomainException;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use Exception;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXOGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Class ReturnUrlEndpoint
 */
class ReturnUrlEndpoint
{
    const ENDPOINT = 'ppc-return-url';
    /**
     * The PayPal Gateway.
     *
     * @var PayPalGateway
     */
    private $gateway;
    /**
     * The Order Endpoint.
     *
     * @var OrderEndpoint
     */
    private $order_endpoint;
    /**
     * The session handler
     *
     * @var SessionHandler
     */
    protected $session_handler;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * ReturnUrlEndpoint constructor.
     *
     * @param PayPalGateway   $gateway         The PayPal Gateway.
     * @param OrderEndpoint   $order_endpoint  The Order Endpoint.
     * @param SessionHandler  $session_handler The session handler.
     * @param LoggerInterface $logger          The logger.
     */
    public function __construct(PayPalGateway $gateway, OrderEndpoint $order_endpoint, SessionHandler $session_handler, LoggerInterface $logger)
    {
        $this->gateway = $gateway;
        $this->order_endpoint = $order_endpoint;
        $this->session_handler = $session_handler;
        $this->logger = $logger;
    }
    /**
     * Handles the incoming request.
     */
    public function handle_request(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['token'])) {
            wc_add_notice(__('Payment session expired. Please try placing your order again.', 'woocommerce-paypal-payments'), 'error');
            wp_safe_redirect($this->get_checkout_url_with_error());
            exit;
        }
        $token = sanitize_text_field(wp_unslash($_GET['token']));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        try {
            $order = $this->order_endpoint->order($token);
        } catch (Exception $exception) {
            $this->logger->warning("Return URL endpoint failed to fetch order {$token}: " . $exception->getMessage());
            wc_add_notice(__('Could not retrieve payment information. Please try again.', 'woocommerce-paypal-payments'), 'error');
            wp_safe_redirect($this->get_checkout_url_with_error());
            exit;
        }
        // Handle 3DS completion if needed.
        if ($this->needs_3ds_completion($order)) {
            try {
                $order = $this->complete_3ds_verification($order);
            } catch (Exception $e) {
                $this->logger->warning("3DS completion failed for order {$token}: " . $e->getMessage());
                wc_add_notice($this->get_3ds_error_message($e), 'error');
                wp_safe_redirect($this->get_checkout_url_with_error());
                exit;
            }
        }
        // Replace session order for approved/completed orders.
        if ($order->status()->is(OrderStatus::APPROVED) || $order->status()->is(OrderStatus::COMPLETED)) {
            $this->session_handler->replace_order($order);
        }
        $wc_order_id = (int) $order->purchase_units()[0]->custom_id();
        if (!$wc_order_id) {
            // We cannot finish processing here without WC order, but at least go into the continuation mode.
            if ($order->status()->is(OrderStatus::APPROVED) || $order->status()->is(OrderStatus::COMPLETED)) {
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }
            $this->logger->warning("Return URL endpoint {$token}: no WC order ID.");
            wc_add_notice(__('Order information is missing. Please try placing your order again.', 'woocommerce-paypal-payments'), 'error');
            wp_safe_redirect($this->get_checkout_url_with_error());
            exit;
        }
        $wc_order = wc_get_order($wc_order_id);
        if (!is_a($wc_order, \WC_Order::class)) {
            $this->logger->warning("Return URL endpoint {$token}: WC order {$wc_order_id} not found.");
            wc_add_notice(__('Order not found. Please try placing your order again.', 'woocommerce-paypal-payments'), 'error');
            wp_safe_redirect($this->get_checkout_url_with_error());
            exit;
        }
        if ($wc_order->get_payment_method() === OXXOGateway::ID) {
            $this->session_handler->destroy_session_data();
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
        $payment_gateway = $this->get_payment_gateway($wc_order->get_payment_method());
        if (!$payment_gateway) {
            wc_add_notice(__('Payment gateway is unavailable. Please try again or contact support.', 'woocommerce-paypal-payments'), 'error');
            wp_safe_redirect($this->get_checkout_url_with_error());
            exit;
        }
        $success = $payment_gateway->process_payment($wc_order_id);
        if (isset($success['result']) && 'success' === $success['result']) {
            add_filter('allowed_redirect_hosts', function ($allowed_hosts): array {
                $allowed_hosts[] = 'www.paypal.com';
                $allowed_hosts[] = 'www.sandbox.paypal.com';
                return (array) $allowed_hosts;
            });
            wp_safe_redirect($success['redirect']);
            exit;
        }
        wc_add_notice(__('Payment processing failed. Please try again or contact support.', 'woocommerce-paypal-payments'), 'error');
        wp_safe_redirect($this->get_checkout_url_with_error());
        exit;
    }
    /**
     * Get checkout URL with additional error parameters.
     *
     * Applies the 'ppcp_return_url_error_args' filter to allow external modules to add error parameters.
     *
     * @return string Checkout URL with error query arguments, if any.
     */
    private function get_checkout_url_with_error(): string
    {
        $url = wc_get_checkout_url();
        $args = apply_filters('ppcp_return_url_error_args', array(), $this);
        if (!empty($args)) {
            $url = add_query_arg($args, $url);
        }
        return $url;
    }
    /**
     * Check if order needs 3DS completion.
     *
     * @param Order $order The PayPal order.
     * @return bool
     */
    private function needs_3ds_completion(Order $order): bool
    {
        // If order is still CREATED after 3DS redirect, it needs to be captured.
        return $order->status()->is(OrderStatus::CREATED);
    }
    /**
     * Complete 3DS verification by capturing the order.
     *
     * @param mixed $order The PayPal order.
     * @return mixed The processed order.
     * @throws Exception When 3DS completion fails.
     * @throws RuntimeException When API errors occur that don't match decline patterns.
     */
    private function complete_3ds_verification($order)
    {
        try {
            $captured_order = $this->order_endpoint->capture($order);
            // Check if capture actually succeeded vs. payment declined.
            if ($captured_order->status()->is(OrderStatus::COMPLETED)) {
                return $captured_order;
            } else {
                // Capture API succeeded but payment was declined.
                throw new Exception(__('Payment was declined by the payment provider. Please try a different payment method.', 'woocommerce-paypal-payments'));
            }
        } catch (DomainException $e) {
            throw new Exception(__('3D Secure authentication was unavailable or failed. Please try a different payment method or contact your bank.', 'woocommerce-paypal-payments'));
        } catch (RuntimeException $e) {
            if (strpos($e->getMessage(), 'declined') !== \false || strpos($e->getMessage(), 'PAYMENT_DENIED') !== \false || strpos($e->getMessage(), 'INSTRUMENT_DECLINED') !== \false || strpos($e->getMessage(), 'Payment provider declined') !== \false) {
                throw new Exception(__('Your payment was declined after 3D Secure verification. Please try a different payment method or contact your bank.', 'woocommerce-paypal-payments'));
            }
            throw $e;
        }
    }
    /**
     * Get user-friendly error message for 3DS failures.
     *
     * @param Exception $exception The exception.
     * @return string
     */
    private function get_3ds_error_message(Exception $exception): string
    {
        $error_message = $exception->getMessage();
        if (strpos($error_message, '3D Secure') !== \false) {
            return $error_message;
        }
        if (strpos($error_message, 'declined') !== \false) {
            return __('Your payment was declined after 3D Secure verification. Please try a different payment method or contact your bank.', 'woocommerce-paypal-payments');
        }
        return __('There was an error processing your payment. Please try again or contact support.', 'woocommerce-paypal-payments');
    }
    /**
     * Gets the appropriate payment gateway for the given payment method.
     *
     * @param string $payment_method The payment method ID.
     * @return \WC_Payment_Gateway|null
     */
    private function get_payment_gateway(string $payment_method)
    {
        // For regular PayPal payments, use the injected gateway.
        if ($payment_method === $this->gateway->id) {
            return $this->gateway;
        }
        // For other payment methods (like AXO), get from WooCommerce.
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        if (isset($available_gateways[$payment_method])) {
            return $available_gateways[$payment_method];
        }
        return null;
    }
}
