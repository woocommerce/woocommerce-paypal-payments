<?php
/**
 * PayPal Settings Blueprint Importer.
 *
 * @package WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

use Automattic\WooCommerce\Blueprint\StepProcessor;
use Automattic\WooCommerce\Blueprint\StepProcessorResult;
use Automattic\WooCommerce\Blueprint\Steps\SetSiteOptions;

/**
 * PayPal Settings Importer.
 */
class PayPalSettingsImporter implements StepProcessor {

	/**
	 * Sentinel value to detect if option doesn't exist.
	 */
	private const OPTION_NOT_FOUND = '__PAYPAL_OPTION_NOT_FOUND__';

	/**
	 * Explicit list of PayPal options that can be imported.
	 *
	 * @var array<string>
	 */
	private const PAYPAL_OPTIONS = array(
		// Core PPCP data settings (new settings).
		'woocommerce-ppcp-data-common',
		'woocommerce-ppcp-data-onboarding',
		'woocommerce-ppcp-data-payment',
		'woocommerce-ppcp-data-settings',
		'woocommerce-ppcp-data-styling',
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

	/**
	 * Process PayPal settings import.
	 *
	 * @param object $schema Schema object.
	 * @return StepProcessorResult
	 */
	public function process( $schema ): StepProcessorResult {
		$result = StepProcessorResult::success( SetSiteOptions::get_step_name() );

		if ( ! isset( $schema->options ) || ! is_object( $schema->options ) ) {
			$result->add_error( 'Invalid PayPal options data' );
			return $result;
		}

		// Validate that the object can be meaningfully converted to array.
		if ( ! $this->is_valid_options_object( $schema->options ) ) {
			$result->add_error( 'PayPal options data is not in the expected format' );
			return $result;
		}

		$options        = (array) $schema->options;
		$imported_count = 0;

		foreach ( $options as $option_name => $option_value ) {
			// Validate option name first (before using it in any operations).
			if ( ! $this->is_valid_option_name( $option_name ) ) {
				$result->add_error( 'Invalid option name provided' );
				continue;
			}

			// Validate option value early.
			if ( ! $this->is_valid_option_value( $option_value ) ) {
				$sanitized_name = sanitize_text_field( (string) $option_name );
				$result->add_warn( "Skipped option with invalid value: {$sanitized_name}" );
				continue;
			}

			// Check if this is a PayPal-related option.
			if ( ! $this->is_paypal_option( $option_name ) ) {
				$sanitized_name = sanitize_text_field( $option_name );
				$result->add_warn( "Skipped non-PayPal option: {$sanitized_name}" );
				continue;
			}

			// Attempt to update the option with proper error handling.
			if ( $this->update_option_safely( $option_name, $option_value ) ) {
				++$imported_count;
			} else {
				$sanitized_name = sanitize_text_field( $option_name );
				$result->add_error( "Failed to update option: {$sanitized_name}" );
			}
		}

		$result->add_info( "Successfully imported {$imported_count} PayPal options" );
		return $result;
	}

	/**
	 * Get step class.
	 *
	 * @return string
	 */
	public function get_step_class(): string {
		return SetSiteOptions::class;
	}

	/**
	 * Check capabilities.
	 *
	 * @param object $schema Schema object.
	 * @return bool
	 */
	public function check_step_capabilities( $schema ): bool {
		return current_user_can( 'manage_woocommerce' ) && current_user_can( 'manage_options' );
	}

	/**
	 * Validate that the options object can be meaningfully converted to array.
	 *
	 * @param object $options The options object.
	 * @return bool
	 */
	private function is_valid_options_object( object $options ): bool {
		// Check if it's a stdClass or iterable object that can be cast to array.
		return $options instanceof \stdClass || is_iterable( $options );
	}

	/**
	 * Validate option name.
	 *
	 * @param mixed $option_name The option name to validate.
	 * @return bool
	 */
	private function is_valid_option_name( $option_name ): bool {
		return is_string( $option_name ) && ! empty( trim( $option_name ) ) && strlen( $option_name ) <= 191;
	}

	/**
	 * Validate option value for WordPress options.
	 *
	 * @param mixed $option_value The option value to validate.
	 * @return bool
	 */
	private function is_valid_option_value( $option_value ): bool {
		// WordPress options should be scalar, array, or object (but not resources or closures).
		if ( is_resource( $option_value ) || $option_value instanceof \Closure ) {
			return false;
		}

		if ( null === $option_value ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if option is in the PayPal options allowlist.
	 *
	 * @param string $option_name Option name.
	 * @return bool
	 */
	private function is_paypal_option( string $option_name ): bool {
		return in_array( $option_name, self::PAYPAL_OPTIONS, true );
	}

	/**
	 * Safely update an option with proper comparison for existing values.
	 *
	 * @param string $option_name  Option name.
	 * @param mixed  $option_value Option value.
	 * @return bool
	 */
	private function update_option_safely( string $option_name, $option_value ): bool {
		// Convert objects to arrays recursively.
		$option_value = $this->convert_objects_to_arrays( $option_value );

		// Get the current value with a sentinel to distinguish between false and non-existent.
		$current_value = get_option( $option_name, self::OPTION_NOT_FOUND );

		// If the values are already equal, consider it a success.
		if ( self::OPTION_NOT_FOUND !== $current_value && $this->values_are_equal( $current_value, $option_value ) ) {
			return true;
		}

		return update_option( $option_name, $option_value );
	}

	/**
	 * Recursively convert objects to arrays.
	 * Blueprint data comes in as stdClass objects from JSON decode.
	 *
	 * @param mixed $data The data to convert.
	 * @return mixed
	 */
	private function convert_objects_to_arrays( $data ) {
		if ( is_object( $data ) ) {
			$data = get_object_vars( $data );
		}

		if ( is_array( $data ) ) {
			return array_map( array( $this, 'convert_objects_to_arrays' ), $data );
		}

		return $data;
	}

	/**
	 * Compare two values for equality, handling complex data types properly.
	 *
	 * @param mixed $value1 First value.
	 * @param mixed $value2 Second value.
	 * @return bool
	 */
	private function values_are_equal( $value1, $value2 ): bool {
		// For arrays and objects, serialize for comparison to handle deep equality.
		if ( ( is_array( $value1 ) || is_object( $value1 ) ) && ( is_array( $value2 ) || is_object( $value2 ) ) ) {
			return serialize( $value1 ) === serialize( $value2 );
		}

		// For scalar values, use strict comparison.
		return $value1 === $value2;
	}
}
