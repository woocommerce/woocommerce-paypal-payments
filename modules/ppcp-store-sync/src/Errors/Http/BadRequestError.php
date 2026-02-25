<?php

/**
 * 400 Bad Request HTTP error.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Errors\Http
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Errors\Http;

use WooCommerce\PayPalCommerce\StoreSync\Enums\HttpErrorName;
use WooCommerce\PayPalCommerce\StoreSync\Errors\AgenticError;
/**
 * Use for invalid request format, malformed JSON, missing required fields.
 */
class BadRequestError extends AgenticError
{
    protected const ERROR_NAME = HttpErrorName::INVALID_REQUEST;
    protected const STATUS_CODE = 400;
}
