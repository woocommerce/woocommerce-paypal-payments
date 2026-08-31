<?php

/**
 * Maps admin Apple Pay settings to the v6 frontend configuration.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
class ApplePayConfig extends \WooCommerce\PayPalCommerce\SdkV6\Helper\MethodRenderGate
{
    /**
     * The <apple-pay-button> custom property expects a CSS length, not an integer.
     */
    private const RADIUS_MAP = array('pill' => '24px', 'rect' => '4px');
    private const DEFAULT_RADIUS = '24px';
    /**
     * The Apple Pay button styling for a page context (product, cart, checkout,
     * mini-cart). Only meaningful where should_render() is true.
     *
     * @return array{color: string, type: string, language: string, borderRadius: string}
     */
    public function styles(string $context): array
    {
        $styling = $this->method_styles($context);
        $type = $this->is_express_row($context) ? 'plain' : PropertiesDictionary::map_type($styling->label);
        // SettingsProvider maps these through filters the Apple Pay module
        // registers, which are absent when that module is not loaded. Mapping again
        // is idempotent and keeps the values valid either way.
        return array('color' => PropertiesDictionary::map_color($styling->color), 'type' => $type, 'language' => PropertiesDictionary::map_language($this->settings_provider->applepay_button_language()), 'borderRadius' => self::RADIUS_MAP[$styling->shape] ?? self::DEFAULT_RADIUS);
    }
    /**
     * The name shown on the payment sheet and sent with merchant validation.
     */
    public function display_name(): string
    {
        return (string) get_bloginfo('name');
    }
    protected function too_early_notice(): string
    {
        return esc_html__('Apple Pay availability cannot be determined before the wp_loaded action has run.', 'woocommerce-paypal-payments');
    }
    protected function method_enabled(): bool
    {
        return $this->settings_provider->applepay_enabled();
    }
    protected function method_styles(string $context): LocationStylingDTO
    {
        return $this->settings_provider->applepay_styles($context);
    }
    protected function gateway_id(): string
    {
        return ApplePayGateway::ID;
    }
}
