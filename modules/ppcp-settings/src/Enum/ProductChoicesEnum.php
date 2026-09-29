<?php

/**
 * Defines valid "product" options that can be chosen during onboarding.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Enum
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Enum;

/**
 * Enum for the "product" list choice.
 */
class ProductChoicesEnum
{
    /**
     * Virtual products that don't require shipping
     * Sample: downloads, courses, events
     */
    public const VIRTUAL = 'virtual';
    /**
     * Physical products that require shipping
     * Sample: books, clothing, electronics
     */
    public const PHYSICAL = 'physical';
    /**
     * Subscription products that require automatic payments
     * Sample: memberships, subscriptions
     */
    public const SUBSCRIPTIONS = 'subscriptions';
    /**
     * Get all valid seller types.
     *
     * @return array List of all valid products.
     */
    public static function get_valid_values(): array
    {
        return array(self::VIRTUAL, self::PHYSICAL, self::SUBSCRIPTIONS);
    }
    /**
     * Check if a given type is valid.
     *
     * @param string $type The value to validate.
     * @return bool True, if the value is a valid product.
     */
    public static function is_valid(string $type): bool
    {
        return in_array($type, self::get_valid_values(), \true);
    }
}
