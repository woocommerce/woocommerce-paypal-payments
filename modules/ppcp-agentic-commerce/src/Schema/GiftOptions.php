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
	protected function parse_fields( array $input, callable $add_issue ): void {
		// TODO: Implement parse_fields() method.
	}

	public function is_gift(): bool {
		return true;
	}
}
