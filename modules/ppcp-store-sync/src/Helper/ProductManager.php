<?php

/**
 * Responsibility: WooCommerce Products
 *
 * Unified helper for WooCommerce product resolution and stock checking.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use WC_Product;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
class ProductManager
{
    protected static array $product_cache = array();
    /**
     * Find a product by variant_id or item_id using multiple resolution strategies.
     *
     * Resolution strategies:
     * 1. SKU lookup for variant_id
     * 2. SKU lookup for item_id
     * 3. Direct ID casting for variant_id
     * 4. Direct ID casting for item_id
     *
     * @param CartItem $item The cart item.
     * @return WC_Product|null The resolved product or null.
     */
    public function find_product(CartItem $item): ?WC_Product
    {
        $variant_id = $item->variant_id();
        // @phpstan-ignore-next-line method.deprecated
        $item_id = $item->item_id();
        $cache_key = $this->build_cache_key($variant_id, $item_id);
        if (array_key_exists($cache_key, self::$product_cache)) {
            return self::$product_cache[$cache_key];
        }
        $product_id = null;
        // Strategy 1: Try variant_id as SKU.
        if ($variant_id) {
            $product_id = wc_get_product_id_by_sku($variant_id);
        }
        // Strategy 2: Try item_id as SKU.
        if (!$product_id && $item_id) {
            $product_id = wc_get_product_id_by_sku($item_id);
        }
        // Strategy 3: Try variant_id as direct ID.
        if (!$product_id && $variant_id && is_numeric($variant_id)) {
            $product_id = (int) $variant_id;
        }
        // Strategy 4: Try item_id as direct ID.
        if (!$product_id && $item_id && is_numeric($item_id)) {
            $product_id = (int) $item_id;
        }
        $product = $product_id ? wc_get_product($product_id) : null;
        $product = $product instanceof WC_Product ? $product : null;
        self::$product_cache[$cache_key] = $product;
        return $product;
    }
    /**
     * Check if the product is in stock with optional quantity validation.
     *
     * @param WC_Product $product  The product to check.
     * @param int|null   $quantity The desired quantity (optional).
     * @return bool True if the product is in stock (and has sufficient quantity if provided).
     */
    public function is_in_stock(WC_Product $product, ?int $quantity = null): bool
    {
        if (!$product->is_in_stock()) {
            return \false;
        }
        if ($quantity === null) {
            return \true;
        }
        if ($product->managing_stock()) {
            $stock_quantity = $product->get_stock_quantity();
            if ($stock_quantity !== null && $stock_quantity < $quantity) {
                return \false;
            }
        }
        return \true;
    }
    private function build_cache_key(?string $variant_id, ?string $item_id): string
    {
        return ($variant_id ?? 'null') . '|' . ($item_id ?? 'null');
    }
}
