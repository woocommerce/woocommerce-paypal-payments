<?php

/**
 * The local alternative payment methods module.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WC_Order;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\FeesUpdater;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
/**
 * Class LocalAlternativePaymentMethodsModule
 */
class LocalAlternativePaymentMethodsModule implements ServiceModule, ExtendingModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * Payment methods configuration.
     *
     * @var array
     */
    private array $payment_methods = array();
    /**
     * {@inheritDoc}
     */
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
    /**
     * {@inheritDoc}
     */
    public function extensions(): array
    {
        return require __DIR__ . '/../extensions.php';
    }
    /**
     * {@inheritDoc}
     */
    public function run(ContainerInterface $c): bool
    {
        add_action('after_setup_theme', fn() => $this->run_with_translations($c));
        return \true;
    }
    /**
     * Set up WP hooks that depend on translation features.
     * Runs after the theme setup, when translations are available, which is fired
     * before the `init` hook, which usually contains most of the logic.
     *
     * @param ContainerInterface $c The DI container.
     * @return void
     */
    private function run_with_translations(ContainerInterface $c): void
    {
        $this->payment_methods = $c->get('ppcp-local-apms.payment-methods');
        // When Local APMs are disabled, none of the following hooks are needed.
        if (!$this->should_add_local_apm_gateways($c)) {
            return;
        }
        $this->register_pwc_feature_flag_filters();
        add_action('wp_enqueue_scripts', function () use ($c) {
            if (!is_checkout() && !is_cart() && !is_wc_endpoint_url('order-pay')) {
                return;
            }
            $asset_getter = $c->get('ppcp-local-apms.asset_getter');
            assert($asset_getter instanceof AssetGetter);
            $asset_version = $c->get('ppcp.asset-version');
            wp_enqueue_style('ppcp-local-apms-gateway', $asset_getter->get_asset_url('gateway.css'), array(), $asset_version);
        });
        /**
         * The "woocommerce_payment_gateways" filter is responsible for ADDING
         * custom payment gateways to WooCommerce. Here, we add all the local
         * APM gateways to the filtered list, so they become available later on.
         */
        add_filter(
            'woocommerce_payment_gateways',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($methods) use ($c) {
                if (!is_array($methods)) {
                    return $methods;
                }
                $payment_methods = $c->get('ppcp-local-apms.payment-methods');
                $payment_methods = apply_filters('woocommerce_paypal_payments_local_apm_payment_methods', $payment_methods);
                foreach ($payment_methods as $key => $value) {
                    $methods[] = $c->get('ppcp-local-apms.' . $key . '.wc-gateway');
                }
                return $methods;
            }
        );
        /**
         * Filters the "available gateways" list by REMOVING gateways that
         * are not available for the current customer.
         */
        add_filter(
            'woocommerce_available_payment_gateways',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($methods) use ($c) {
                if (!is_array($methods) || is_admin() || empty(WC()->customer)) {
                    // Don't restrict the gateway list on wp-admin or when no customer is known.
                    return $methods;
                }
                $payment_methods = $c->get('ppcp-local-apms.payment-methods');
                /**
                 * Filter the payment methods array before checking availability.
                 *
                 * @param array $payment_methods The payment methods configuration array.
                 * @return array The filtered payment methods array.
                 */
                $payment_methods = apply_filters('woocommerce_paypal_payments_local_apm_payment_methods', $payment_methods);
                $customer_country = WC()->customer->get_billing_country() ?: WC()->customer->get_shipping_country();
                $site_currency = get_woocommerce_currency();
                // Remove unsupported gateways from the customer's payment options.
                foreach ($payment_methods as $payment_method) {
                    // Empty arrays mean "allow all" - skip restriction checks.
                    $is_currency_supported = empty($payment_method['currencies']) || in_array($site_currency, $payment_method['currencies'], \true);
                    $is_country_supported = empty($payment_method['countries']) || in_array($customer_country, $payment_method['countries'], \true);
                    if (!$is_currency_supported || !$is_country_supported) {
                        unset($methods[$payment_method['id']]);
                    }
                }
                return $methods;
            }
        );
        /**
         * Adds all local APM gateways in the "payment_method_type" block registry
         * to make the payment methods available in the Block Checkout.
         *
         * @see IntegrationRegistry::initialize
         */
        add_action('woocommerce_blocks_payment_method_type_registration', function (PaymentMethodRegistry $payment_method_registry) use ($c): void {
            $payment_methods = $c->get('ppcp-local-apms.payment-methods');
            /**
             * Filter the payment methods array before registering block payment methods.
             *
             * @param array $payment_methods The payment methods configuration array.
             * @return array The filtered payment methods array.
             */
            $payment_methods = apply_filters('woocommerce_paypal_payments_local_apm_payment_methods', $payment_methods);
            foreach ($payment_methods as $key => $value) {
                $payment_method_registry->register($c->get('ppcp-local-apms.' . $key . '.payment-method'));
            }
        });
        add_filter('woocommerce_paypal_payments_localized_script_data', function (array $data) use ($c) {
            $payment_methods = $c->get('ppcp-local-apms.payment-methods');
            /**
             * Filter the payment methods array before adding to disable-funding.
             *
             * @param array $payment_methods The payment methods configuration array.
             * @return array The filtered payment methods array.
             */
            $payment_methods = apply_filters('woocommerce_paypal_payments_local_apm_payment_methods', $payment_methods);
            $default_disable_funding = $data['url_params']['disable-funding'] ?? '';
            $disable_funding = array_merge(array_keys($payment_methods), array_filter(explode(',', $default_disable_funding)));
            $data['url_params']['disable-funding'] = implode(',', array_unique($disable_funding));
            return $data;
        });
        add_action('woocommerce_before_thankyou', array($this, 'handle_cancelled_local_apm'));
        add_action('template_redirect', array($this, 'handle_pwc_order_received_redirect'));
        add_action('woocommerce_paypal_payments_payment_capture_completed_webhook_handler', function (WC_Order $wc_order, string $order_id) use ($c) {
            $payment_methods = $c->get('ppcp-local-apms.payment-methods');
            if (!$this->is_local_apm($wc_order->get_payment_method(), $payment_methods)) {
                return;
            }
            $fees_updater = $c->get('wcgateway.helper.fees-updater');
            assert($fees_updater instanceof FeesUpdater);
            $fees_updater->update($order_id, $wc_order);
        }, 10, 2);
    }
    /**
     * Handle cancelled local APM payments on the thank you page.
     *
     * @param int $order_id The order ID.
     * @return void
     */
    public function handle_cancelled_local_apm($order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $cancelled = wc_clean(wp_unslash($_GET['cancelled'] ?? ''));
        $cancelled = is_array($cancelled) ? '' : (string) $cancelled;
        $order_key = wc_clean(wp_unslash($_GET['key'] ?? ''));
        $order_key = is_array($order_key) ? '' : (string) $order_key;
        // phpcs:enable
        if (!$this->is_local_apm($order->get_payment_method(), $this->payment_methods) || !$cancelled || $order->get_order_key() !== $order_key) {
            return;
        }
        if ($order->get_payment_method() === \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway::ID) {
            $this->handle_cancelled_crypto_payment($order, $cancelled);
            return;
        }
        $this->handle_cancelled_standard_apm($order);
    }
    /**
     * Handle cancelled crypto payments.
     *
     * @param WC_Order $order The WooCommerce order.
     * @param string   $cancelled The cancelled parameter value.
     * @return void
     */
    private function handle_cancelled_crypto_payment(WC_Order $order, string $cancelled): void
    {
        if (!$cancelled) {
            return;
        }
        if ($order->get_status() === 'on-hold') {
            $order->update_status('failed', __('Pay with Crypto payment was cancelled or failed.', 'woocommerce-paypal-payments'));
            $order->add_order_note(__('Payment was cancelled during the Pay with Crypto payment process.', 'woocommerce-paypal-payments'), 1);
        }
        add_filter('woocommerce_order_has_status', '__return_true');
        if (!wp_doing_ajax() && !is_admin()) {
            $clean_url = isset($_SERVER['REQUEST_URI']) ? remove_query_arg('cancelled', esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))) : '';
            wp_safe_redirect(home_url($clean_url));
            exit;
        }
    }
    /**
     * Handle cancelled standard APM payments (non-crypto).
     *
     * @param WC_Order $order The WooCommerce order.
     * @return void
     */
    private function handle_cancelled_standard_apm(WC_Order $order): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $error_code = wc_clean(wp_unslash($_GET['errorcode'] ?? ''));
        if ($error_code === 'processing_error' || $error_code === 'payment_error') {
            $order->update_status('failed', __("The payment can't be processed because of an error.", 'woocommerce-paypal-payments'));
            add_filter('woocommerce_order_has_status', '__return_true');
        }
    }
    /**
     * Check if given payment method is a local APM.
     *
     * @param string $selected_payment_method Selected payment method.
     * @param array  $payment_methods Available local APMs.
     * @return bool
     */
    private function is_local_apm(string $selected_payment_method, array $payment_methods): bool
    {
        foreach ($payment_methods as $payment_method) {
            if ($payment_method['id'] === $selected_payment_method) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * Check if the local APMs should be added to the available payment gateways.
     *
     * @param ContainerInterface $container Container.
     * @return bool
     */
    private function should_add_local_apm_gateways(ContainerInterface $container): bool
    {
        // APMs are only available after merchant onboarding is completed.
        $is_connected = $container->get('settings.flag.is-connected');
        if (!$is_connected) {
            /**
             * When the merchant is _not_ connected yet, we still need to
             * register the APM gateways in one case:
             *
             * During the authentication process (which happens via a REST call)
             * the gateways need to be present, so they can be correctly
             * pre-configured for new merchants.
             */
            return $this->is_rest_request();
        }
        // The general plugin functionality must be enabled.
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        if (!$settings->has('enabled') || !$settings->get('enabled')) {
            return \false;
        }
        // Register APM gateways, when the relevant setting is active.
        return $settings->has('allow_local_apm_gateways') && $settings->get('allow_local_apm_gateways') === \true;
    }
    /**
     * Register PWC feature flag filters.
     *
     * @return void
     */
    private function register_pwc_feature_flag_filters(): void
    {
        /**
         * Filter payment methods array to exclude PWC when feature flag is disabled.
         */
        add_filter('woocommerce_paypal_payments_local_apm_payment_methods', function (array $methods) {
            if ($this->is_pwc_feature_enabled()) {
                return $methods;
            }
            // Remove PWC from payment methods array when feature flag is disabled.
            return array_filter($methods, function ($method) {
                return $method['id'] !== 'ppcp-pwc';
            });
        });
        /**
         * Filter APM gateway list to conditionally exclude PWC.
         */
        add_filter('woocommerce_paypal_payments_gateway_group_apm', function (array $group): array {
            if (!$this->is_pwc_feature_enabled()) {
                $group = array_filter($group, function ($method) {
                    return $method['id'] !== 'ppcp-pwc';
                });
            }
            return $group;
        });
        /**
         * Filter todos list to conditionally exclude PWC-related todos.
         */
        add_filter('woocommerce_paypal_payments_todos_list', function (array $todos): array {
            if (!$this->is_pwc_feature_enabled()) {
                unset($todos['enable_pwc']);
                unset($todos['apply_for_pwc']);
            }
            return $todos;
        });
        /**
         * Filter features list to conditionally exclude PWC feature.
         */
        add_filter('woocommerce_paypal_payments_features_list', function (array $features): array {
            if (!$this->is_pwc_feature_enabled()) {
                unset($features[FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO]);
            }
            return $features;
        });
        /**
         * Filter localized script data to exclude PWC from disable-funding list.
         */
        add_filter('woocommerce_paypal_payments_localized_script_data', function (array $data) {
            if (!$this->is_pwc_feature_enabled()) {
                return $data;
            }
            $current_disable_funding = $data['url_params']['disable-funding'] ?? '';
            $funding_sources = array_filter(explode(',', $current_disable_funding));
            $funding_sources = array_filter($funding_sources, function ($source) {
                return $source !== 'pwc';
            });
            $data['url_params']['disable-funding'] = implode(',', array_unique($funding_sources));
            return $data;
        }, 11);
    }
    /**
     * Handle PWC order received page redirect to strip token parameter.
     *
     * PayPal automatically appends a 'token' parameter to return URLs after crypto payments.
     * When the 'token' parameter is present on the order-received page, WooCommerce displays
     * a minimal page instead of the full order details.
     *
     * This intercepts PWC orders on 'template_redirect' (before any output buffering)
     * and redirects to a clean URL without the token, allowing WooCommerce to display
     * the full order-received page.
     *
     * Note: PWC uses ORDER_COMPLETE_ON_PAYMENT_APPROVAL, so the token serves no purpose
     * but we can't prevent PayPal from appending it.
     *
     * @return void
     */
    public function handle_pwc_order_received_redirect(): void
    {
        // Only run on order-received endpoint.
        if (!is_wc_endpoint_url('order-received')) {
            return;
        }
        // Check if 'token' exists anywhere in the URL first.
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (strpos($request_uri, 'token=') === \false) {
            return;
        }
        // Get order ID from the URL endpoint.
        global $wp;
        $order_id = isset($wp->query_vars['order-received']) ? absint($wp->query_vars['order-received']) : 0;
        if (!$order_id) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order instanceof WC_Order) {
            return;
        }
        // Only handle PWC - other payment methods may use 'token' legitimately (e.g., 3DS).
        if ($order->get_payment_method() !== \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway::ID) {
            return;
        }
        wp_safe_redirect($order->get_checkout_order_received_url());
        exit;
    }
    /**
     * Checks, whether the current request is trying to access a WooCommerce REST endpoint.
     *
     * @return bool True, if the request path matches the WC-Rest namespace.
     */
    private function is_rest_request(): bool
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $request_uri = wp_unslash($_SERVER['REQUEST_URI'] ?? '');
        return str_contains($request_uri, '/wp-json/wc/');
    }
    /**
     * Check if PWC (Pay with Crypto) feature flag is enabled.
     *
     * @return bool True if PWC is enabled, false otherwise.
     */
    private function is_pwc_feature_enabled(): bool
    {
        return apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
            'woocommerce.feature-flags.woocommerce_paypal_payments.pwc_enabled',
            getenv('PCP_PWC_ENABLED') === '1'
        );
    }
}
