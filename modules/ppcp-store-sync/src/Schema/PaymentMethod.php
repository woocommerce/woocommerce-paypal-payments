<?php
/**
 * Defines the payment method schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\InvalidData;
use WooCommerce\PayPalCommerce\StoreSync\Validation\MissingField;

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
		if ( ! isset( $input['type'] ) || ! is_string( $input['type'] ) ) {
			$add_issue( new MissingField( 'Payment method is required', 'No value for the payment method type found', 'type' ) );
		} else {
			$type = trim( $input['type'] );

			if ( empty( $type ) ) {
				$add_issue( new MissingField( 'Payment method is required', 'No value for the payment method type found', 'type' ) );
			} elseif ( 'paypal' !== $type ) {
				$add_issue( new InvalidData( 'Unexpected payment method type', 'Only PayPal is supported', 'type' ) );
			}
		}

		// Optional fields.
		if ( isset( $input['token'] ) && is_string( $input['token'] ) ) {
			$this->token = trim( $input['token'] );
		}
		if ( isset( $input['payer_id'] ) && is_string( $input['payer_id'] ) ) {
			$this->payer_id = trim( $input['payer_id'] );
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
