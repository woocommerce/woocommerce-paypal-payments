<?php
/**
 * PayPal Settings Blueprint Exporter.
 *
 * @package WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

use Automattic\WooCommerce\Blueprint\Exporters\StepExporter;
use Automattic\WooCommerce\Blueprint\Exporters\HasAlias;
use Automattic\WooCommerce\Blueprint\Steps\SetSiteOptions;
use Automattic\WooCommerce\Blueprint\Steps\Step;

/**
 * PayPal Settings Exporter for WooCommerce Blueprint.
 */
class PayPalSettingsExporter implements StepExporter, HasAlias {

	/**
	 * Sentinel value to detect if option doesn't exist.
	 */
	private const OPTION_NOT_FOUND = '__PAYPAL_OPTION_NOT_FOUND__';

	/**
	 * PayPal-related options to export (excluding transients and core gateway settings).
	 *
	 * @var array<string>
	 */
	private const PAYPAL_OPTIONS = array(
		// Core PPCP data settings.
		'woocommerce-ppcp-data-common',
		'woocommerce-ppcp-data-onboarding',
		'woocommerce-ppcp-data-payment',
		'woocommerce-ppcp-data-settings',
		'woocommerce-ppcp-data-styling',
		// Main PPCP configuration.
		'woocommerce-ppcp-settings',
		'woocommerce-ppcp-version',
		'woocommerce-ppcp-is-new-merchant',
		// Admin and migration flags.
		'woocommerce_ppcp-admin-notices',
		'woocommerce_ppcp-is_pay_later_settings_migrated',
		'woocommerce_ppcp-is_smart_button_settings_migrated',
		'woocommerce_ppcp-settings-should-use-old-ui',
		// Individual payment method settings (non-gateway).
		'woocommerce_venmo_settings',
		'woocommerce_pay-later_settings',
	);

	/**
	 * Export PayPal settings.
	 *
	 * @return Step
	 */
	public function export(): Step {
		$paypal_options = array();

		foreach ( self::PAYPAL_OPTIONS as $option_name ) {
			$value = get_option( $option_name, self::OPTION_NOT_FOUND );
			if ( self::OPTION_NOT_FOUND !== $value ) {
				$paypal_options[ $option_name ] = $value;
			}
		}

		return new SetSiteOptions( $paypal_options );
	}

	/**
	 * Get step name.
	 *
	 * @return string
	 */
	public function get_step_name(): string {
		return SetSiteOptions::get_step_name();
	}

	/**
	 * Get alias for this exporter.
	 *
	 * @return string
	 */
	public function get_alias(): string {
		return 'paypalSettings';
	}

	/**
	 * Return label used in the frontend.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'PayPal Settings', 'woocommerce-paypal-payments' );
	}

	/**
	 * Return the description used in the frontend.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Exports PayPal Payments settings and configuration options.', 'woocommerce-paypal-payments' );
	}

	/**
	 * Check if user has capability to export PayPal settings.
	 *
	 * @return bool
	 */
	public function check_step_capabilities(): bool {
		return current_user_can( 'manage_woocommerce' ) && current_user_can( 'manage_options' );
	}
}
