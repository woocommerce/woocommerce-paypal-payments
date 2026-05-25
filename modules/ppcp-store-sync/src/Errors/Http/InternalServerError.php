<?php

/**
 * 500 Internal Server Error HTTP error.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Errors\Http
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Errors\Http;

use WooCommerce\PayPalCommerce\StoreSync\Errors\AgenticError;
use WooCommerce\PayPalCommerce\StoreSync\Enums\HttpErrorName;
/**
 * Use for system errors, database failures, third-party service issues.
 */
class InternalServerError extends AgenticError
{
    protected const ERROR_NAME = HttpErrorName::INTERNAL_SERVER_ERROR;
    protected const STATUS_CODE = 500;
}
