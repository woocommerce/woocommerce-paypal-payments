<?php

/**
 * Advanced card payment method.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
/**
 * Class AdvancedCardPaymentMethod
 */
class AdvancedCardPaymentMethod extends AbstractPaymentMethodType
{
    private AssetGetter $asset_getter;
    /**
     * The assets version.
     *
     * @var string
     */
    private $version;
    /**
     * Credit card gateway.
     *
     * @var CreditCardGateway
     */
    private $gateway;
    /**
     * The smart button script loading handler.
     *
     * @var SmartButtonInterface|callable
     */
    private $smart_button;
    /**
     * The settings provider.
     *
     * @var SettingsProvider
     */
    protected SettingsProvider $plugin_settings;
    protected CardPaymentsConfiguration $card_payments_configuration;
    protected bool $save_payment_methods_eligible;
    /**
     * @param AssetGetter                   $asset_getter
     * @param string                        $version The assets version.
     * @param CreditCardGateway             $gateway
     * @param SmartButtonInterface|callable $smart_button The smart button script loading handler.
     * @param SettingsProvider              $settings_provider The settings provider.
     * @param CardPaymentsConfiguration     $card_payments_configuration
     * @param bool                          $save_payment_methods_eligible Whether save payment methods is eligible for the current country.
     */
    public function __construct(AssetGetter $asset_getter, string $version, CreditCardGateway $gateway, $smart_button, SettingsProvider $settings_provider, CardPaymentsConfiguration $card_payments_configuration, bool $save_payment_methods_eligible)
    {
        $this->name = CreditCardGateway::ID;
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->gateway = $gateway;
        $this->smart_button = $smart_button;
        $this->plugin_settings = $settings_provider;
        $this->card_payments_configuration = $card_payments_configuration;
        $this->save_payment_methods_eligible = $save_payment_methods_eligible;
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
    public function is_active()
    {
        return \true;
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_script_handles()
    {
        wp_register_script('ppcp-advanced-card-checkout-block', $this->asset_getter->get_asset_url('advanced-card-checkout-block.js'), array('wp-i18n'), $this->version, \true);
        wp_set_script_translations('ppcp-advanced-card-checkout-block', 'woocommerce-paypal-payments');
        return array('ppcp-advanced-card-checkout-block');
    }
    /**
     * {@inheritDoc}
     */
    public function get_payment_method_data()
    {
        $script_data = $this->smart_button_instance()->script_data();
        $script_data = array_merge($script_data, array('is_user_logged_in' => is_user_logged_in()));
        return array('id' => $this->name, 'title' => $this->gateway->title, 'description' => $this->gateway->description, 'scriptData' => $script_data, 'supports' => $this->gateway->supports, 'save_card_text' => esc_html__('Save your card', 'woocommerce-paypal-payments'), 'is_vaulting_enabled' => $this->save_payment_methods_eligible && $this->plugin_settings->save_card_details(), 'card_icons' => $this->plugin_settings->card_icons(), 'name_on_card' => $this->card_payments_configuration->show_name_on_card());
    }
    /**
     * The smart button.
     *
     * @return SmartButtonInterface
     */
    private function smart_button_instance(): SmartButtonInterface
    {
        if ($this->smart_button instanceof SmartButtonInterface) {
            return $this->smart_button;
        }
        if (is_callable($this->smart_button)) {
            $this->smart_button = ($this->smart_button)();
        }
        return $this->smart_button;
    }
}
