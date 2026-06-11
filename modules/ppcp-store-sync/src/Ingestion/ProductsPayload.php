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
    public function get_array(): array
    {
        return $this->transform_products($this->product_ids);
    }
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
            $api_product = array('id' => (string) $product->get_id(), 'title' => $this->product_manager->get_product_title($product), 'link' => $this->product_manager->get_product_link($product), 'image_link' => $this->product_manager->get_product_image($product), 'description' => $this->product_manager->get_product_description($product, $product->get_short_description()), 'price' => $this->product_manager->format_price($product->get_price()), 'availability' => $this->product_manager->get_product_availability($product), 'merchantStoreUrl' => $this->merchant_store_url);
            // Add optional fields.
            if ($product->get_sku()) {
                $api_product['mpn'] = $product->get_sku();
            }
            if ($product->get_sale_price()) {
                $api_product['sale_price'] = $this->product_manager->format_price($product->get_sale_price());
            }
            $product_type = $this->product_manager->get_product_type($product);
            if ($product_type) {
                $api_product['product_type'] = $product_type;
            }
            $api_products[] = $api_product;
        }
        return $api_products;
    }
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
            $variant = array('id' => (string) $variation->get_id(), 'item_group_id' => (string) $variable_product->get_id(), 'title' => $this->product_manager->get_product_title($variation), 'link' => $this->product_manager->get_product_link($variation), 'image_link' => $this->product_manager->get_product_image($variation, wp_get_attachment_image_url((int) $variable_product->get_image_id(), 'full') ?: ''), 'description' => $this->product_manager->get_product_description($variation, $variable_product->get_description()), 'price' => $this->product_manager->format_price($variation->get_price()), 'availability' => $this->product_manager->get_product_availability($variation), 'merchantStoreUrl' => $this->merchant_store_url);
            // Add variant attributes using WooCommerce methods.
            $attributes = $variation->get_variation_attributes();
            foreach ($attributes as $attribute => $value) {
                $clean_attr = str_replace(array('attribute_pa_', 'attribute_'), '', $attribute);
                if (in_array($clean_attr, array('color', 'size', 'gender'), \true)) {
                    $variant[$clean_attr] = $value;
                }
            }
            if ($variation->get_sku()) {
                $variant['mpn'] = $variation->get_sku();
            }
            if ($variation->get_sale_price()) {
                $variant['sale_price'] = $this->product_manager->format_price($variation->get_sale_price());
            }
            // Add the parent product.
            if ($product_type) {
                $variant['product_type'] = $product_type;
            }
            $variants[] = $variant;
        }
        return $variants;
    }
}
