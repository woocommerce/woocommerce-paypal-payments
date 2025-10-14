<?php
/**
 * Defines the applied coupon schema.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @see AppliedCouponTest - Unit tests for this class.
 */
class AppliedCoupon extends AgenticSchema {
	private ?string $code = null;

	private ?string $description = null;

	private ?Money $discount_amount = null;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->code            = null;
		$this->description     = null;
		$this->discount_amount = null;

		// Optional fields.
		if ( isset( $input['code'] ) && is_string( $input['code'] ) ) {
			$code = trim( $input['code'] );

			if ( $code ) {
				$this->code = $code;
			}
		}
		if ( isset( $input['description'] ) && is_string( $input['description'] ) ) {
			$description = trim( $input['description'] );

			if ( $description ) {
				$this->description = $description;
			}
		}
		if ( isset( $input['discount_amount'] ) && is_array( $input['discount_amount'] ) ) {
			$money = Money::from_array( $input['discount_amount'] );

			$issues = $money->validate();
			if ( empty( $issues ) ) {
				$this->discount_amount = $money;
			} else {
				foreach ( $issues as $issue ) {
					$add_issue( $issue );
				}
			}
		}
	}

	public function code(): ?string {
		return $this->code;
	}

	public function description(): ?string {
		return $this->description;
	}

	public function discount_amount(): ?Money {
		return $this->discount_amount;
	}
}
