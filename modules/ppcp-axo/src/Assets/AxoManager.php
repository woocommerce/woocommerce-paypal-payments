<?php

/**
 * The AXO AxoManager
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Axo\Assets;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter;
use WooCommerce\PayPalCommerce\Axo\Endpoint\AxoScriptAttributes;
use WooCommerce\PayPalCommerce\Axo\Endpoint\FrontendLogger;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
/**
 * Class AxoManager.
 *
 * @param string $module_url The URL to the module.
 */
class AxoManager
{
    /**
     * The URL to the module.
     *
     * @var string
     */
    private string $module_url;
    /**
     * The assets version.
     *
     * @var string
     */
    private string $version;
    /**
     * The settings.
     *
     * @var Settings
     */
    private Settings $settings;
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
    /**
     * The Settings status helper.
     *
     * @var SettingsStatus
     */
    private $settings_status;
    /**
     * The getter of the 3-letter currency code of the shop.
     *
     * @var CurrencyGetter
     */
    private CurrencyGetter $currency;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * Session handler.
     *
     * @var SessionHandler
     */
    private SessionHandler $session_handler;
    /**
     * The WcGateway module URL.
     *
     * @var string
     */
    private string $wcgateway_module_url;
    /**
     * The supported country card type matrix.
     *
     * @var array
     */
    private array $supported_country_card_type_matrix;
    /**
     * AxoManager constructor.
     *
     * @param string          $module_url The URL to the module.
     * @param string          $version The assets version.
     * @param SessionHandler  $session_handler The Session handler.
     * @param Settings        $settings The Settings.
     * @param Environment     $environment The environment object.
     * @param array           $insights_data Data needed for the PayPal Insights.
     * @param SettingsStatus  $settings_status The Settings status helper.
     * @param CurrencyGetter  $currency The getter of the 3-letter currency code of the shop.
     * @param LoggerInterface $logger The logger.
     * @param string          $wcgateway_module_url The WcGateway module URL.
     * @param array           $supported_country_card_type_matrix The supported country card type matrix for Axo.
     */
    public function __construct(string $module_url, string $version, SessionHandler $session_handler, Settings $settings, Environment $environment, array $insights_data, SettingsStatus $settings_status, CurrencyGetter $currency, LoggerInterface $logger, string $wcgateway_module_url, array $supported_country_card_type_matrix)
    {
        $this->module_url = $module_url;
        $this->version = $version;
        $this->session_handler = $session_handler;
        $this->settings = $settings;
        $this->environment = $environment;
        $this->insights_data = $insights_data;
        $this->settings_status = $settings_status;
        $this->currency = $currency;
        $this->logger = $logger;
        $this->wcgateway_module_url = $wcgateway_module_url;
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
        wp_register_style('wc-ppcp-axo', untrailingslashit($this->module_url) . '/assets/css/styles.css', array(), $this->version);
        wp_enqueue_style('wc-ppcp-axo');
        // Register scripts.
        wp_register_script('wc-ppcp-axo', untrailingslashit($this->module_url) . '/assets/js/boot.js', array(), $this->version, \true);
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
            'disable_cards' => $this->settings->has('disable_cards') ? (array) $this->settings->get('disable_cards') : array(),
            'enabled_shipping_locations' => apply_filters('woocommerce_paypal_payments_axo_shipping_wc_enabled_locations', array()),
            'style_options' => array('root' => array('backgroundColor' => $this->settings->has('axo_style_root_bg_color') ? $this->settings->get('axo_style_root_bg_color') : '', 'errorColor' => $this->settings->has('axo_style_root_error_color') ? $this->settings->get('axo_style_root_error_color') : '', 'fontFamily' => $this->settings->has('axo_style_root_font_family') ? $this->settings->get('axo_style_root_font_family') : '', 'textColorBase' => $this->settings->has('axo_style_root_text_color_base') ? $this->settings->get('axo_style_root_text_color_base') : '', 'fontSizeBase' => $this->settings->has('axo_style_root_font_size_base') ? $this->settings->get('axo_style_root_font_size_base') : '', 'padding' => $this->settings->has('axo_style_root_padding') ? $this->settings->get('axo_style_root_padding') : '', 'primaryColor' => $this->settings->has('axo_style_root_primary_color') ? $this->settings->get('axo_style_root_primary_color') : ''), 'input' => array('backgroundColor' => $this->settings->has('axo_style_input_bg_color') ? $this->settings->get('axo_style_input_bg_color') : '', 'borderRadius' => $this->settings->has('axo_style_input_border_radius') ? $this->settings->get('axo_style_input_border_radius') : '', 'borderColor' => $this->settings->has('axo_style_input_border_color') ? $this->settings->get('axo_style_input_border_color') : '', 'borderWidth' => $this->settings->has('axo_style_input_border_width') ? $this->settings->get('axo_style_input_border_width') : '', 'textColorBase' => $this->settings->has('axo_style_input_text_color_base') ? $this->settings->get('axo_style_input_text_color_base') : '', 'focusBorderColor' => $this->settings->has('axo_style_input_focus_border_color') ? $this->settings->get('axo_style_input_focus_border_color') : '')),
            'name_on_card' => $this->settings->has('axo_name_on_card') ? $this->settings->get('axo_name_on_card') : '',
            'woocommerce' => array('states' => array('US' => WC()->countries->get_states('US'), 'CA' => WC()->countries->get_states('CA'))),
            'icons_directory' => esc_url($this->wcgateway_module_url) . 'assets/images/axo/',
            'module_url' => untrailingslashit($this->module_url),
            'ajax' => array('frontend_logger' => array('endpoint' => \WC_AJAX::get_endpoint(FrontendLogger::ENDPOINT), 'nonce' => wp_create_nonce(FrontendLogger::nonce())), 'axo_script_attributes' => array('endpoint' => \WC_AJAX::get_endpoint(AxoScriptAttributes::ENDPOINT), 'nonce' => wp_create_nonce(AxoScriptAttributes::nonce()))),
            'logging_enabled' => $this->settings->has('logging_enabled') ? $this->settings->get('logging_enabled') : '',
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
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
