<?php
/**
 * Base class for all business rule validations.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Validation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\IssueContext;

/**
 * Implements the ValidationIssue schema.
 *
 * @see https://github.com/paypal/agent-commerce/blob/28b799b0d11b6fb62f423e203de6ea4b9f2ce122/v1/docs/SCHEMA_REFERENCE.md#validationissue
 */
abstract class ValidationIssue {
	/**
	 * Main error category.
	 *
	 * Child classes must override this constant.
	 */
	protected const ISSUE_CODE = '';

	/**
	 * Classifies the issue.
	 *
	 * Child classes must override this constant.
	 */
	protected const ISSUE_TYPE = '';

	private const MAX_MESSAGE_LENGTH = 255;

	private const MAX_USER_MESSAGE_LENGTH = 500;

	private const MAX_RESOLUTION_OPTIONS = 5;

	private string $message;

	private string $user_message = '';

	private string $field = '';

	private string $item_id = '';

	private array $context = array();

	private array $resolution_options = array();

	final private function __construct( string $message ) {
		$this->message = trim( substr( $message, 0, self::MAX_MESSAGE_LENGTH ) );
	}

	/**
	 * Creates a new validation issue instance.
	 *
	 * @param string $message Technical error description for AI consumption.
	 * @return static
	 */
	public static function create( string $message ): self {
		return new static( $message );
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

	/**
	 * Sets the field that triggered the issue.
	 *
	 * @param string $field Field path, e.g. "shipping_address.postal_code".
	 * @return static
	 */
	public function for_field( string $field ): self {
		$this->field = $field;

		return $this;
	}

	/**
	 * Sets the customer-friendly error message.
	 *
	 * @param string $user_message Customer-facing message.
	 * @return static
	 */
	public function user_message( string $user_message ): self {
		$this->user_message = trim( substr( $user_message, 0, self::MAX_USER_MESSAGE_LENGTH ) );

		return $this;
	}

	/**
	 * Sets the cart item ID that triggered the issue.
	 *
	 * @param string $item_id Cart item identifier.
	 * @return static
	 */
	public function item_id( string $item_id ): self {
		$this->item_id = $item_id;

		return $this;
	}

	/**
	 * Adds one or more context instances to the validation issue.
	 *
	 * Accepts either a single IssueContext or an array of IssueContext objects.
	 * Non-IssueContext values are silently ignored.
	 *
	 * @param IssueContext|array $context A context instance or array of instances.
	 * @return static
	 */
	public function add_context( $context ): self {
		if ( $context instanceof IssueContext ) {
			$this->context[] = $context;

			return $this;
		}

		if ( is_array( $context ) ) {
			foreach ( $context as $item ) {
				$this->add_context( $item );
			}
		}

		return $this;
	}

	/**
	 * Adds one or more resolution options to the validation issue.
	 *
	 * Accepts either a single ResolutionOption or an array of ResolutionOption objects.
	 * Non-ResolutionOption values are silently ignored.
	 * A maximum of 5 resolution options is allowed in total.
	 *
	 * @param ResolutionOption|array $resolution A resolution option or array of options.
	 * @return static
	 */
	public function add_resolution( $resolution ): self {
		if ( count( $this->resolution_options ) >= self::MAX_RESOLUTION_OPTIONS ) {
			return $this;
		}

		if ( $resolution instanceof ResolutionOption ) {
			$this->resolution_options[] = $resolution;

			return $this;
		}

		if ( is_array( $resolution ) ) {
			foreach ( $resolution as $item ) {
				$this->add_resolution( $item );
			}
		}

		return $this;
	}

	public function to_array(): array {
		$data = array(
			'code'    => $this->code(),
			'type'    => $this->type(),
			'message' => $this->message,
		);

		if ( $this->user_message ) {
			$data['user_message'] = $this->user_message;
		}
		if ( $this->field ) {
			$data['field'] = $this->field;
		}
		if ( $this->item_id ) {
			$data['item_id'] = $this->item_id;
		}
		if ( ! empty( $this->context ) ) {
			$data['context'] = array_map(
				static fn( IssueContext $context ) => $context->to_array(),
				$this->context
			);
		}
		if ( ! empty( $this->resolution_options ) ) {
			$data['resolution_options'] = array_map(
				static fn( ResolutionOption $option ) => $option->to_array(),
				$this->resolution_options
			);
		}

		return $data;
	}
}
