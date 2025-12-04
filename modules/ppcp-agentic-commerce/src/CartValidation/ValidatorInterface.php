<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;

interface ValidatorInterface {

	/**
	 * @param PayPalCart $cart The cart to validate.
	 * @return ValidationIssue[]|null List of detected validation issues or null if no issues found.
	 */
	public function validate( PayPalCart $cart ): ?array;
}
