<?php

/**
 * Detects third-party plugins relevant for compatibility checks.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PluginDetector
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\PluginDetector;

/**
 * Detects active plugins that may customize products
 *
 * @see self::scan() for the plugins list.
 */
class PluginDetector
{
    /**
     * @return array<string, bool> List of plugins check was made for,
     *      boolean shows whether the plugin is active
     */
    public function scan(): array
    {
        return array('woocommerce-subscriptions' => $this->is_woocommerce_subscriptions_active(), 'woocommerce-gift-cards' => $this->is_woocommerce_gift_cards_active(), 'woocommerce-product-bundles' => $this->is_woocommerce_product_bundles_active(), 'woocommerce-product-addons' => $this->is_woocommerce_product_addons_active(), 'woocommerce-min-max-quantities' => $this->is_woocommerce_min_max_quantities_active(), 'woocommerce-composite-products' => $this->is_woocommerce_composite_products_active(), 'woocommerce-shipping-per-product' => $this->is_woocommerce_shipping_per_product_active(), 'woocommerce-deposits' => $this->is_woocommerce_deposits_active());
    }
    /**
     * Checks whether WooCommerce Subscriptions is active.
     *
     * @return bool
     */
    private function is_woocommerce_subscriptions_active(): bool
    {
        return class_exists(\WC_Subscriptions::class);
    }
    /**
     * Checks whether WooCommerce Gift Cards is active.
     *
     * @return bool
     */
    private function is_woocommerce_gift_cards_active(): bool
    {
        return function_exists('WC_GC');
    }
    /**
     * Checks whether WooCommerce Product Bundles is active.
     *
     * @return bool
     */
    private function is_woocommerce_product_bundles_active(): bool
    {
        return class_exists(\WC_Bundles::class);
    }
    /**
     * Checks whether WooCommerce Product Add-Ons is active.
     *
     * @return bool
     */
    private function is_woocommerce_product_addons_active(): bool
    {
        return class_exists(\WC_Product_Addons::class);
    }
    /**
     * Checks whether WooCommerce Min/Max Quantities is active.
     *
     * @return bool
     */
    private function is_woocommerce_min_max_quantities_active(): bool
    {
        return class_exists(\WC_Min_Max_Quantities::class);
    }
    /**
     * Checks whether WooCommerce Composite Products is active.
     *
     * @return bool
     */
    private function is_woocommerce_composite_products_active(): bool
    {
        return class_exists(\WC_Composite_Products::class);
    }
    /**
     * Checks whether WooCommerce Per-Product Shipping is active.
     *
     * @return bool
     */
    private function is_woocommerce_shipping_per_product_active(): bool
    {
        return class_exists(\WC_Shipping_Per_Product_Init::class);
    }
    /**
     * Checks whether WooCommerce Deposits is active.
     *
     * @return bool
     */
    private function is_woocommerce_deposits_active(): bool
    {
        return defined('WC_DEPOSITS_VERSION');
    }
}
