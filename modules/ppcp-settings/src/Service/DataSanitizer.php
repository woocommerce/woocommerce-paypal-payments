<?php
/**
 * Provides data sanitization logic.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service;

use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;

/**
 * DataSanitizer service. Generally used by REST endpoints (sanitize input data)
 * and data models (sanitize data during DB access)
 */
class DataSanitizer {
	/**
	 * Sanitizes the provided styling data.
	 *
	 * @param mixed   $data     The styling data to sanitize.
	 * @param ?string $location Name of the location.
	 * @return LocationStylingDTO Styling data.
	 */
	public function sanitize_location_style( $data, string $location = null ) : LocationStylingDTO {
		if ( $data instanceof LocationStylingDTO ) {
			if ( $location ) {
				$data->location = $location;
			}

			return $data;
		}

		if ( is_object( $data ) ) {
			$data = (array) $data;
		}

		if ( ! is_array( $data ) ) {
			return new LocationStylingDTO( $location ?? '' );
		}

		if ( null === $location ) {
			$location = $data['location'] ?? '';
		}

		$is_enabled = $this->sanitize_bool( $data['enabled'] ?? true );
		$shape      = $this->sanitize_text( $data['shape'] ?? 'rect' );
		$label      = $this->sanitize_text( $data['label'] ?? 'pay' );
		$color      = $this->sanitize_text( $data['color'] ?? 'gold' );
		$layout     = $this->sanitize_text( $data['layout'] ?? 'vertical' );
		$tagline    = $this->sanitize_bool( $data['tagline'] ?? false );
		$methods    = $this->sanitize_array(
			$data['methods'] ?? array(),
			array( $this, 'sanitize_text' )
		);

		return new LocationStylingDTO(
			$location,
			$is_enabled,
			$methods,
			$shape,
			$label,
			$color,
			$layout,
			$tagline
		);
	}

	/**
	 * Helper. Ensures the value is a string.
	 *
	 * @param mixed  $value   Value to sanitize.
	 * @param string $default Default value.
	 * @return string Sanitized string.
	 */
	protected function sanitize_text( $value, string $default = '' ) : string {
		return sanitize_text_field( $value ?? $default );
	}

	/**
	 * Helper. Ensures the value is a boolean.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return bool Sanitized boolean.
	 */
	protected function sanitize_bool( $value ) : bool {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Helper. Ensures the value is an array and all items are sanitized.
	 *
	 * @param null|array $array             Value to sanitize.
	 * @param callable   $sanitize_callback Callback to sanitize each item in the array.
	 * @return array Array with sanitized items.
	 */
	protected function sanitize_array( ?array $array, callable $sanitize_callback ) : array {
		if ( ! is_array( $array ) ) {
			return array();
		}

		return array_map( $sanitize_callback, $array );
	}
}
