<?php
/**
 * Base class for all business rule validations.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Validation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

/**
 * Implements the ValidationIssue schema.
 *
 * @see https://github.com/paypal/agent-commerce/blob/28b799b0d11b6fb62f423e203de6ea4b9f2ce122/v1/docs/SCHEMA_REFERENCE.md#validationissue
 */
abstract class ValidationIssue {
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
	 * Reference to the cart item that triggered the issue.
	 */
	private string $item_id;

	/**
	 * Context information about the validation issue.
	 */
	private array $context;

	/**
	 * Available resolution options for the validation issue.
	 */
	private array $resolution_options;

	/**
	 * Defines the validation issue contents.
	 *
	 * @param string $message            Technical error description.
	 * @param string $user_message       Optional. Customer friendly error message.
	 * @param string $field              Optional. Identifies the field that triggered the issue.
	 * @param string $item_id            Optional. Identifies the cart item that triggered the issue.
	 * @param array  $context            Optional. Context information.
	 * @param array  $resolution_options Optional. Available resolution options.
	 */
	public function __construct(
		string $message,
		string $user_message = '',
		string $field = '',
		string $item_id = '',
		array $context = array(),
		array $resolution_options = array()
	) {
		$this->message            = $message ?: 'Validation error occurred';
		$this->user_message       = $user_message;
		$this->field              = $field;
		$this->item_id            = $item_id;
		$this->context            = $context;
		$this->resolution_options = $resolution_options;
	}

	/**
	 * Returns the error code, which is a high-level description of the problem.
	 * Possible values are defined in the `Enums/ErrorCode` class.
	 */
	public function code(): string {
		return static::ISSUE_CODE;
	}

	/**
	 * Returns the error type, which classifies the issue.
	 * Possible values are defined in the `Enums/ErrorType` class.
	 */
	public function type(): string {
		return static::ISSUE_TYPE;
	}

	public function to_array(): array {
		$data = array(
			'code'    => $this->code(),
			'type'    => $this->type(),
			'message' => (string) substr( $this->message, 0, 255 ),
		);

		if ( $this->user_message ) {
			$data['user_message'] = (string) substr( $this->user_message, 0, 500 );
		}
		if ( $this->field ) {
			$data['field'] = $this->field;
		}
		if ( $this->item_id ) {
			$data['item_id'] = $this->item_id;
		}
		if ( ! empty( $this->context ) ) {
			$data['context'] = $this->context;
		}
		if ( ! empty( $this->resolution_options ) ) {
			$data['resolution_options'] = $this->resolution_options;
		}

		return $data;
	}
}
