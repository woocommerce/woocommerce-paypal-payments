<?php
/**
 * Base class for all business rule validations.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Validation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

abstract class ValidationIssue {
	public function to_array(): array {
		return array( 'message' => 'not implemented' );
	}
}
