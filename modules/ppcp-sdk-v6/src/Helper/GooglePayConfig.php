<?php

/**
 * Maps admin Google Pay settings to the v6 frontend configuration.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Googlepay\Helper\PropertiesDictionary;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
class GooglePayConfig extends \WooCommerce\PayPalCommerce\SdkV6\Helper\WalletConfig
{
    /**
     * Google's buttonRadius expects an integer, not a CSS length.
     */
    private const RADIUS_MAP = array('pill' => 24, 'rect' => 4);
    private const DEFAULT_RADIUS = 24;
    /**
     * The Google Pay button styling for a page context (product, cart,
     * checkout, mini-cart). Only meaningful where should_render() is true.
     *
     * @return array{color: string, type: string, language: string, borderRadius: int}
     */
    public function styles(string $context): array
    {
        $styling = $this->wallet_styles($context);
        // SettingsProvider runs these through the Google Pay module's mapping
        // filters, but those only exist while that module is loaded. Mapping
        // here as well is idempotent and keeps the values valid either way.
        $type = PropertiesDictionary::map_type($styling->label);
        // The mini cart is too narrow for "Buy with G Pay"; v5 makes the same
        // substitution.
        if ('mini-cart' === $context && 'buy' === $type) {
            $type = 'pay';
        }
        return array('color' => PropertiesDictionary::map_color($styling->color), 'type' => $type, 'language' => PropertiesDictionary::map_language($this->settings_provider->googlepay_button_language()), 'borderRadius' => self::RADIUS_MAP[$styling->shape] ?? self::DEFAULT_RADIUS);
    }
    protected function too_early_notice(): string
    {
        return esc_html__('Google Pay availability cannot be determined before the wp_loaded action has run.', 'woocommerce-paypal-payments');
    }
    protected function wallet_enabled(): bool
    {
        return $this->settings_provider->googlepay_enabled();
    }
    protected function wallet_styles(string $context): LocationStylingDTO
    {
        return $this->settings_provider->googlepay_styles($context);
    }
    protected function gateway_id(): string
    {
        return GooglePayGateway::ID;
    }
}
