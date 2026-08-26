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
use Automattic\WooCommerce\Blueprint\Steps\Step;

/**
 * PayPal Settings Exporter for WooCommerce Blueprint.
 *
 * Registered twice, as the default connection-free export and as the opt-in one.
 * Two instances rather than one configurable export because Blueprint gives
 * exporters no per-request context: choosing an alias is the only lever the UI has.
 */
class PayPalSettingsExporter implements StepExporter, HasAlias {

	/**
	 * Sentinel value to detect if option doesn't exist.
	 */
	private const OPTION_NOT_FOUND = '__PAYPAL_OPTION_NOT_FOUND__';

	/**
	 * Alias of the default export, which carries no connection details.
	 */
	public const ALIAS = 'paypalSettings';

	/**
	 * Alias of the opt-in export, which carries the connection details.
	 */
	public const ALIAS_WITH_CONNECTION = 'paypalSettingsWithConnection';

	/**
	 * Selection id of the opt-in export.
	 *
	 * Deliberately different from the step name emitted into the file, so that
	 * asking for 'setPayPalSettings' can only ever select the safe export.
	 */
	public const STEP_NAME_WITH_CONNECTION = 'setPayPalSettingsWithConnection';

	/**
	 * Strips connection data from the exported options.
	 *
	 * @var ConnectionDataSanitizer
	 */
	private ConnectionDataSanitizer $sanitizer;

	/**
	 * Whether this instance exports the merchant's connection details.
	 *
	 * @var bool
	 */
	private bool $include_connection;

	/**
	 * Constructor.
	 *
	 * @param ConnectionDataSanitizer $sanitizer          Connection data sanitizer.
	 * @param bool                    $include_connection Whether to export connection details.
	 */
	public function __construct(
		ConnectionDataSanitizer $sanitizer,
		bool $include_connection = false
	) {
		$this->sanitizer          = $sanitizer;
		$this->include_connection = $include_connection;
	}

	/**
	 * Export PayPal settings.
	 *
	 * @return Step
	 */
	public function export(): Step {
		$paypal_options = array();

		foreach ( PayPalBlueprintOptions::OPTION_NAMES as $option_name ) {
			$value = get_option( $option_name, self::OPTION_NOT_FOUND );
			if ( self::OPTION_NOT_FOUND !== $value ) {
				$paypal_options[ $option_name ] = $value;
			}
		}

		if ( ! $this->includes_connection() ) {
			$paypal_options = $this->sanitizer->sanitize( $paypal_options );
		}

		return new SetPayPalSettings( $paypal_options );
	}

	/**
	 * Whether the resulting export carries the merchant's connection details.
	 *
	 * Filter 'woocommerce_paypal_payments_blueprint_export_connection_details' for
	 * automated environments with no merchant present to make the choice. Returning
	 * true makes every export from this store include the connection details.
	 *
	 * @return bool
	 */
	private function includes_connection(): bool {
		return (bool) apply_filters(
			'woocommerce_paypal_payments_blueprint_export_connection_details',
			$this->include_connection
		);
	}

	/**
	 * Get step name.
	 *
	 * @return string
	 */
	public function get_step_name(): string {
		return $this->include_connection
			? self::STEP_NAME_WITH_CONNECTION
			: SetPayPalSettings::get_step_name();
	}

	/**
	 * Get alias for this exporter.
	 *
	 * @return string
	 */
	public function get_alias(): string {
		return $this->include_connection
			? self::ALIAS_WITH_CONNECTION
			: self::ALIAS;
	}

	/**
	 * Return label used in the frontend.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->include_connection
			? __( 'PayPal Settings (including connection details)', 'woocommerce-paypal-payments' )
			: __( 'PayPal Settings', 'woocommerce-paypal-payments' );
	}

	/**
	 * Return the description used in the frontend.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->include_connection
			? __( 'Exports PayPal Payments settings together with the connection credentials of the connected account.', 'woocommerce-paypal-payments' )
			: __( 'Exports PayPal Payments settings and configuration options, without any connection credentials.', 'woocommerce-paypal-payments' );
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
