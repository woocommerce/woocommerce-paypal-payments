<?php
/**
 * Defines a single PayPal-controlled checkout field.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;

/**
 * @see CheckoutFieldTest - Unit tests for this class.
 */
class CheckoutField extends AgenticSchema {
	private const VALID_STATUS = array(
		'PENDING',
		'COMPLETED',
		'REJECTED',
		'ERROR',
	);

	private ?string $type = null;

	private string $status = '';

	private ?array $value = null;

	private ?array $context = null;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->type    = null;
		$this->status  = 'ERROR';
		$this->value   = null;
		$this->context = null;

		// Parse mandatory fields.
		if ( ! empty( $input['type'] ) && is_string( $input['type'] ) ) {
			$this->type = strtoupper( trim( $input['type'] ) );
		} else {
			$add_issue(
				ValidationIssue::create_missing_field( 'Type is required' )
					->user_message( 'The field type is mandatory' )
					->for_field( 'type' )
			);
		}

		if ( ! empty( $input['status'] ) && is_string( $input['status'] ) ) {
			$status = strtoupper( trim( $input['status'] ) );

			if ( in_array( $status, self::VALID_STATUS, true ) ) {
				$this->status = $status;
			} else {
				$add_issue(
					ValidationIssue::create_invalid_data( 'Status is invalid' )
					->user_message( 'The status value is not supported' )
					->for_field( 'status' )
				);
			}
		} else {
			$add_issue(
				ValidationIssue::create_missing_field( 'Status is required' )
				->user_message( 'The field status is mandatory' )
				->for_field( 'status' )
			);
		}

		// Parse optional fields.
		if ( isset( $input['value'] ) && is_array( $input['value'] ) ) {
			$this->value = $input['value'];
		}

		if ( isset( $input['context'] ) && is_array( $input['context'] ) ) {
			$this->context = $input['context'];
		}
	}

	public function type(): ?string {
		return $this->type;
	}

	public function status(): string {
		return $this->status;
	}

	public function value(): ?array {
		return $this->value;
	}

	public function context(): ?array {
		return $this->context;
	}
}
