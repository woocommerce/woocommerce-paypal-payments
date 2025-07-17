<?php
/**
 * PayPal Settings Blueprint Exporter
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
 * PayPal Settings Exporter for WooCommerce Blueprint
 */
class PayPalSettingsExporter implements StepExporter, HasAlias {

	/**
	 * PayPal related options to export (excluding transients and core gateway settings)
	 *
	 * @var array<string>
	 */
	private const PAYPAL_OPTIONS = [
		'woocommerce_ppcp-admin-notices',
		'woocommerce_ppcp-is_pay_later_settings_migrated',
		'woocommerce_ppcp-is_smart_button_settings_migrated',
		'woocommerce_ppcp-settings-should-use-old-ui',
		'woocommerce_ppcp-oxxo-gateway_settings',
		'woocommerce_ppcp-pay-upon-invoice-gateway_settings',
		'woocommerce-ppcp-data-common',
		'woocommerce-ppcp-data-onboarding',
		'woocommerce-ppcp-data-payment',
		'woocommerce-ppcp-data-settings',
		'woocommerce-ppcp-data-styling',
		'woocommerce-ppcp-is-new-merchant',
		'woocommerce-ppcp-settings',
		'woocommerce-ppcp-version',
		'woocommerce_venmo_settings',
		'woocommerce_pay-later_settings',
		'woocommerce_paypal_settings',
		'woocommerce_payments_provider_state_snapshots',
	];

	/**
	 * Export PayPal settings
	 *
	 * @return Step
	 */
	public function export(): Step {
		$paypal_options = [];

		foreach ( self::PAYPAL_OPTIONS as $option_name ) {
			$value = get_option( $option_name );
			if ( false !== $value ) {
				$paypal_options[ $option_name ] = $value;
			}
		}

		return new SetSiteOptions( $paypal_options );
	}

	/**
	 * Get step name
	 *
	 * @return string
	 */
	public function get_step_name(): string {
		return SetSiteOptions::get_step_name();
	}

	/**
	 * Get alias for this exporter
	 *
	 * @return string
	 */
	public function get_alias(): string {
		return 'paypalSettings';
	}

	/**
	 * Return label used in the frontend
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'PayPal Settings', 'woocommerce' );
	}

	/**
	 * Return description used in the frontend
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Exports PayPal Commerce Platform settings and configuration options.', 'woocommerce' );
	}

	/**
	 * Check if user has capability to export PayPal settings
	 *
	 * @return bool
	 */
	public function check_step_capabilities(): bool {
		return current_user_can( 'manage_woocommerce' ) && current_user_can( 'manage_options' );
	}
}
