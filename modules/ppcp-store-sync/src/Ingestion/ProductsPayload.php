<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use WC_Product;
use WC_Product_Variation;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
class ProductsPayload
{
    private string $merchant_store_url;
    /**
     * @var int[]
     */
    private array $product_ids;
    private ProductManager $product_manager;
    public function __construct(string $merchant_store_url, array $product_ids, ProductManager $product_manager)
    {
        $this->merchant_store_url = $merchant_store_url;
        $this->product_ids = $product_ids;
        $this->product_manager = $product_manager;
    }
    /**
     * @return ProductDTO[]
     */
    public function get_products(): array
    {
        return $this->transform_products($this->product_ids);
    }
    /**
     * @return ProductDTO[]
     */
    private function transform_products(array $product_ids): array
    {
        $api_products = array();
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }
            // Skip variations - handle them separately.
            if ($product->get_type() === 'variation') {
                continue;
            }
            // Handle variable products by only adding their variants.
            if ($product->is_type('variable')) {
                $variants = $this->get_product_variants($product);
                if ($variants) {
                    // Only add variants, not the parent variable product.
                    $api_products = array_merge($api_products, $variants);
                }
                continue;
            }
            // For all other product types (simple, grouped, etc.).
            $api_products[] = new \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductDTO($this->merchant_store_url, (string) $product->get_id(), (string) $product->get_id(), $this->product_manager->get_product_title($product), $this->product_manager->get_product_link($product), $this->product_manager->get_product_image($product), $this->product_manager->get_product_description($product, $product->get_short_description()), $this->product_manager->format_price($product->get_price()), $this->product_manager->get_product_availability($product), $product->get_sku() ?: null, $product->get_sale_price() ? $this->product_manager->format_price($product->get_sale_price()) : null, $this->product_manager->get_product_type($product) ?: null);
        }
        return $api_products;
    }
    /**
     * @return ProductDTO[]
     */
    private function get_product_variants(WC_Product $variable_product): array
    {
        $variants = array();
        $variation_ids = $variable_product->get_children();
        $product_type = $this->product_manager->get_product_type($variable_product);
        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation instanceof WC_Product_Variation || !$variation->is_purchasable()) {
                continue;
            }
            $attributes = $this->extract_variant_attributes($variation);
            $variants[] = new \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductDTO($this->merchant_store_url, (string) $variation->get_id(), (string) $variable_product->get_id(), $this->product_manager->get_product_title($variation), $this->product_manager->get_product_link($variation), $this->product_manager->get_product_image($variation, wp_get_attachment_image_url((int) $variable_product->get_image_id(), 'full') ?: ''), $this->product_manager->get_product_description($variation, $variable_product->get_description()), $this->product_manager->format_price($variation->get_price()), $this->product_manager->get_product_availability($variation), $variation->get_sku() ?: null, $variation->get_sale_price() ? $this->product_manager->format_price($variation->get_sale_price()) : null, $product_type ?: null, $attributes['color'] ?? null, $attributes['size'] ?? null, $attributes['gender'] ?? null);
        }
        return $variants;
    }
    /**
     * Extracts the color/size/gender attributes from a variation.
     *
     * WooCommerce prefixes attribute keys with `attribute_pa_` (taxonomy) or
     * `attribute_` (custom); both are stripped before matching.
     *
     * @return array<string, string> Map of the recognised attribute names to their values.
     */
    private function extract_variant_attributes(WC_Product_Variation $variation): array
    {
        $attributes = array();
        foreach ($variation->get_variation_attributes() as $attribute => $value) {
            $clean_attr = str_replace(array('attribute_pa_', 'attribute_'), '', $attribute);
            if (in_array($clean_attr, array('color', 'size', 'gender'), \true)) {
                $attributes[$clean_attr] = (string) $value;
            }
        }
        return $attributes;
    }
}
