<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;
interface ValidatorInterface
{
    /**
     * Validates cart against business rules.
     *
     * @param PayPalCart $cart The cart to validate.
     *
     * @return ValidationIssue|ValidationIssue[]|null An empty array or null if valid.
     *                                                Otherwise, a list of all validation issues
     *                                                that were detected.
     */
    public function validate(PayPalCart $cart);
}
