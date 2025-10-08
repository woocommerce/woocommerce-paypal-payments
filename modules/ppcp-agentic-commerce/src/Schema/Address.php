<?php
/**
 * Defines a postal address (shipping or billing).
 *
 * @see     https://github.com/paypal/agent-commerce/blob/28b799b0d11b6fb62f423e203de6ea4b9f2ce122/v1/docs/SCHEMA_REFERENCE.md#address
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @see AddressTest - Unit tests for this class.
 */
class Address extends AgenticSchema {
	private string $country_code = '';

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->country_code = '';

		// Parse mandatory fields.
		if ( isset( $input['country_code'] ) ) {
			$this->country_code = strtoupper( $input['country_code'] );
		}
	}

	public function country_code(): string {
		return $this->country_code;
	}
}
