<?php

/**
 * HTTP error names enum for agentic commerce errors.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Enums
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Standard HTTP error names from the PayPal Cart API specification.
 */
class HttpErrorName
{
    // 400 Bad Request errors
    public const INVALID_REQUEST = 'INVALID_REQUEST';
    public const INVALID_CART_ID = 'INVALID_CART_ID';
    // 404 Not Found errors
    public const CART_NOT_FOUND = 'CART_NOT_FOUND';
    // 422 Unprocessable Entity errors
    public const UNPROCESSABLE_ENTITY = 'UNPROCESSABLE_ENTITY';
    // 500 Internal Server Error errors
    public const INTERNAL_SERVER_ERROR = 'INTERNAL_SERVER_ERROR';
    public const PAYMENT_PROCESSOR_UNAVAILABLE = 'PAYMENT_PROCESSOR_UNAVAILABLE';
    public const PAYMENT_CAPTURE_FAILED = 'PAYMENT_CAPTURE_FAILED';
    public const INVENTORY_SYSTEM_ERROR = 'INVENTORY_SYSTEM_ERROR';
    public const ORDER_SYSTEM_ERROR = 'ORDER_SYSTEM_ERROR';
    /**
     * Get the appropriate HTTP status code for an error name.
     *
     * @param string $name The error name.
     * @return int
     */
    public static function get_status_code(string $name): int
    {
        switch ($name) {
            case self::INVALID_REQUEST:
            case self::INVALID_CART_ID:
                return 400;
            case self::CART_NOT_FOUND:
                return 404;
            case self::UNPROCESSABLE_ENTITY:
                return 422;
            case self::INTERNAL_SERVER_ERROR:
            case self::PAYMENT_PROCESSOR_UNAVAILABLE:
            case self::PAYMENT_CAPTURE_FAILED:
            case self::INVENTORY_SYSTEM_ERROR:
            case self::ORDER_SYSTEM_ERROR:
            default:
                return 500;
        }
    }
}
