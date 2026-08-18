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
use WooCommerce\PayPalCommerce\ApiClient\Helper\ReturnUrlSecret;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Webhooks\CustomIds;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
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
     * Verifies the single-use secret that the return URL carries.
     *
     * @var ReturnUrlSecret
     */
    protected $return_url_secret;
    /**
     * The one-day period, after the correction is installed, in which a return for
     * a PayPal order that carries no secret is still accepted.
     */
    private const BINDING_SINCE_OPTION = 'ppcp_return_url_binding_since';
    /**
     * ReturnUrlEndpoint constructor.
     *
     * @param PayPalGateway        $gateway           The PayPal Gateway.
     * @param OrderEndpoint        $order_endpoint    The Order Endpoint.
     * @param SessionHandler       $session_handler   The session handler.
     * @param LoggerInterface      $logger            The logger.
     * @param ReturnUrlSecret|null $return_url_secret Verifies the return URL secret.
     *                                                Defaults to a real instance, so
     *                                                that a caller can never turn the
     *                                                authorization test off by accident.
     */
    public function __construct(PayPalGateway $gateway, OrderEndpoint $order_endpoint, SessionHandler $session_handler, LoggerInterface $logger, ?ReturnUrlSecret $return_url_secret = null)
    {
        $this->gateway = $gateway;
        $this->order_endpoint = $order_endpoint;
        $this->session_handler = $session_handler;
        $this->logger = $logger;
        $this->return_url_secret = $return_url_secret ?? new ReturnUrlSecret();
    }
    /**
     * Handles the incoming request.
     */
    public function handle_request(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['token'])) {
            $this->maybe_resume_card_3ds();
            wc_add_notice(__('Payment session expired. Please try placing your order again.', 'woocommerce-paypal-payments'), 'error');
            wp_safe_redirect($this->get_checkout_url_with_error());
            exit;
        }
        $token = sanitize_text_field(wp_unslash($_GET['token']));
        // wp_unslash() can return an array, so the value is sanitized on the next line behind an is_string() guard.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $provided_nonce = wp_unslash($_GET['ppcp_return_nonce'] ?? '');
        $provided_nonce = is_string($provided_nonce) ? sanitize_text_field($provided_nonce) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        try {
            $order = $this->order_endpoint->order($token);
        } catch (Exception $exception) {
            $this->logger->warning("Return URL endpoint failed to fetch order {$token}: " . $exception->getMessage());
            wc_add_notice(__('Could not retrieve payment information. Please try again.', 'woocommerce-paypal-payments'), 'error');
            wp_safe_redirect($this->get_checkout_url_with_error());
            exit;
        }
        // The WC order is resolved here, and not after the 3D Secure block, because
        // the authorization test below needs it. Each operation that changes data
        // stays behind that test.
        $custom_id = (string) $order->purchase_units()[0]->custom_id();
        $wc_order_id = (int) $custom_id;
        $wc_order = null;
        if ($wc_order_id) {
            $found = wc_get_order($wc_order_id);
            $wc_order = $found instanceof \WC_Order ? $found : null;
        }
        // The token travels in a public URL, so it is not proof by itself. Refuse
        // before the capture, before the session is changed, and before the payment
        // is processed.
        if (!$this->is_authorized_return($wc_order, $token, $provided_nonce, $custom_id)) {
            $this->logger->warning("Return URL endpoint {$token}: return refused, no proof of origin.");
            wc_add_notice(__('We could not confirm this payment session. Please try again.', 'woocommerce-paypal-payments'), 'error');
            wp_safe_redirect($this->get_checkout_url_with_error());
            exit;
        }
        // Reported only to a request that has proven its origin. Ahead of the test the
        // distinct message would tell a stranger whether the WC order behind a guessed
        // token still exists.
        if ($wc_order_id && !$wc_order instanceof \WC_Order) {
            $this->logger->warning("Return URL endpoint {$token}: WC order {$wc_order_id} not found.");
            wc_add_notice(__('Order not found. Please try placing your order again.', 'woocommerce-paypal-payments'), 'error');
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
        if (!$wc_order instanceof \WC_Order) {
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
        if ($wc_order->get_payment_method() === 'ppcp-oxxo-gateway') {
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
            // The secret works one time only, so a replay of the same URL is refused.
            $this->return_url_secret->consume($token);
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
     * Tells whether the request gives proof that it comes from the checkout flow
     * that made this PayPal order.
     *
     * Four proofs are accepted. The first one that holds ends the test, so a proof
     * that costs more is only used when a cheaper one does not apply.
     *
     * @param \WC_Order|null $wc_order       The WC order, when the custom_id gives one.
     * @param string         $token          The PayPal order ID from the request.
     * @param string         $provided_nonce The secret from the request.
     * @param string         $custom_id      The custom_id of the first purchase unit.
     *
     * @return bool
     */
    private function is_authorized_return(?\WC_Order $wc_order, string $token, string $provided_nonce, string $custom_id): bool
    {
        // Proof A — the secret that this shop put in the return URL.
        if ($this->return_url_secret->verify($token, $provided_nonce)) {
            return \true;
        }
        // Proof B — the session still holds the same PayPal order.
        $session_order = $this->session_handler->order();
        if ($session_order instanceof Order && hash_equals($session_order->id(), $token)) {
            return \true;
        }
        // Proof B — the custom_id carries the session that made the order. This is
        // the binding that PurchaseUnitFactory writes for a cart-context order.
        if (0 === strpos($custom_id, CustomIds::CUSTOMER_ID_PREFIX)) {
            $expected = substr($custom_id, strlen(CustomIds::CUSTOMER_ID_PREFIX));
            $session = WC()->session ?? null;
            $current = $session ? (string) $session->get_customer_id() : '';
            if ('' !== $expected && '' !== $current && hash_equals($expected, $current)) {
                return \true;
            }
        }
        // Proof C — the user that sends the request owns the WC order. A guest order
        // holds the customer ID 0, so 0 never counts as ownership.
        if ($wc_order instanceof \WC_Order) {
            $current_user_id = get_current_user_id();
            if ($current_user_id && $current_user_id === $wc_order->get_customer_id()) {
                return \true;
            }
        }
        // Proof D — the order was made before this correction was installed.
        if (!$this->return_url_secret->has_secret($token)) {
            $binding_since = (int) get_option(self::BINDING_SINCE_OPTION, 0);
            if ($binding_since && time() < $binding_since + DAY_IN_SECONDS) {
                $this->logger->warning("Return URL endpoint {$token}: accepted with no bound secret inside the migration period.");
                return \true;
            }
        }
        return \false;
    }
    /**
     * Resumes a vaulted-card payment after a 3D Secure challenge.
     *
     * PayPal returns from a vaulted-card 3DS challenge without the order token, so
     * the order is identified by the WC order id that CaptureCardPayment encoded
     * in the return URL. Re-runs the gateway's payment processing, which captures
     * the now-authenticated order and completes it. Exits on a handled success;
     * returns so the caller can fall back to the error redirect otherwise.
     */
    private function maybe_resume_card_3ds(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $wc_order_id = isset($_GET['ppcp_resume_wc_order']) ? absint(wp_unslash($_GET['ppcp_resume_wc_order'])) : 0;
        // wp_unslash() can return an array, so the value is sanitized on the next line behind an is_string() guard.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $provided_nonce = wp_unslash($_GET['ppcp_resume_nonce'] ?? '');
        $provided_nonce = is_string($provided_nonce) ? sanitize_text_field($provided_nonce) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if (!$wc_order_id || !$provided_nonce) {
            return;
        }
        $wc_order = wc_get_order($wc_order_id);
        if (!$wc_order instanceof \WC_Order) {
            return;
        }
        // The order id arrives from a public, guessable query argument; require the
        // one-time nonce stored on the order to match before any payment handling,
        // so a hand-crafted return URL cannot trigger a resume.
        $stored_nonce = (string) $wc_order->get_meta(CreditCardGateway::THREE_DS_RESUME_META);
        if (!$stored_nonce || !hash_equals($stored_nonce, $provided_nonce)) {
            return;
        }
        $gateway = $this->get_payment_gateway($wc_order->get_payment_method());
        if (!$gateway) {
            return;
        }
        try {
            $result = $gateway->process_payment($wc_order_id);
        } catch (Exception $exception) {
            $this->logger->warning("Card 3DS resume failed for WC order {$wc_order_id}: " . $exception->getMessage());
            return;
        }
        if (isset($result['result']) && 'success' === $result['result']) {
            wp_safe_redirect($result['redirect']);
            exit;
        }
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
