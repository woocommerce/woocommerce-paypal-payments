<?php
/**
 * Defines the payment method schema.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @see PaymentMethodTest - Unit tests for this class.
 */
class PaymentMethod extends AgenticSchema {
	protected function parse_fields( array $input, callable $add_issue ): void {
		// TODO: Implement parse_fields() method.
	}

	public function type(): string {
		return 'paypal';
	}

	public function token(): string {
		return 'EC-7U8939823K567';
	}

	public function payer_id(): string {
		return 'PAYER123456789';
	}
}
