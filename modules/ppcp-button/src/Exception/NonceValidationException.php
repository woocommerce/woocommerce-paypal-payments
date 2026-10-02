<?php

/**
 * NonceValidationException.
 *
 * @package WooCommerce\PayPalCommerce\Button\Exception
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Exception;

/**
 * Thrown when nonce validation fails on an AJAX endpoint request.
 */
class NonceValidationException extends \WooCommerce\PayPalCommerce\Button\Exception\RuntimeException
{
}
