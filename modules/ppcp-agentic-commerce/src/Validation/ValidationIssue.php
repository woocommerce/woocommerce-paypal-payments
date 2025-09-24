<?php
/**
 * Base class for all business rule validations.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Validation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use RuntimeException;

/**
 * Implements the ValidationIssue schema.
 *
 * @see https://github.com/paypal/agent-commerce/blob/28b799b0d11b6fb62f423e203de6ea4b9f2ce122/v1/docs/SCHEMA_REFERENCE.md#validationissue
 */
abstract class ValidationIssue {
	private const VALID_CODES = array(
		'INVENTORY_ISSUE',
		'PRICING_ERROR',
		'SHIPPING_ERROR',
		'PAYMENT_ERROR',
		'DATA_ERROR',
		'BUSINESS_RULE_ERROR',
	);

	private const VALID_TYPES = array(
		'MISSING_FIELD',
		'INVALID_DATA',
		'BUSINESS_RULE',
	);

	/**
	 * Main error category.
	 *
	 * Child classes must define this constant.
	 */
	protected const ISSUE_CODE = '';

	/**
	 * Classifies the issue.
	 *
	 * Child classes must define this constant.
	 */
	protected const ISSUE_TYPE = '';

	/**
	 * Technical error message, mainly for AI.
	 */
	private string $message;

	/**
	 * Customer friendly error message.
	 */
	private string $user_message;

	/**
	 * Reference to the field that triggered the issue, e.g. "shipping_address.postal_code"
	 */
	private string $field;

	/**
	 * Defines the validation issue contents.
	 *
	 * @param string $message      Technical error description.
	 * @param string $user_message Optional. Customer friendly error message.
	 * @throws RuntimeException When the error specs are incomplete.
	 */
	public function __construct( string $message, string $user_message = '', string $field = '' ) {
		if ( ! in_array( static::ISSUE_CODE, self::VALID_CODES, true ) ) {
			throw new RuntimeException( 'Invalid ISSUE_CODE constant' );
		}
		if ( ! in_array( static::ISSUE_TYPE, self::VALID_TYPES, true ) ) {
			throw new RuntimeException( 'Invalid ISSUE_TYPE constant' );
		}
		if ( empty( $message ) ) {
			throw new RuntimeException( 'Validation message cannot be empty' );
		}

		$this->message      = $message;
		$this->user_message = $user_message;
		$this->field        = $field;
	}

	public function to_array(): array {
		$data = array(
			'code'    => static::ISSUE_CODE,
			'type'    => static::ISSUE_TYPE,
			'message' => (string) substr( $this->message, 0, 255 ),
		);

		if ( $this->user_message ) {
			$data['user_message'] = (string) substr( $this->user_message, 0, 500 );
		}
		if ( $this->field ) {
			$data['field'] = $this->field;
		}

		return $data;
	}
}
