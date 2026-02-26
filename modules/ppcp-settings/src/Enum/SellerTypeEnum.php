<?php

/**
 * Defines possible values for the merchant's `seller_type` property.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Enum
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Enum;

/**
 * Enum for the merchant's `seller_type` property.
 */
class SellerTypeEnum
{
    /**
     * Seller is a business, signed in with a business account.
     */
    public const BUSINESS = 'business';
    /**
     * Casual seller, authenticated with a personal account.
     */
    public const PERSONAL = 'personal';
    /**
     * Initial status, before the seller authenticated, or if detection failed.
     */
    public const UNKNOWN = 'unknown';
    /**
     * Get all valid seller types.
     *
     * @return array List of all valid seller_types.
     */
    public static function get_valid_values(): array
    {
        return array(self::BUSINESS, self::PERSONAL, self::UNKNOWN);
    }
    /**
     * Check if a given type is valid.
     *
     * @param string $type The value to validate.
     * @return bool True, if the value is a valid seller_type.
     */
    public static function is_valid(string $type): bool
    {
        return in_array($type, self::get_valid_values(), \true);
    }
}
