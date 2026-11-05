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
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
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
    protected SettingsProvider $plugin_settings;
    protected CardPaymentsConfiguration $card_payments_configuration;
    protected bool $save_payment_methods_eligible;
    private PaymentSettings $payment_settings;
    /**
     * @var array<int, array{type: string, title: string, url: string}>
     */
    private array $credit_card_icons;
    /**
     * @param AssetGetter                   $asset_getter
     * @param string                        $version
     * @param CreditCardGateway             $gateway
     * @param SmartButtonInterface|callable $smart_button The smart button script loading handler.
     * @param SettingsProvider              $settings_provider
     * @param CardPaymentsConfiguration     $card_payments_configuration
     * @param bool                          $save_payment_methods_eligible
     * @param PaymentSettings               $payment_settings
     * @param array                         $credit_card_icons Pre-built card icon data.
     */
    public function __construct(AssetGetter $asset_getter, string $version, CreditCardGateway $gateway, $smart_button, SettingsProvider $settings_provider, CardPaymentsConfiguration $card_payments_configuration, bool $save_payment_methods_eligible, PaymentSettings $payment_settings, array $credit_card_icons)
    {
        $this->name = CreditCardGateway::ID;
        $this->asset_getter = $asset_getter;
        $this->version = $version;
        $this->gateway = $gateway;
        $this->smart_button = $smart_button;
        $this->plugin_settings = $settings_provider;
        $this->card_payments_configuration = $card_payments_configuration;
        $this->save_payment_methods_eligible = $save_payment_methods_eligible;
        $this->payment_settings = $payment_settings;
        $this->credit_card_icons = $credit_card_icons;
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
        return array('id' => $this->name, 'title' => $this->gateway->title, 'description' => $this->gateway->description, 'scriptData' => $script_data, 'supports' => $this->gateway->supports, 'save_card_text' => esc_html__('Save your card', 'woocommerce-paypal-payments'), 'is_vaulting_enabled' => $this->save_payment_methods_eligible && $this->plugin_settings->save_card_details(), 'card_icons' => $this->build_card_icons(), 'name_on_card' => $this->card_payments_configuration->show_name_on_card());
    }
    /**
     * Returns card icons in the {id, alt, src} format expected by PaymentMethodIcons,
     * or an empty array when the merchant has disabled logo display.
     *
     * @return array<int, array{id: string, alt: string, src: string}>
     */
    private function build_card_icons(): array
    {
        if (!$this->payment_settings->get_show_card_logos()) {
            return array();
        }
        return array_map(static function (array $icon): array {
            return array('id' => $icon['type'], 'alt' => $icon['title'], 'src' => $icon['url']);
        }, $this->credit_card_icons);
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
