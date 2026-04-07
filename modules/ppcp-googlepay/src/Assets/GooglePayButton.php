<?php

/**
 * Registers and configures the necessary Javascript for the button, credit messaging and DCC
 * fields.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Googlepay\Assets;

use Exception;
use WC_Countries;
use WC_AJAX;
use WC_Product;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Googlepay\Endpoint\UpdatePaymentDataEndpoint;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
class GooglePayButton implements ButtonInterface
{
    private Context $context;
    private AssetGetter $asset_getter;
    private string $sdk_url;
    private string $version;
    private SettingsProvider $settings;
    private Environment $environment;
    private SubscriptionHelper $subscription_helper;
    public function __construct(AssetGetter $asset_getter, string $sdk_url, string $version, SubscriptionHelper $subscription_helper, SettingsProvider $settings, Environment $environment, Context $context)
    {
        $this->asset_getter = $asset_getter;
        $this->sdk_url = $sdk_url;
        $this->version = $version;
        $this->subscription_helper = $subscription_helper;
        $this->settings = $settings;
        $this->environment = $environment;
        $this->context = $context;
    }
    public function initialize(): void
    {
    }
    /**
     * Returns if Google Pay button is enabled
     */
    public function is_enabled(): bool
    {
        if (!$this->settings->googlepay_enabled()) {
            return \false;
        }
        $methods = $this->settings->button_styling($this->context->context())->methods;
        return in_array(GooglePayGateway::ID, $methods, \true);
    }
    /**
     * Registers the necessary action hooks to render the HTML depending on the settings.
     *
     * @return bool
     *
     * @psalm-suppress RedundantCondition
     */
    public function render(): bool
    {
        if (!$this->is_enabled()) {
            return \false;
        }
        if ($this->subscription_helper->plugin_is_active() && !$this->subscription_helper->accept_manual_renewals()) {
            if (is_product() && $this->subscription_helper->current_product_is_subscription()) {
                return \false;
            }
            if ($this->subscription_helper->order_pay_contains_subscription()) {
                return \false;
            }
            if ($this->subscription_helper->cart_contains_subscription()) {
                return \false;
            }
        }
        /**
         * Param types removed to avoid third-party issues.
         *
         * @psalm-suppress MissingClosureParamType
         */
        add_filter('woocommerce_paypal_payments_sdk_components_hook', function ($components) {
            $components[] = 'googlepay';
            return $components;
        });
        $button_hooks = array(array('hook' => 'woocommerce_paypal_payments_single_product_button_render', 'filter' => 'woocommerce_paypal_payments_googlepay_single_product_button_render_hook', 'callback' => fn() => $this->googlepay_button(), 'priority' => 32), array('hook' => 'woocommerce_paypal_payments_cart_button_render', 'filter' => 'woocommerce_paypal_payments_googlepay_cart_button_render_hook', 'callback' => fn() => $this->googlepay_button()), array('hook' => 'woocommerce_paypal_payments_checkout_button_render', 'filter' => 'woocommerce_paypal_payments_googlepay_checkout_button_render_hook', 'callback' => function () {
            $this->googlepay_button();
            $this->hide_gateway_until_eligible();
        }), array('hook' => 'woocommerce_paypal_payments_payorder_button_render', 'filter' => 'woocommerce_paypal_payments_googlepay_payorder_button_render_hook', 'callback' => function () {
            $this->googlepay_button();
            $this->hide_gateway_until_eligible();
        }), array('hook' => 'woocommerce_paypal_payments_minicart_button_render', 'filter' => 'woocommerce_paypal_payments_googlepay_minicart_button_render_hook', 'callback' => fn() => print '<span id="ppc-button-googlepay-container-minicart" class="ppcp-button-apm ppcp-button-googlepay ppcp-button-minicart"></span>'));
        foreach ($button_hooks as $entry) {
            $hook = apply_filters($entry['filter'], $entry['hook']);
            $hook = is_string($hook) ? $hook : $entry['hook'];
            add_action($hook, $entry['callback'], $entry['priority'] ?? 21);
        }
        return \true;
    }
    /**
     * GooglePay button markup
     */
    private function googlepay_button(): void
    {
        ?>
		<div id="ppc-button-googlepay-container" class="ppcp-button-apm ppcp-button-googlepay">
			<?php 
        wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce');
        ?>
		</div>
		<?php 
    }
    /**
     * Outputs an inline CSS style that hides the Google Pay gateway (on Classic Checkout).
     * The style is removed by `PaymentButton.js` once the eligibility of the payment method
     * is confirmed.
     *
     * @return void
     */
    protected function hide_gateway_until_eligible(): void
    {
        ?>
		<style data-hide-gateway='<?php 
        echo esc_attr(GooglePayGateway::ID);
        ?>'>
			.wc_payment_method.payment_method_ppcp-googlepay {
				display: none;
			}
		</style>
		<?php 
    }
    /**
     * Enqueues scripts/styles.
     */
    public function enqueue(): void
    {
        if (!$this->is_enabled()) {
            return;
        }
        wp_register_script('wc-ppcp-googlepay', $this->asset_getter->get_asset_url('boot.js'), array(), $this->version, \true);
        wp_enqueue_script('wc-ppcp-googlepay');
        $this->enqueue_styles();
        wp_localize_script('wc-ppcp-googlepay', 'wc_ppcp_googlepay', $this->script_data());
    }
    /**
     * Enqueues styles.
     */
    public function enqueue_styles(): void
    {
        if (!$this->is_enabled()) {
            return;
        }
        wp_register_style('wc-ppcp-googlepay', $this->asset_getter->get_asset_url('styles.css'), array(), $this->version);
        wp_enqueue_style('wc-ppcp-googlepay');
    }
    /**
     * Enqueues scripts/styles for admin.
     */
    public function enqueue_admin(): void
    {
        wp_register_style('wc-ppcp-googlepay-admin', $this->asset_getter->get_asset_url('styles.css'), array(), $this->version);
        wp_enqueue_style('wc-ppcp-googlepay-admin');
        wp_register_script('wc-ppcp-googlepay-admin', $this->asset_getter->get_asset_url('boot-admin.js'), array(), $this->version, \true);
        wp_enqueue_script('wc-ppcp-googlepay-admin');
        wp_localize_script('wc-ppcp-googlepay-admin', 'wc_ppcp_googlepay_admin', $this->script_data());
    }
    /**
     * The configuration for the smart buttons.
     *
     * @return array
     * @throws NotFoundException If the settings are not found.
     */
    public function script_data(): array
    {
        $use_shipping_form = $this->should_use_shipping();
        // On the product page, only show the shipping form for physical products.
        $context = $this->context->context();
        if ($use_shipping_form && 'product' === $context) {
            $product = wc_get_product();
            if (!$product || $product->is_downloadable() || $product->is_virtual()) {
                $use_shipping_form = \false;
            }
        }
        if (!is_null(WC()->cart) && !WC()->cart->needs_shipping()) {
            $use_shipping_form = \false;
        }
        $shipping = array('enabled' => $use_shipping_form, 'configured' => wc_shipping_enabled() && wc_get_shipping_method_count(\false, \true) > 0);
        if ($shipping['enabled']) {
            $shipping['countries'] = array_keys($this->wc_countries()->get_shipping_countries());
        }
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $is_wc_gateway_enabled = isset($available_gateways[GooglePayGateway::ID]);
        return array(
            'environment' => $this->environment->is_sandbox() ? 'TEST' : 'PRODUCTION',
            'is_debug' => defined('WP_DEBUG') && WP_DEBUG,
            // @phpstan-ignore booleanAnd.rightAlwaysFalse
            'is_enabled' => $this->is_enabled(),
            'is_wc_gateway_enabled' => $is_wc_gateway_enabled,
            'sdk_url' => $this->sdk_url,
            'button' => array(
                'wrapper' => '#ppc-button-googlepay-container',
                // style: For now we use cart. Pass the context if necessary.
                'style' => $this->button_styles_for_context('cart'),
                'mini_cart_wrapper' => '#ppc-button-googlepay-container-minicart',
                'mini_cart_style' => $this->button_styles_for_context('mini-cart'),
            ),
            'shipping' => $shipping,
            'ajax' => array('update_payment_data' => array('endpoint' => WC_AJAX::get_endpoint(UpdatePaymentDataEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(UpdatePaymentDataEndpoint::nonce()))),
        );
    }
    /**
     * Determines the style for a given indicator in a given context.
     *
     * @param string $context The context.
     *
     * @return array
     */
    private function button_styles_for_context(string $context): array
    {
        $styles = $this->settings->googlepay_styles($context);
        return array('color' => $styles->color, 'type' => $styles->label, 'language' => $this->settings->googlepay_button_language());
    }
    /**
     * Returns a WC_Countries instance to check shipping
     *
     * @return WC_Countries
     */
    private function wc_countries(): WC_Countries
    {
        return new WC_Countries();
    }
    private function should_use_shipping(): bool
    {
        if (!$this->settings->enable_pay_now()) {
            return \false;
        }
        $context = $this->context->context();
        // On the product page, only show shipping if a physical product.
        if ('product' === $context) {
            $product = wc_get_product();
            return $product instanceof WC_Product && !$product->is_downloadable() && !$product->is_virtual();
        }
        // On other pages, just check the cart.
        return !is_null(WC()->cart) && WC()->cart->needs_shipping();
    }
}
