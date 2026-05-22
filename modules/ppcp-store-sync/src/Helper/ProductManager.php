<?php

/**
 * Responsibility: WooCommerce Products
 *
 * Unified helper for WooCommerce product resolution, stock checking, and data extraction.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use WC_Product;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
class ProductManager
{
    protected static array $product_cache = array();
    private StoreCurrencyValue $store_currency;
    public function __construct(StoreCurrencyValue $store_currency)
    {
        $this->store_currency = $store_currency;
    }
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
    /**
     * @param WC_Product $product  The WooCommerce product object.
     * @param string     $fallback Fallback description (e.g. short description for simple products,
     *                             parent description for variations).
     * @return string Plain-text description, passed through the filter hook.
     */
    public function get_product_description(WC_Product $product, string $fallback = ''): string
    {
        $description = $product->get_description() ?: $fallback;
        $plain_text = wp_strip_all_tags($description);
        $plain_text = html_entity_decode($plain_text, \ENT_QUOTES, 'UTF-8');
        $plain_text = trim(preg_replace('/\s+/', ' ', $plain_text) ?? '');
        /**
         * Filters the product description for PayPal Agentic Commerce ingestion.
         *
         * @since 3.4.0
         *
         * @param string     $plain_text The plain text description.
         * @param WC_Product $product    The WooCommerce product object.
         */
        return apply_filters('woocommerce_paypal_payments_agentic_commerce_item_description', $plain_text, $product);
    }
    /**
     * @param WC_Product $product The WooCommerce product object.
     * @return string The filtered product title.
     */
    public function get_product_title(WC_Product $product): string
    {
        /**
         * Filters the product title for PayPal Agentic Commerce ingestion.
         *
         * @since 3.4.0
         *
         * @param string     $title   The product title.
         * @param WC_Product $product The WooCommerce product object.
         */
        return apply_filters('woocommerce_paypal_payments_agentic_commerce_item_title', $product->get_name(), $product);
    }
    /**
     * @param WC_Product $product The WooCommerce product object.
     * @return string The filtered product permalink.
     */
    public function get_product_link(WC_Product $product): string
    {
        /**
         * Filters the product link for PayPal Agentic Commerce ingestion.
         *
         * @since 3.4.0
         *
         * @param string     $link    The product permalink.
         * @param WC_Product $product The WooCommerce product object.
         */
        return apply_filters('woocommerce_paypal_payments_agentic_commerce_item_link', $product->get_permalink(), $product);
    }
    /**
     * @param WC_Product $product  The WooCommerce product object.
     * @param string     $fallback Fallback image URL (e.g. parent product image for variations).
     * @return string The filtered image URL.
     */
    public function get_product_image(WC_Product $product, string $fallback = ''): string
    {
        $image_id = (int) $product->get_image_id();
        $image_url = wp_get_attachment_image_url($image_id, 'full') ?: $fallback;
        /**
         * Filters the product image URL for PayPal Agentic Commerce ingestion.
         *
         * @since 3.4.0
         *
         * @param string     $image_url The product image URL.
         * @param WC_Product $product   The WooCommerce product object.
         */
        return apply_filters('woocommerce_paypal_payments_agentic_commerce_item_image', $image_url, $product);
    }
    /**
     * @param WC_Product $product The WooCommerce product object.
     * @return string The filtered availability status.
     */
    public function get_product_availability(WC_Product $product): string
    {
        $mapping = array('instock' => 'in stock', 'outofstock' => 'out of stock', 'onbackorder' => 'backorder');
        $availability = $mapping[$product->get_stock_status()] ?? 'out of stock';
        /**
         * Filters the product availability for PayPal Agentic Commerce usage.
         *
         * @since 3.4.0
         *
         * @param string     $availability The mapped availability status.
         * @param WC_Product $product      The WooCommerce product object.
         */
        return apply_filters('woocommerce_paypal_payments_agentic_commerce_item_availability', $availability, $product);
    }
    /**
     * @param WC_Product $product The WooCommerce product object.
     * @return string Plain-text category list, passed through the filter hook, or empty string.
     */
    public function get_product_type(WC_Product $product): string
    {
        $categories = wc_get_product_category_list($product->get_id());
        $plain_text = wp_strip_all_tags($categories);
        $plain_text = html_entity_decode($plain_text, \ENT_QUOTES, 'UTF-8');
        $plain_text = trim(preg_replace('/\s+/', ' ', $plain_text) ?? '');
        /**
         * Filters the product type for PayPal Agentic Commerce usage.
         *
         * @since 3.4.0
         *
         * @param string     $plain_text The plain text category list.
         * @param WC_Product $product    The WooCommerce product object.
         */
        return apply_filters('woocommerce_paypal_payments_agentic_commerce_item_product_type', $plain_text, $product);
    }
    /**
     * @param string|mixed $price WooCommerce uses strings, but any numeric value is accepted.
     *                            Defends the method against plugins or future changes that use
     *                            a different data type.
     * @return string
     */
    public function format_price($price): string
    {
        if (!$price || !is_numeric($price)) {
            return '';
        }
        return Money::create($price, $this->store_currency->value())->to_price();
    }
    private function build_cache_key(?string $variant_id, ?string $item_id): string
    {
        return ($variant_id ?? 'null') . '|' . ($item_id ?? 'null');
    }
}
