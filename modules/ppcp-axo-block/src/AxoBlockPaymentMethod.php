<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AxoBlock;

use WC_Payment_Gateway;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Axo\Endpoint\AxoScriptAttributes;
use WooCommerce\PayPalCommerce\Axo\Endpoint\FrontendLogger;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
class AxoBlockPaymentMethod extends AbstractPaymentMethodType
{
    private AssetGetter $asset_getter;
    /**
     * Credit card gateway.
     *
     * @var WC_Payment_Gateway
     */
    private $gateway;
    protected SettingsProvider $settings_provider;
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
    private AssetGetter $wcgateway_module_asset_getter;
    /**
     * The supported country card type matrix.
     *
     * @var array
     */
    private $supported_country_card_type_matrix;
    /**
     * @param AssetGetter               $asset_getter
     * @param WC_Payment_Gateway        $gateway Credit card gateway.
     * @param SettingsProvider          $settings_provider The settings provider.
     * @param CardPaymentsConfiguration $dcc_configuration The DCC gateway settings.
     * @param Environment               $environment The environment object.
     * @param AssetGetter               $wcgateway_module_asset_getter
     * @param array                     $payment_method_selected_map Mapping of payment methods to the PayPal Insights 'payment_method_selected' types.
     * @param array                     $supported_country_card_type_matrix The supported country card type matrix for Axo.
     */
    public function __construct(AssetGetter $asset_getter, WC_Payment_Gateway $gateway, SettingsProvider $settings_provider, CardPaymentsConfiguration $dcc_configuration, Environment $environment, AssetGetter $wcgateway_module_asset_getter, array $payment_method_selected_map, array $supported_country_card_type_matrix)
    {
        $this->name = AxoGateway::ID;
        $this->asset_getter = $asset_getter;
        $this->gateway = $gateway;
        $this->settings_provider = $settings_provider;
        $this->dcc_configuration = $dcc_configuration;
        $this->environment = $environment;
        $this->wcgateway_module_asset_getter = $wcgateway_module_asset_getter;
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
        $script_asset_path = $this->asset_getter->get_asset_php_path('index.js');
        $script_asset = file_exists($script_asset_path) ? require $script_asset_path : array('dependencies' => array(), 'version' => '1.0.0');
        $script_url = $this->asset_getter->get_asset_url('index.js');
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
        return array(
            'environment' => array('is_sandbox' => $this->environment->current_environment() === 'sandbox'),
            'widgets' => array('email' => 'render'),
            'insights' => array(
                'enabled' => defined('WP_DEBUG') && WP_DEBUG,
                // @phpstan-ignore booleanAnd.rightAlwaysFalse
                'client_id' => $this->settings_provider->merchant_data()->client_id ?: null,
                'session_id' => WC()->session && method_exists(WC()->session, 'get_customer_unique_id') ? substr(md5(WC()->session->get_customer_unique_id()), 0, 16) : '',
                'amount' => array('currency_code' => get_woocommerce_currency(), 'value' => WC()->cart && method_exists(WC()->cart, 'get_total') ? WC()->cart->get_total('numeric') : null),
                'payment_method_selected_map' => $this->payment_method_selected_map,
            ),
            'allowed_cards' => $this->supported_country_card_type_matrix,
            'disable_cards' => $this->settings_provider->disabled_cards(),
            'enabled_shipping_locations' => apply_filters('woocommerce_paypal_payments_axo_shipping_wc_enabled_locations', array()),
            'style_options' => array('root' => $this->settings_provider->fastlane_root_styles(), 'input' => $this->settings_provider->fastlane_input_styles()),
            'name_on_card' => $this->dcc_configuration->show_name_on_card(),
            'show_watermark' => $this->settings_provider->show_fastlane_watermark(),
            'woocommerce' => array('states' => array('US' => WC()->countries->get_states('US'), 'CA' => WC()->countries->get_states('CA'))),
            'icons_directory' => $this->wcgateway_module_asset_getter->get_static_asset_url('images/axo/'),
            'ajax' => array('frontend_logger' => array('endpoint' => \WC_AJAX::get_endpoint(FrontendLogger::ENDPOINT), 'nonce' => wp_create_nonce(FrontendLogger::nonce())), 'axo_script_attributes' => array('endpoint' => \WC_AJAX::get_endpoint(AxoScriptAttributes::ENDPOINT), 'nonce' => wp_create_nonce(AxoScriptAttributes::nonce()))),
            'logging_enabled' => $this->settings_provider->enable_logging(),
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
            // @phpstan-ignore booleanAnd.rightAlwaysFalse
            'card_icons' => $this->settings_provider->card_icons(),
            'merchant_country' => WC()->countries->get_base_country(),
        );
    }
}
