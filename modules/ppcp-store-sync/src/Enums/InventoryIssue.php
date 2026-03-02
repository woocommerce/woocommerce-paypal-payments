<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Specific inventory-related issue codes.
 *
 * Used in the context.specific_issue field when the main error code
 * is INVENTORY_ISSUE.
 */
class InventoryIssue
{
    public const ITEM_OUT_OF_STOCK = 'ITEM_OUT_OF_STOCK';
    public const INSUFFICIENT_INVENTORY = 'INSUFFICIENT_INVENTORY';
    public const BACK_ORDERED = 'BACK_ORDERED';
    public const ITEM_DISCONTINUED = 'ITEM_DISCONTINUED';
    public const PRE_ORDER_ONLY = 'PRE_ORDER_ONLY';
    public const LOW_STOCK_WARNING = 'LOW_STOCK_WARNING';
    public const INVENTORY_RESERVED = 'INVENTORY_RESERVED';
    public const SEASONAL_UNAVAILABLE = 'SEASONAL_UNAVAILABLE';
    public const VARIANT_NOT_AVAILABLE = 'VARIANT_NOT_AVAILABLE';
    public const CUSTOM_OPTION_UNAVAILABLE = 'CUSTOM_OPTION_UNAVAILABLE';
    public static function get_all(): array
    {
        return array(self::ITEM_OUT_OF_STOCK, self::INSUFFICIENT_INVENTORY, self::BACK_ORDERED, self::ITEM_DISCONTINUED, self::PRE_ORDER_ONLY, self::LOW_STOCK_WARNING, self::INVENTORY_RESERVED, self::SEASONAL_UNAVAILABLE, self::VARIANT_NOT_AVAILABLE, self::CUSTOM_OPTION_UNAVAILABLE);
    }
    public static function is_valid(string $issue): bool
    {
        return in_array($issue, self::get_all(), \true);
    }
}
