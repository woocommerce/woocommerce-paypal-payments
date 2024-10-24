<?php
/**
 * REST endpoint to manage the onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WC_REST_Controller;

/**
 * Base class for REST controllers in the settings module.
 *
 * This is a base class for specific REST endpoints; do not instantiate this
 * class directly.
 */
class RestEndpoint extends WC_REST_Controller {
	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3/wc_paypal';

	/**
	 * Verify access.
	 *
	 * Override this method if custom permissions required.
	 */
	public function check_permission() : bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Sanitizes parameters based on a field mapping.
	 *
	 * This method iterates through a field map, applying sanitization methods
	 * to the corresponding values in the input parameters array.
	 *
	 * @param array $params    The input parameters to sanitize.
	 * @param array $field_map An associative array mapping profile keys to sanitization rules.
	 *                         Each rule should have 'js_name' and 'sanitize' keys.
	 *
	 * @return array An array of sanitized parameters.
	 */
	protected function sanitize_for_wordpress( array $params, array $field_map ) : array {
		$sanitized = array();

		foreach ( $field_map as $key => $details ) {
			$source_key    = $details['js_name'] ?? '';
			$sanitation_cb = $details['sanitize'] ?? null;

			if ( ! $source_key || ! isset( $params[ $source_key ] ) ) {
				continue;
			}

			$value = $params[ $source_key ];

			if ( null === $sanitation_cb ) {
				$sanitized[ $key ] = $value;
			} elseif ( method_exists( $this, $sanitation_cb ) ) {
				$sanitized[ $key ] = $this->{$sanitation_cb}( $value );
			} elseif ( is_callable( $sanitation_cb ) ) {
				$sanitized[ $key ] = $sanitation_cb( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitizes data for JavaScript based on a field mapping.
	 *
	 * This method transforms the input data array according to the provided field map,
	 * renaming keys to their JavaScript equivalents as specified in the mapping.
	 *
	 * @param array $data      The input data array to be sanitized.
	 * @param array $field_map An associative array mapping PHP keys to JavaScript key names.
	 *                         Each element should have a 'js_name' key specifying the JavaScript
	 *                         name.
	 *
	 * @return array An array of sanitized data with keys renamed for JavaScript use.
	 */
	protected function sanitize_for_javascript( array $data, array $field_map ) : array {
		$sanitized = array();

		foreach ( $field_map as $key => $details ) {
			$output_key = $details['js_name'] ?? '';

			if ( ! $output_key || ! isset( $data[ $key ] ) ) {
				continue;
			}

			$sanitized[ $output_key ] = $data[ $key ];
		}

		return $sanitized;
	}

	/**
	 * Convert a value to a boolean.
	 *
	 * @param mixed $value The value to convert.
	 *
	 * @return bool|null The boolean value, or null if not set.
	 */
	protected function to_boolean( $value ) : ?bool {
		return $value !== null ? (bool) $value : null;
	}

	/**
	 * Convert a value to a number.
	 *
	 * @param mixed $value The value to convert.
	 *
	 * @return int|float|null The numeric value, or null if not set.
	 */
	protected function to_number( $value ) {
		return $value !== null ? ( is_numeric( $value ) ? $value + 0 : null ) : null;
	}

}
