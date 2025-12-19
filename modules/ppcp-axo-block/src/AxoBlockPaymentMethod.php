<?php

/**
 * Axo block payment method.
 *
 * @package WooCommerce\PayPalCommerce\AxoBlock
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AxoBlock;

use WC_Payment_Gateway;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\Axo\Endpoint\AxoScriptAttributes;
use WooCommerce\PayPalCommerce\Axo\Endpoint\FrontendLogger;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
/**
 * Class AxoBlockPaymentMethod
 */
class AxoBlockPaymentMethod extends AbstractPaymentMethodType
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
     * Credit card gateway.
     *
     * @var WC_Payment_Gateway
     */
    private $gateway;
    /**
     * The smart button script loading handler.
     *
     * @var SmartButtonInterface|callable
     */
    private $smart_button;
    /**
     * The settings.
     *
     * @var Settings
     */
    protected $settings;
    /**
     * The DCC gateway settings.
     *
     * @var CardPaymentsConfiguration
     */
    protected CardPaymentsConfiguration $dcc_configuration;
    /**
     * The environment object.
     *
     * @var Environment
     */
    private $environment;
    /**
     * Mapping of payment methods to the PayPal Insights 'payment_method_selected' types.
     *
     * @var array
     */
    private array $payment_method_selected_map;
    /**
     * The WcGateway module URL.
     *
     * @var string
     */
    private $wcgateway_module_url;
    /**
     * The supported country card type matrix.
     *
     * @var array
     */
    private $supported_country_card_type_matrix;
    /**
     * AdvancedCardPaymentMethod constructor.
     *
     * @param string                        $module_url The URL of this module.
     * @param string                        $version The assets version.
     * @param WC_Payment_Gateway            $gateway Credit card gateway.
     * @param SmartButtonInterface|callable $smart_button The smart button script loading handler.
     * @param Settings                      $settings The settings.
     * @param CardPaymentsConfiguration     $dcc_configuration The DCC gateway settings.
     * @param Environment                   $environment The environment object.
     * @param string                        $wcgateway_module_url The WcGateway module URL.
     * @param array                         $payment_method_selected_map Mapping of payment methods to the PayPal Insights 'payment_method_selected' types.
     * @param array                         $supported_country_card_type_matrix The supported country card type matrix for Axo.
     */
    public function __construct(string $module_url, string $version, WC_Payment_Gateway $gateway, $smart_button, Settings $settings, CardPaymentsConfiguration $dcc_configuration, Environment $environment, string $wcgateway_module_url, array $payment_method_selected_map, array $supported_country_card_type_matrix)
    {
        $this->name = AxoGateway::ID;
        $this->module_url = $module_url;
        $this->version = $version;
        $this->gateway = $gateway;
        $this->smart_button = $smart_button;
        $this->settings = $settings;
        $this->dcc_configuration = $dcc_configuration;
        $this->environment = $environment;
        $this->wcgateway_module_url = $wcgateway_module_url;
        $this->payment_method_selected_map = $payment_method_selected_map;
        $this->supported_country_card_type_matrix = $supported_country_card_type_matrix;
    }
    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
    }
    /**
     * {@inheritDoc}
     */
    public function is_active(): bool
    {
        return $this->gateway->is_available();
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_script_handles(): array
    {
        $script_path = 'assets/js/index.js';
        $script_asset_path = trailingslashit($this->module_url) . 'assets/js/index.asset.php';
        $script_asset = file_exists($script_asset_path) ? require $script_asset_path : array('dependencies' => array(), 'version' => '1.0.0');
        $script_url = trailingslashit($this->module_url) . $script_path;
        wp_register_script('ppcp-axo-block', $script_url, $script_asset['dependencies'], $script_asset['version'], \true);
        wp_localize_script('ppcp-axo-block', 'wc_ppcp_axo', $this->script_data());
        return array('ppcp-axo-block');
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_data()
    {
        return array('id' => $this->name, 'title' => $this->gateway->title, 'description' => $this->gateway->description, 'supports' => array_filter($this->gateway->supports, array($this->gateway, 'supports')));
    }
    /**
     * The configuration for AXO.
     *
     * @return array
     */
    private function script_data(): array
    {
        if (is_admin()) {
            return array();
        }
        return array('environment' => array('is_sandbox' => $this->environment->current_environment() === 'sandbox'), 'widgets' => array('email' => 'render'), 'insights' => array('enabled' => defined('WP_DEBUG') && WP_DEBUG, 'client_id' => $this->settings->has('client_id') ? $this->settings->get('client_id') : null, 'session_id' => WC()->session && method_exists(WC()->session, 'get_customer_unique_id') ? substr(md5(WC()->session->get_customer_unique_id()), 0, 16) : '', 'amount' => array('currency_code' => get_woocommerce_currency(), 'value' => WC()->cart && method_exists(WC()->cart, 'get_total') ? WC()->cart->get_total('numeric') : null), 'payment_method_selected_map' => $this->payment_method_selected_map), 'allowed_cards' => $this->supported_country_card_type_matrix, 'disable_cards' => $this->settings->has('disable_cards') ? (array) $this->settings->get('disable_cards') : array(), 'enabled_shipping_locations' => apply_filters('woocommerce_paypal_payments_axo_shipping_wc_enabled_locations', array()), 'style_options' => array('root' => array('backgroundColor' => $this->settings->has('axo_style_root_bg_color') ? $this->settings->get('axo_style_root_bg_color') : '', 'errorColor' => $this->settings->has('axo_style_root_error_color') ? $this->settings->get('axo_style_root_error_color') : '', 'fontFamily' => $this->settings->has('axo_style_root_font_family') ? $this->settings->get('axo_style_root_font_family') : '', 'textColorBase' => $this->settings->has('axo_style_root_text_color_base') ? $this->settings->get('axo_style_root_text_color_base') : '', 'fontSizeBase' => $this->settings->has('axo_style_root_font_size_base') ? $this->settings->get('axo_style_root_font_size_base') : '', 'padding' => $this->settings->has('axo_style_root_padding') ? $this->settings->get('axo_style_root_padding') : '', 'primaryColor' => $this->settings->has('axo_style_root_primary_color') ? $this->settings->get('axo_style_root_primary_color') : ''), 'input' => array('backgroundColor' => $this->settings->has('axo_style_input_bg_color') ? $this->settings->get('axo_style_input_bg_color') : '', 'borderRadius' => $this->settings->has('axo_style_input_border_radius') ? $this->settings->get('axo_style_input_border_radius') : '', 'borderColor' => $this->settings->has('axo_style_input_border_color') ? $this->settings->get('axo_style_input_border_color') : '', 'borderWidth' => $this->settings->has('axo_style_input_border_width') ? $this->settings->get('axo_style_input_border_width') : '', 'textColorBase' => $this->settings->has('axo_style_input_text_color_base') ? $this->settings->get('axo_style_input_text_color_base') : '', 'focusBorderColor' => $this->settings->has('axo_style_input_focus_border_color') ? $this->settings->get('axo_style_input_focus_border_color') : '')), 'name_on_card' => $this->dcc_configuration->show_name_on_card(), 'woocommerce' => array('states' => array('US' => WC()->countries->get_states('US'), 'CA' => WC()->countries->get_states('CA'))), 'icons_directory' => esc_url($this->wcgateway_module_url) . 'assets/images/axo/', 'module_url' => untrailingslashit($this->module_url), 'ajax' => array('frontend_logger' => array('endpoint' => \WC_AJAX::get_endpoint(FrontendLogger::ENDPOINT), 'nonce' => wp_create_nonce(FrontendLogger::nonce())), 'axo_script_attributes' => array('endpoint' => \WC_AJAX::get_endpoint(AxoScriptAttributes::ENDPOINT), 'nonce' => wp_create_nonce(AxoScriptAttributes::nonce()))), 'logging_enabled' => $this->settings->has('logging_enabled') ? $this->settings->get('logging_enabled') : '', 'wp_debug' => defined('WP_DEBUG') && WP_DEBUG, 'card_icons' => $this->settings->has('card_icons') ? (array) $this->settings->get('card_icons') : array(), 'merchant_country' => WC()->countries->get_base_country());
    }
}
