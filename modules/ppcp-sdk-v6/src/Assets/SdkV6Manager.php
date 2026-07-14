<?php

/**
 * Manages the SDK v6 frontend assets.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Blocks\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
/**
 * Class SdkV6Manager
 */
class SdkV6Manager
{
    public const WRAPPER_ID = 'ppc-button-ppcp-gateway-v6';
    public const MINI_CART_WRAPPER_ID = 'ppc-button-minicart-v6';
    /**
     * The asset getter.
     *
     * @var AssetGetter
     */
    private AssetGetter $asset_getter;
    /**
     * The assets version.
     *
     * @var string
     */
    private string $version;
    /**
     * The environment object.
     *
     * @var Environment
     */
    private Environment $environment;
    /**
     * The button style mapper.
     *
     * @var ButtonStyleMapper
     */
    private ButtonStyleMapper $style_mapper;
    /**
     * Whether shipping should be handled inside the PayPal popup.
     *
     * @var bool
     */
    private bool $should_handle_shipping;
    /**
     * The settings status helper (per-location button enablement).
     *
     * @var SettingsStatus
     */
    private SettingsStatus $settings_status;
    /**
     * The page context helper.
     *
     * @var Context
     */
    private Context $context;
    /**
     * SdkV6Manager constructor.
     *
     * @param AssetGetter       $asset_getter The asset getter.
     * @param string            $version The assets version.
     * @param Environment       $environment The environment object.
     * @param ButtonStyleMapper $style_mapper The button style mapper.
     * @param bool              $should_handle_shipping Whether to handle shipping in PayPal.
     * @param SettingsStatus    $settings_status The settings status helper.
     * @param Context           $context The page context helper.
     */
    public function __construct(AssetGetter $asset_getter, string $version, Environment $environment, ButtonStyleMapper $style_mapper, bool $should_handle_shipping, SettingsStatus $settings_status, Context $context)
    {
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->environment = $environment;
        $this->style_mapper = $style_mapper;
        $this->should_handle_shipping = $should_handle_shipping;
        $this->settings_status = $settings_status;
        $this->context = $context;
    }
    /**
     * Enqueues scripts/styles.
     *
     * @return void
     */
    public function enqueue(): void
    {
        if (!$this->should_load_on_current_page()) {
            return;
        }
        $script_url = $this->asset_getter->get_asset_url('boot.js');
        if (!$script_url) {
            return;
        }
        wp_register_script('wc-ppcp-sdk-v6-boot', $script_url, array(), $this->version, \true);
        wp_localize_script('wc-ppcp-sdk-v6-boot', 'wc_ppcp_sdk_v6', $this->script_data());
        wp_enqueue_script('wc-ppcp-sdk-v6-boot');
    }
    /**
     * Registers the render hooks that output the button wrapper elements.
     *
     * Uses the same theme hooks as the v5 SmartButton so v6 buttons appear
     * in the same locations.
     *
     * @return void
     */
    public function register_render_hooks(): void
    {
        // Activate is_cart()/is_checkout() on classic-shortcode block pages;
        // otherwise this only happens as a side effect of constructing the
        // (discarded) v5 SmartButton.
        $this->context->init_context();
        if ($this->settings_status->is_smart_button_enabled_for_location('product')) {
            add_action('woocommerce_single_product_summary', function (): void {
                $this->render_wrapper();
            }, 31);
        }
        if ($this->settings_status->is_smart_button_enabled_for_location('cart')) {
            add_action('woocommerce_proceed_to_checkout', function (): void {
                if (!is_cart()) {
                    return;
                }
                $this->render_wrapper();
            }, 20);
        }
        if ($this->settings_status->is_smart_button_enabled_for_location('checkout')) {
            add_action('woocommerce_review_order_after_payment', function (): void {
                $this->render_wrapper();
            });
        }
        if ($this->settings_status->is_smart_button_enabled_for_location('mini-cart')) {
            add_action('woocommerce_widget_shopping_cart_after_buttons', function (): void {
                echo '<p class="woocommerce-mini-cart__buttons buttons">';
                echo '<span id="' . esc_attr(self::MINI_CART_WRAPPER_ID) . '"></span>';
                echo '</p>';
            }, 30);
        }
    }
    /**
     * Outputs the main button wrapper element.
     *
     * @return void
     */
    private function render_wrapper(): void
    {
        echo '<div class="ppc-button-wrapper"><div id="' . esc_attr(self::WRAPPER_ID) . '"></div></div>';
    }
    /**
     * Whether the v6 SDK loads on the current page.
     *
     * Follows the v5 SmartButton gating: each WC page type requires its
     * location to be enabled in the button settings, and an enabled
     * mini-cart location enqueues on every page (the classic mini-cart
     * widget can appear anywhere). The bootstrap only loads the SDK once
     * a button wrapper exists in the DOM.
     *
     * Also used to scope the v5 suppression: v5 must only be disabled on
     * pages where v6 loads (both SDKs claim window.paypal), and keep
     * running everywhere else (block cart/checkout, pay-now). That
     * scoping is migration-phase only — see extensions.php; at release
     * the suppression becomes unconditional and only the merchant
     * location-settings gating in this method remains meaningful.
     *
     * @return bool
     */
    public function should_load_on_current_page(): bool
    {
        $page_location = $this->get_page_context();
        if ($page_location && $this->settings_status->is_smart_button_enabled_for_location($page_location)) {
            return \true;
        }
        // The mini-cart case only applies when the classic widget is in
        // use; block-theme mini-carts are out of this module's scope, and
        // loading (and suppressing v5) sitewide without a widget would
        // break the v5-rendered block express buttons for nothing.
        return $this->settings_status->is_smart_button_enabled_for_location('mini-cart') && is_active_widget(\false, \false, 'woocommerce_widget_cart');
    }
    /**
     * The configuration data for the SDK v6 bootstrap script.
     *
     * @return array
     */
    private function script_data(): array
    {
        $base_url = $this->environment->is_sandbox() ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
        $buyer_country = WC()->customer ? WC()->customer->get_billing_country() : '';
        if (!$buyer_country) {
            $buyer_country = wc_get_base_location()['country'] ?? '';
        }
        $page_context = $this->get_page_context();
        $shipping_enabled = $this->should_handle_shipping && 'checkout' !== $page_context;
        $store_api_base = rtrim(rest_url('wc/store/v1/cart'), '/');
        $button_styles = array();
        if ($page_context) {
            $button_styles[$page_context] = $this->style_mapper->styles_for_context($page_context);
        }
        if ($this->settings_status->is_smart_button_enabled_for_location('mini-cart')) {
            $button_styles['mini-cart'] = $this->style_mapper->styles_for_context('mini-cart');
        }
        return array('sdk_url' => $base_url . '/web-sdk/v6/core', 'page_context' => $page_context, 'currency' => get_woocommerce_currency(), 'amount' => $this->transaction_amount(), 'buyer_country' => $buyer_country, 'locale' => str_replace('_', '-', get_locale()), 'ajax' => array('client_token' => array('endpoint' => \WC_AJAX::get_endpoint(ClientTokenEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(ClientTokenEndpoint::nonce())), 'change_cart' => array('endpoint' => \WC_AJAX::get_endpoint(ChangeCartEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(ChangeCartEndpoint::nonce())), 'create_order' => array('endpoint' => \WC_AJAX::get_endpoint(CreateOrderEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(CreateOrderEndpoint::nonce())), 'approve_order' => array('endpoint' => \WC_AJAX::get_endpoint(ApproveOrderEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(ApproveOrderEndpoint::nonce())), 'update_shipping' => array('endpoint' => \WC_AJAX::get_endpoint(UpdateShippingEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(UpdateShippingEndpoint::nonce())), 'wc_store_api' => array('cart' => $store_api_base, 'select_shipping_rate' => $store_api_base . '/select-shipping-rate', 'update_customer' => $store_api_base . '/update-customer', 'nonce' => wp_create_nonce('wc_store_api'))), 'urls' => array('checkout' => wc_get_checkout_url()), 'labels' => array('generic_error' => __('Something went wrong. Please try again or choose another payment source.', 'woocommerce-paypal-payments')), 'shipping' => array('handle_in_paypal' => $shipping_enabled, 'need_shipping' => $this->need_shipping()), 'button_styles' => $button_styles, 'wrapper' => '#' . self::WRAPPER_ID, 'mini_cart_wrapper' => '#' . self::MINI_CART_WRAPPER_ID);
    }
    /**
     * Whether the current cart needs shipping.
     *
     * @return bool
     */
    private function need_shipping(): bool
    {
        $cart = WC()->cart;
        return $cart && $cart->needs_shipping();
    }
    /**
     * Returns the expected transaction amount for eligibility checks
     * (affects Pay Later thresholds): the cart total, or the product
     * price on product pages while the cart is empty.
     *
     * @return string The amount as a decimal string, or empty when unknown.
     */
    private function transaction_amount(): string
    {
        $cart = WC()->cart;
        if ($cart && !$cart->is_empty()) {
            return number_format((float) $cart->get_total('edit'), 2, '.', '');
        }
        if (is_product()) {
            $product = wc_get_product(get_the_ID());
            if ($product) {
                $price = (float) wc_get_price_including_tax($product);
                if ($price) {
                    return number_format($price, 2, '.', '');
                }
            }
        }
        return '';
    }
    /**
     * Returns the page context for the current WC page.
     *
     * Resolves through the shared Context helper (which handles
     * classic-shortcode block pages) and narrows to the contexts this
     * module supports; block cart/checkout and pay-now are out of scope.
     *
     * @return string
     */
    private function get_page_context(): string
    {
        $context = $this->context->context();
        if (in_array($context, array('product', 'cart', 'checkout'), \true)) {
            return $context;
        }
        return '';
    }
}
