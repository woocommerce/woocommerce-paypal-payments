<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\FraudProtection\Recaptcha;

use Automattic\WooCommerce\Utilities\OrderUtil;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Order;
use WC_Product;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\FraudProtection\PersistentCounter;
use WP_Error;
use WP_Post;
class Recaptcha
{
    private const V2_CONTAINER_ID = 'ppcp-recaptcha-v2-container';
    private const ERROR_CODE_MISSING_TOKEN = 'ppcp_recaptcha_missing_token';
    private const ERROR_CODE_VERIFICATION_FAILED = 'ppcp_recaptcha_verification_failed';
    private const CAPTCHA_USAGE_LIMIT = 5;
    private const CAPTCHA_RESULT_TRANSIENT_KEY = 'ppcp_recaptcha_result_';
    private const CAPTCHA_RESULT_META_KEY = 'ppcp_recaptcha_captcha_result';
    public const REJECTION_LOGGER_SOURCE = 'woocommerce-paypal-payments-recaptcha';
    public const REJECTION_COUNTER_OPTION = 'ppcp_recaptcha_rejection_counter';
    private \WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\RecaptchaIntegration $integration;
    /**
     * The methods that require captcha.
     *
     * @var string[]
     */
    private array $payment_methods;
    private AssetGetter $asset_getter;
    private string $asset_version;
    private LoggerInterface $logger;
    private PersistentCounter $rejection_counter;
    private float $last_v3_score = 0;
    /**
     * @param RecaptchaIntegration $integration
     * @param string[]             $payment_methods The methods that require captcha.
     * @param AssetGetter          $asset_getter
     * @param string               $asset_version
     * @param LoggerInterface      $logger
     * @param PersistentCounter    $rejection_counter
     */
    public function __construct(\WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\RecaptchaIntegration $integration, array $payment_methods, AssetGetter $asset_getter, string $asset_version, LoggerInterface $logger, PersistentCounter $rejection_counter)
    {
        $this->integration = $integration;
        $this->payment_methods = $payment_methods;
        $this->asset_getter = $asset_getter;
        $this->asset_version = $asset_version;
        $this->logger = $logger;
        $this->rejection_counter = $rejection_counter;
    }
    protected function should_use_recaptcha(): bool
    {
        if (!wc_string_to_bool($this->integration->enabled)) {
            return \false;
        }
        if (wc_string_to_bool($this->integration->get_option('guest_only')) && is_user_logged_in()) {
            return \false;
        }
        $has_v3 = !empty($this->integration->get_option('site_key_v3')) && !empty($this->integration->get_option('secret_key_v3'));
        $has_v2 = !empty($this->integration->get_option('site_key_v2')) && !empty($this->integration->get_option('secret_key_v2'));
        if (!$has_v3 || !$has_v2) {
            return \false;
        }
        return \true;
    }
    public function render_settings_page_log(): string
    {
        $count = esc_attr((string) $this->rejection_counter->current());
        $url = esc_url(admin_url('admin.php?page=wc-status&tab=logs&source=' . self::REJECTION_LOGGER_SOURCE));
        return "\n\t\t<tr valign='top'>\n\t\t\t<td colspan='2' style='padding: 0;'>\n\t\t\t\t<h4>Requests rejected by reCAPTCHA v3:\n\t\t\t\t\t{$count}\n\t\t\t\t\t<a href='{$url}'>view logs</a>\n\t\t\t\t</h4>\n\t\t\t</td>\n\t\t</tr>";
    }
    public function enqueue_scripts(): void
    {
        if (!is_checkout() && !is_cart() && !is_product()) {
            return;
        }
        if (!$this->should_use_recaptcha()) {
            return;
        }
        $is_blocks = has_block('woocommerce/checkout') || has_block('woocommerce/cart');
        wp_enqueue_script('ppcp-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($this->integration->get_option('site_key_v3')), array(), $this->asset_version, \true);
        $dependencies = array('ppcp-recaptcha');
        if ($is_blocks) {
            $dependencies[] = 'wp-data';
        }
        wp_enqueue_script('ppcp-recaptcha-handler', $this->asset_getter->get_asset_url('recaptcha-handler.js'), $dependencies, $this->asset_version, \true);
        wp_localize_script('ppcp-recaptcha-handler', 'ppcpRecaptchaSettings', array('siteKeyV3' => $this->integration->get_option('site_key_v3'), 'siteKeyV2' => $this->integration->get_option('site_key_v2'), 'theme' => $this->integration->get_option('v2_theme', 'light'), 'isBlocks' => $is_blocks, 'isCheckout' => is_checkout(), 'isCart' => is_cart(), 'isSingleProduct' => is_product(), 'v2ContainerId' => self::V2_CONTAINER_ID, 'errorCodeMissingToken' => self::ERROR_CODE_MISSING_TOKEN, 'errorCodeVerificationFailed' => self::ERROR_CODE_VERIFICATION_FAILED));
    }
    public function render_v2_container(): string
    {
        return '<div id="' . esc_attr(self::V2_CONTAINER_ID) . '" style="margin:20px 0;"></div>';
    }
    public function intercept_paypal_ajax(array $request_data): void
    {
        if (!$this->should_use_recaptcha()) {
            return;
        }
        $token = sanitize_text_field(wp_unslash($request_data['ppcp_recaptcha_token'] ?? ''));
        $version = sanitize_text_field(wp_unslash($request_data['ppcp_recaptcha_version'] ?? ''));
        if (empty($token)) {
            wp_send_json_error(array('message' => __('Please complete the CAPTCHA verification.', 'woocommerce-paypal-payments'), 'code' => self::ERROR_CODE_MISSING_TOKEN), 400);
            exit;
        }
        $success = $version === 'v3' ? $this->verify_v3($token, $this->integration->get_option('secret_key_v3'), $this->score_threshold()) : $this->verify_v2($token, $this->integration->get_option('secret_key_v2'));
        if (!$success) {
            if ($version === 'v3') {
                $content = $request_data;
                // Sending only form to reduce the amount of data.
                if (isset($request_data['form'])) {
                    $content = $request_data['form'];
                }
                $this->log_rejection('Create Order AJAX', $content);
            }
            wp_send_json_error(array('message' => __('CAPTCHA verification failed. Please try again.', 'woocommerce-paypal-payments'), 'code' => self::ERROR_CODE_VERIFICATION_FAILED), 403);
            exit;
        }
    }
    public function validate_classic_checkout(): void
    {
        if (!$this->should_use_recaptcha()) {
            return;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification handled by WooCommerce before this hook fires
        /** @psalm-suppress PossiblyInvalidCast */
        $payment_method = sanitize_text_field(wp_unslash((string) ($_POST['payment_method'] ?? '')));
        /** @psalm-suppress PossiblyInvalidCast */
        $token = sanitize_text_field(wp_unslash((string) ($_POST['ppcp_recaptcha_token'] ?? '')));
        /** @psalm-suppress PossiblyInvalidCast */
        $version = sanitize_text_field(wp_unslash((string) ($_POST['ppcp_recaptcha_version'] ?? '')));
        if (!in_array($payment_method, $this->payment_methods, \true)) {
            return;
        }
        if (empty($token)) {
            wc_add_notice(__('Please complete the CAPTCHA verification.', 'woocommerce-paypal-payments'), 'error');
            return;
        }
        $success = $version === 'v3' ? $this->verify_v3($token, $this->integration->get_option('secret_key_v3'), $this->score_threshold()) : $this->verify_v2($token, $this->integration->get_option('secret_key_v2'));
        if (!$success) {
            if ($version === 'v3') {
                $content = $_POST;
                $this->log_rejection('Classic Checkout Submission', $content);
            }
            wc_add_notice(__('CAPTCHA verification failed. Please try again.', 'woocommerce-paypal-payments'), 'error');
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
    /**
     * @param  WP_Error|null|true $errors
     *
     * @return WP_Error|null|true WP_Error
     */
    public function validate_blocks_request($errors)
    {
        $request_uri = sanitize_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        if (!is_wp_error($errors) && strpos($request_uri, '/wc/store/v1/checkout') !== \false) {
            if (!$this->should_use_recaptcha()) {
                return $errors;
            }
            $request_body = file_get_contents('php://input');
            if (!is_string($request_body)) {
                return $errors;
            }
            $data = json_decode($request_body, \true);
            // Not an order creation request.
            if (!is_array($data) || !isset($data['billing_address'])) {
                return $errors;
            }
            $ext_data = $data['extensions']['ppcp_recaptcha'] ?? null;
            $payment_method = sanitize_text_field($data['payment_method'] ?? '');
            if (!in_array($payment_method, $this->payment_methods, \true)) {
                return $errors;
            }
            if (empty($ext_data) || empty($ext_data['token']) || empty($ext_data['version'])) {
                return new WP_Error(self::ERROR_CODE_MISSING_TOKEN, __('Please complete the CAPTCHA verification.', 'woocommerce-paypal-payments'), array('status' => 400));
            }
            $token = sanitize_text_field($ext_data['token']);
            $version = sanitize_text_field($ext_data['version']);
            // Initialize WooCommerce session as it doesn't exist in REST API requests.
            WC()->initialize_session();
            $success = $version === 'v3' ? $this->verify_v3($token, $this->integration->get_option('secret_key_v3'), $this->score_threshold()) : $this->verify_v2($token, $this->integration->get_option('secret_key_v2'));
            if (!$success) {
                if ($version === 'v3') {
                    $content = $data;
                    unset($content['extensions']);
                    $this->log_rejection('Block Checkout Submission', $content);
                }
                return new WP_Error(self::ERROR_CODE_VERIFICATION_FAILED, __('CAPTCHA verification failed. Please try again.', 'woocommerce-paypal-payments'), array('status' => 403));
            }
        }
        return $errors;
    }
    public function add_result_meta(WC_Order $order): void
    {
        $customer_id = $this->customer_identifier();
        if (!$customer_id) {
            $this->logger->debug('Skipping reCAPTCHA meta addition: No customer identifier available', array('order_id' => $order->get_id(), 'backtrace' => \true));
            return;
        }
        $result = get_transient(self::CAPTCHA_RESULT_TRANSIENT_KEY . $customer_id);
        if (!$result) {
            return;
        }
        $order->update_meta_data(self::CAPTCHA_RESULT_META_KEY, $result);
        $order->save();
        delete_transient(self::CAPTCHA_RESULT_TRANSIENT_KEY . $customer_id);
    }
    public function add_metabox(): void
    {
        if (!wc_string_to_bool($this->integration->get_option('show_metabox'))) {
            return;
        }
        $screen = OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';
        add_meta_box(
            'ppcp_recaptcha_status',
            __('reCAPTCHA Status', 'woocommerce-paypal-payments'),
            /**
             * @param $order WC_Order|WP_Post
             * @psalm-suppress MissingClosureParamType
             */
            function ($order): void {
                $order = $order instanceof WC_Order ? $order : wc_get_order($order);
                if (!$order instanceof WC_Order) {
                    return;
                }
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $this->render_metabox($order);
            },
            $screen,
            'normal'
        );
    }
    private function render_metabox(WC_Order $order): string
    {
        $captcha_result = $order->get_meta(self::CAPTCHA_RESULT_META_KEY);
        if (empty($captcha_result)) {
            return '<p>' . esc_html__('No reCAPTCHA data', 'woocommerce-paypal-payments') . '</p>';
        }
        // Truncate token to last 10 characters for display.
        if (isset($captcha_result['token']) && is_string($captcha_result['token'])) {
            if (strlen($captcha_result['token']) > 10) {
                $captcha_result['token'] = '...' . (string) substr($captcha_result['token'], -10);
            }
        }
        return '<pre>' . esc_html((string) wp_json_encode($captcha_result, \JSON_PRETTY_PRINT)) . '</pre>';
    }
    private function verify_v3(string $token, string $secret, float $threshold): bool
    {
        if ($this->check_cached_verification($token)) {
            return \true;
        }
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array('body' => array('secret' => $secret, 'response' => $token, 'remoteip' => $this->customer_ip())));
        if (is_wp_error($response)) {
            $this->logger->error('reCAPTCHA v3 API error: ' . $response->get_error_message());
            return \false;
        }
        $result = json_decode(wp_remote_retrieve_body($response), \true);
        $score = isset($result['score']) ? floatval($result['score']) : 0;
        $is_above_threshold = !empty($result['success']) && $score >= $threshold;
        $is_valid = apply_filters('woocommerce_paypal_payments_recaptcha_verify_v3_result', $is_above_threshold, $threshold, $result);
        $this->last_v3_score = $score;
        if ($is_valid) {
            $customer_id = $this->customer_identifier();
            if ($customer_id) {
                $cached_data = array('result' => $result, 'token' => $token, 'usage_count' => 1);
                set_transient(self::CAPTCHA_RESULT_TRANSIENT_KEY . $customer_id, $cached_data, 300);
            } else {
                $this->logger->debug('reCAPTCHA v3 verification successful but not cached: No customer identifier available', array('backtrace' => \true));
            }
        }
        return $is_valid;
    }
    private function verify_v2(string $token, string $secret): bool
    {
        if ($this->check_cached_verification($token)) {
            return \true;
        }
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array('body' => array('secret' => $secret, 'response' => $token, 'remoteip' => $this->customer_ip())));
        if (is_wp_error($response)) {
            $this->logger->error('reCAPTCHA v2 API error: ' . $response->get_error_message());
            return \false;
        }
        $result = json_decode(wp_remote_retrieve_body($response), \true);
        $is_valid = apply_filters('woocommerce_paypal_payments_recaptcha_verify_v2_result', $result['success'], $result);
        if ($is_valid) {
            $customer_id = $this->customer_identifier();
            if ($customer_id) {
                $cached_data = array('result' => $result, 'token' => $token, 'usage_count' => 1);
                set_transient(self::CAPTCHA_RESULT_TRANSIENT_KEY . $customer_id, $cached_data, 300);
            } else {
                $this->logger->debug('reCAPTCHA v2 verification successful but not cached: No customer identifier available', array('backtrace' => \true));
            }
        }
        return $is_valid;
    }
    private function check_cached_verification(string $token): bool
    {
        $customer_id = $this->customer_identifier();
        if (!$customer_id) {
            $this->logger->debug('Skipping cached verification check: No customer identifier available', array('backtrace' => \true));
            return \false;
        }
        $cached_data = get_transient(self::CAPTCHA_RESULT_TRANSIENT_KEY . $customer_id);
        if ($cached_data === \false || !isset($cached_data['usage_count'], $cached_data['token'])) {
            return \false;
        }
        if ($cached_data['token'] === $token && $cached_data['usage_count'] < self::CAPTCHA_USAGE_LIMIT) {
            ++$cached_data['usage_count'];
            set_transient(self::CAPTCHA_RESULT_TRANSIENT_KEY . $customer_id, $cached_data, 300);
            return \true;
        }
        if ($cached_data['usage_count'] >= self::CAPTCHA_USAGE_LIMIT) {
            delete_transient(self::CAPTCHA_RESULT_TRANSIENT_KEY . $customer_id);
        }
        return \false;
    }
    private function customer_identifier(): ?string
    {
        if (!WC()->session || !WC()->session->get_customer_id()) {
            return null;
        }
        return (string) WC()->session->get_customer_id();
    }
    private function customer_ip(): string
    {
        return filter_var(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''), \FILTER_VALIDATE_IP) ?: '';
    }
    private function customer_user_agent(): string
    {
        return sanitize_text_field((string) wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
    private function score_threshold(): float
    {
        return floatval($this->integration->get_option('score_threshold', 0.5));
    }
    private function cart_contents(): array
    {
        $cart = WC()->cart;
        if (!isset($cart) || $cart->is_empty()) {
            return array();
        }
        $cart_data = array('items' => array(), 'totals' => array('subtotal' => $cart->subtotal, 'tax' => $cart->get_total_tax(), 'shipping' => $cart->get_shipping_total(), 'total' => $cart->total));
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if (!$product instanceof WC_Product) {
                continue;
            }
            $cart_data['items'][] = array('product_id' => $cart_item['product_id'], 'name' => $product->get_name(), 'quantity' => $cart_item['quantity'], 'price' => $product->get_price(), 'line_total' => $cart_item['line_total'], 'variation' => $cart_item['variation'] ?? array());
        }
        return $cart_data;
    }
    private function log_rejection(string $endpoint_name, array $request_data): void
    {
        $this->rejection_counter->increment();
        if (!wc_string_to_bool($this->integration->get_option('log_rejections'))) {
            return;
        }
        $ip = $this->customer_ip();
        $user_agent = $this->customer_user_agent();
        unset($request_data['ppcp_recaptcha_token']);
        unset($request_data['ppcp_recaptcha_version']);
        unset($request_data['g-recaptcha-response']);
        $cart = $this->cart_contents();
        $this->logger->debug("Rejected by v3 reCAPTCHA at {$endpoint_name} with score {$this->last_v3_score}, IP: {$ip}, User Agent: {$user_agent}.", array('source' => self::REJECTION_LOGGER_SOURCE, 'request' => $request_data, 'cart' => $cart));
    }
}
