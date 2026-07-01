<?php

/**
 * Shared list of PayPal options for Blueprint export/import.
 *
 * @package WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

/**
 * Single source of truth for PayPal option names used in Blueprint export and import.
 */
class PayPalBlueprintOptions
{
    /**
     * PayPal-related options (excluding transients and plugin metadata).
     *
     * @var array<string>
     */
    public const OPTION_NAMES = array(
        // Core PPCP data settings (new settings).
        'woocommerce-ppcp-data-common',
        'woocommerce-ppcp-data-onboarding',
        'woocommerce-ppcp-data-payment',
        'woocommerce-ppcp-data-settings',
        'woocommerce-ppcp-data-styling',
        'woocommerce-ppcp-data-fastlane',
        'woocommerce-ppcp-data-paylater-messaging',
        // Merchant state flags.
        'woocommerce-ppcp-is-new-merchant',
        // Individual payment method settings (gateway titles/descriptions).
        'woocommerce_venmo_settings',
        'woocommerce_pay-later_settings',
    );
}
