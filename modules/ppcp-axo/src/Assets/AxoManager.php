<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Axo\Assets;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Axo\Endpoint\AxoScriptAttributes;
use WooCommerce\PayPalCommerce\Axo\Endpoint\FrontendLogger;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
class AxoManager
{
    private AssetGetter $asset_getter;
    /**
     * The assets version.
     *
     * @var string
     */
    private string $version;
    /**
     * The settings provider.
     *
     * @var SettingsProvider
     */
    private SettingsProvider $settings_provider;
    /**
     * The environment object.
     *
     * @var Environment
     */
    private Environment $environment;
    /**
     * Data needed for the PayPal Insights.
     *
     * @var array
     */
    private array $insights_data;
    private AssetGetter $wcgateway_module_asset_getter;
    /**
     * The supported country card type matrix.
     *
     * @var array
     */
    private array $supported_country_card_type_matrix;
    /**
     * @param AssetGetter      $asset_getter
     * @param string           $version The assets version.
     * @param SettingsProvider $settings_provider The Settings provider.
     * @param Environment      $environment The environment object.
     * @param array            $insights_data Data needed for the PayPal Insights.
     * @param AssetGetter      $wcgateway_module_asset_getter
     * @param array            $supported_country_card_type_matrix The supported country card type matrix for Axo.
     */
    public function __construct(AssetGetter $asset_getter, string $version, SettingsProvider $settings_provider, Environment $environment, array $insights_data, AssetGetter $wcgateway_module_asset_getter, array $supported_country_card_type_matrix)
    {
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->settings_provider = $settings_provider;
        $this->environment = $environment;
        $this->insights_data = $insights_data;
        $this->wcgateway_module_asset_getter = $wcgateway_module_asset_getter;
        $this->supported_country_card_type_matrix = $supported_country_card_type_matrix;
    }
    /**
     * Enqueues scripts/styles.
     *
     * @return void
     */
    public function enqueue()
    {
        // Register styles.
        wp_register_style('wc-ppcp-axo', $this->asset_getter->get_asset_url('styles.css'), array(), $this->version);
        wp_enqueue_style('wc-ppcp-axo');
        // Register scripts.
        wp_register_script('wc-ppcp-axo', $this->asset_getter->get_asset_url('boot.js'), array(), $this->version, \true);
        wp_enqueue_script('wc-ppcp-axo');
        wp_localize_script('wc-ppcp-axo', 'wc_ppcp_axo', $this->script_data());
    }
    /**
     * The configuration for AXO.
     *
     * @return array
     */
    private function script_data(): array
    {
        return array(
            'environment' => array('is_sandbox' => $this->environment->current_environment() === 'sandbox'),
            'widgets' => array('email' => 'render'),
            // The amount is not available when setting the insights data, so we need to merge it here.
            'insights' => (function (array $data): array {
                $data['amount']['value'] = WC()->cart->get_total('numeric');
                return $data;
            })($this->insights_data),
            'allowed_cards' => $this->supported_country_card_type_matrix,
            'disable_cards' => $this->settings_provider->disabled_cards(),
            'enabled_shipping_locations' => apply_filters('woocommerce_paypal_payments_axo_shipping_wc_enabled_locations', array()),
            'style_options' => array('root' => $this->settings_provider->fastlane_root_styles(), 'input' => $this->settings_provider->fastlane_input_styles()),
            'name_on_card' => $this->settings_provider->fastlane_name_on_card(),
            'show_watermark' => $this->settings_provider->show_fastlane_watermark(),
            'woocommerce' => array('states' => array('US' => WC()->countries->get_states('US'), 'CA' => WC()->countries->get_states('CA'))),
            'icons_directory' => $this->wcgateway_module_asset_getter->get_static_asset_url('images/axo/'),
            'ajax' => array('frontend_logger' => array('endpoint' => \WC_AJAX::get_endpoint(FrontendLogger::ENDPOINT), 'nonce' => wp_create_nonce(FrontendLogger::nonce())), 'axo_script_attributes' => array('endpoint' => \WC_AJAX::get_endpoint(AxoScriptAttributes::ENDPOINT), 'nonce' => wp_create_nonce(AxoScriptAttributes::nonce()))),
            'logging_enabled' => $this->settings_provider->enable_logging(),
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
            // @phpstan-ignore booleanAnd.rightAlwaysFalse
            'billing_email_button_text' => __('Continue', 'woocommerce-paypal-payments'),
            'merchant_country' => WC()->countries->get_base_country(),
        );
    }
    /**
     * Returns the action name that PayPal AXO button will use for rendering on the checkout page.
     *
     * @return string
     */
    public function checkout_button_renderer_hook(): string
    {
        /**
         * The filter returning the action name that PayPal AXO button will use for rendering on the checkout page.
         */
        return (string) apply_filters('woocommerce_paypal_payments_checkout_axo_renderer_hook', 'woocommerce_review_order_after_submit');
    }
    /**
     * Renders the HTML for the AXO submit button.
     */
    public function render_checkout_button(): void
    {
        /**
         * The WC filter returning the WC order button text.
         * phpcs:disable WordPress.WP.I18n.TextDomainMismatch
         */
        $label = apply_filters('woocommerce_order_button_text', __('Place order', 'woocommerce'));
        printf('<div id="ppcp-axo-submit-button-container" style="display: none;">
				<button id="place_order" type="button" class="button alt ppcp-axo-order-button wp-element-button">%1$s</button>
			</div>', esc_html($label));
    }
}
