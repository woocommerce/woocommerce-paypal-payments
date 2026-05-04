<?php
/**
 * Shared list of PayPal options for Blueprint export/import.
 *
 * @package WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

/**
 * Single source of truth for PayPal option names used in Blueprint export and import.
 */
class PayPalBlueprintOptions {

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
		// Legacy settings (maintained for backward compatibility during migration).
		'woocommerce-ppcp-settings',
		// Merchant state flags.
		'woocommerce-ppcp-is-new-merchant',
		// UI and migration state flags (prevent re-migration and control UI display).
		'woocommerce_ppcp-settings-should-use-old-ui',
		'woocommerce_ppcp-is_pay_later_settings_migrated',
		'woocommerce_ppcp-is_smart_button_settings_migrated',
		// Individual payment method settings (gateway titles/descriptions).
		'woocommerce_venmo_settings',
		'woocommerce_pay-later_settings',
	);
}
