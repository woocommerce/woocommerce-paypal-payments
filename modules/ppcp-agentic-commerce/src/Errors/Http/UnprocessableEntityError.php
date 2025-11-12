<?php
/**
 * 422 Unprocessable Entity HTTP error.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http;

use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\HttpError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\HttpErrorName;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;

/**
 * Use when business rules prevent cart operations.
 * Can optionally include rich business context via ValidationIssue.
 */
class UnprocessableEntityError extends HttpError {
	protected const ERROR_NAME  = HttpErrorName::UNPROCESSABLE_ENTITY;
	protected const STATUS_CODE = 422;

	private ?ValidationIssue $business_context;

	/**
	 * Create unprocessable entity error with optional business context.
	 *
	 * @param string               $message          Error message.
	 * @param array|null           $details          Optional technical details.
	 * @param ValidationIssue|null $business_context Optional rich business context.
	 * @param string|null          $debug_id         Optional debug ID.
	 */
	public function __construct(
		string $message,
		?array $details = null,
		?ValidationIssue $business_context = null,
		?string $debug_id = null
	) {
		parent::__construct( $message, $details, $debug_id ?? $this->generate_debug_id() );
		$this->business_context = $business_context;
	}

	/**
	 * Convert to array with business_context when present.
	 */
	public function to_array(): array {
		$data = parent::to_array();

		if ( $this->business_context ) {
			$data['business_context'] = $this->business_context->to_array();
		}

		return $data;
	}

	public function get_business_context(): ?ValidationIssue {
		return $this->business_context;
	}
}
