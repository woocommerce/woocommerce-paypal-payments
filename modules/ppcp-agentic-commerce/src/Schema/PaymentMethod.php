<?php
/**
 * Defines the payment method schema.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidData;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\MissingField;

/**
 * @see PaymentMethodTest - Unit tests for this class.
 */
class PaymentMethod extends AgenticSchema {
	private ?string $token = null;

	private ?string $payer_id = null;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->token    = null;
		$this->payer_id = null;

		// Mandatory fields.
		if ( ! empty( $input['type'] ) ) {
			if ( 'paypal' !== $input['type'] ) {
				$add_issue( new InvalidData( 'Unexpected payment method type', 'Only PayPal is supported', 'type' ) );
			}
		} else {
			$add_issue( new MissingField( 'Payment method is required', 'No value for the payment method type found', 'type' ) );
		}

		// Optional fields.
		if ( isset( $input['token'] ) ) {
			$this->token = $input['token'];
		}
		if ( isset( $input['payer_id'] ) ) {
			$this->payer_id = $input['payer_id'];
		}
	}

	public function type(): string {
		return 'paypal';
	}

	public function token(): ?string {
		return $this->token;
	}

	public function payer_id(): ?string {
		return $this->payer_id;
	}
}
