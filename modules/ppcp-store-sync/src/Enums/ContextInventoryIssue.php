<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Specific issue codes for inventory-related validation contexts.
 *
 * Used as the value of the SPECIFIC_ISSUE constant on InventoryIssueContext subclasses.
 */
class ContextInventoryIssue
{
    public const ITEM_OUT_OF_STOCK = 'ITEM_OUT_OF_STOCK';
    public const INSUFFICIENT_INVENTORY = 'INSUFFICIENT_INVENTORY';
    public const BACK_ORDERED = 'BACK_ORDERED';
    public const PRE_ORDER_ONLY = 'PRE_ORDER_ONLY';
    public const ITEM_DISCONTINUED = 'ITEM_DISCONTINUED';
    public const LOW_STOCK_WARNING = 'LOW_STOCK_WARNING';
    public const INVENTORY_RESERVED = 'INVENTORY_RESERVED';
    public const SEASONAL_UNAVAILABLE = 'SEASONAL_UNAVAILABLE';
    public const VARIANT_NOT_AVAILABLE = 'VARIANT_NOT_AVAILABLE';
    public const CUSTOM_OPTION_UNAVAILABLE = 'CUSTOM_OPTION_UNAVAILABLE';
}
