<?php
/**
 * Defines the gift option schema.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @see GiftOptionsTest - Unit tests for this class.
 */
class GiftOptions extends AgenticSchema {
	private bool $is_gift = false;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->is_gift = false;

		// Optional fields.
		if ( isset( $input['is_gift'] ) ) {
			$this->is_gift = $input['is_gift'];
		}
	}

	public function is_gift(): bool {
		return $this->is_gift;
	}

	public function gift_wrap(): bool {
		return true;
	}
}
