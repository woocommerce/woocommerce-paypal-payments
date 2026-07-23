<?php

/**
 * Detects whether a product has been customized by a first-party
 * WooCommerce extension (product type change or plugin-specific meta).
 *
 * @see self::scan() for the plugins list.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PluginDetector
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\PluginDetector;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Compat\Exception\PluginApiChangedException;
class ProductCustomizationDetector
{
    private \WooCommerce\PayPalCommerce\Compat\PluginDetector\PluginDetector $plugin_detector;
    private LoggerInterface $logger;
    /**
     * Cached result of $plugin_detector->scan(), since plugin activation
     * cannot change within a request but scan() may be called once per product.
     *
     * @var array<string, bool>|null
     */
    private ?array $active_plugins = null;
    public function __construct(\WooCommerce\PayPalCommerce\Compat\PluginDetector\PluginDetector $plugin_detector, LoggerInterface $logger)
    {
        $this->plugin_detector = $plugin_detector;
        $this->logger = $logger;
    }
    /**
     * @param \WC_Product $product The product to check.
     * @return array<string, bool> List of plugins check was made for,
     *      boolean shows whether the plugin has customized the product.
     */
    public function scan(\WC_Product $product): array
    {
        if (null === $this->active_plugins) {
            $this->active_plugins = $this->plugin_detector->scan();
        }
        $checks = array('woocommerce-subscriptions' => array($this, 'is_customized_by_subscriptions'), 'woocommerce-gift-cards' => array($this, 'is_customized_by_gift_cards'), 'woocommerce-product-bundles' => array($this, 'is_customized_by_product_bundles'), 'woocommerce-product-addons' => array($this, 'is_customized_by_product_addons'), 'woocommerce-min-max-quantities' => array($this, 'is_customized_by_min_max_quantities'), 'woocommerce-composite-products' => array($this, 'is_customized_by_composite_products'), 'woocommerce-shipping-per-product' => array($this, 'is_customized_by_shipping_per_product'), 'woocommerce-deposits' => array($this, 'is_customized_by_deposits'));
        $result = array();
        foreach ($checks as $plugin => $check) {
            if (empty($this->active_plugins[$plugin])) {
                $result[$plugin] = \false;
                continue;
            }
            try {
                // Only guards checks that call a plugin method (see assert_method_exists()).
                // Checks that read meta directly (min/max quantities, per-product shipping)
                // have no class/method to assert against, so they stay unprotected here.
                $result[$plugin] = (bool) call_user_func($check, $product);
            } catch (PluginApiChangedException $exception) {
                $this->logger->warning("Product customization check for \"{$plugin}\" failed: " . $exception->getMessage());
                $result[$plugin] = \false;
            }
        }
        return $result;
    }
    /**
     * Throws if the given plugin method does not exist, even though the
     * plugin was detected as active. This points at the plugin having
     * changed its API since this check was written.
     *
     * @param string $class The fully qualified class name.
     * @param string $method The method name.
     * @throws PluginApiChangedException If the class or method does not exist.
     */
    private function assert_method_exists(string $class, string $method): void
    {
        if (!method_exists($class, $method)) {
            throw new PluginApiChangedException("{$class}::{$method}() does not exist even though the plugin was detected as active. Its API may have changed.");
        }
    }
    /**
     * Checks whether the product is a WooCommerce Subscriptions product.
     *
     * @param \WC_Product $product The product to check.
     * @return bool
     */
    private function is_customized_by_subscriptions(\WC_Product $product): bool
    {
        $this->assert_method_exists(\WC_Subscriptions_Product::class, 'is_subscription');
        return \WC_Subscriptions_Product::is_subscription($product);
    }
    /**
     * Checks whether the product is a WooCommerce Gift Cards product.
     *
     * @param \WC_Product $product The product to check.
     * @return bool
     */
    private function is_customized_by_gift_cards(\WC_Product $product): bool
    {
        $this->assert_method_exists(\WC_GC_Gift_Card_Product::class, 'is_gift_card');
        return \WC_GC_Gift_Card_Product::is_gift_card($product);
    }
    /**
     * Checks whether the product is a WooCommerce Product Bundles product.
     *
     * @param \WC_Product $product The product to check.
     * @return bool
     */
    private function is_customized_by_product_bundles(\WC_Product $product): bool
    {
        return $product->is_type('bundle');
    }
    /**
     * Checks whether the product has its own WooCommerce Product Add-Ons configured.
     *
     * Calls WC_Product_Addons_Helper::get_product_addons() with $inc_parent and
     * $inc_global set to false, which returns only this product's own
     * `_product_addons` meta instead of merging in global/category-level addon
     * groups that are not specific to this product.
     *
     * @param \WC_Product $product The product to check.
     * @return bool
     */
    private function is_customized_by_product_addons(\WC_Product $product): bool
    {
        $this->assert_method_exists(\WC_Product_Addons_Helper::class, 'get_product_addons');
        $addons = \WC_Product_Addons_Helper::get_product_addons($product->get_id(), \false, \false, \false);
        return array() !== $addons;
    }
    /**
     * Checks whether the product has its own WooCommerce Min/Max Quantities rules.
     *
     * Reads the product-level meta keys directly. The plugin itself has no
     * product-only accessor for these two fields and reads the same meta keys
     * directly internally; its only related method, get_group_of_quantity_for_product(),
     * also merges in category-level term meta, which is not wanted here.
     *
     * Variations store these under differently named, `variation_`-prefixed
     * meta keys instead of the plain ones used for simple/parent products.
     *
     * @param \WC_Product $product The product to check.
     * @return bool
     */
    private function is_customized_by_min_max_quantities(\WC_Product $product): bool
    {
        $meta_keys = $product instanceof \WC_Product_Variation ? array('variation_minimum_allowed_quantity', 'variation_maximum_allowed_quantity', 'variation_group_of_quantity') : array('minimum_allowed_quantity', 'maximum_allowed_quantity', 'group_of_quantity');
        foreach ($meta_keys as $meta_key) {
            if ('' !== $product->get_meta($meta_key, \true)) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * Checks whether the product is a WooCommerce Composite Products product.
     *
     * @param \WC_Product $product The product to check.
     * @return bool
     */
    private function is_customized_by_composite_products(\WC_Product $product): bool
    {
        return $product->is_type('composite');
    }
    /**
     * Checks whether the product has WooCommerce Per-Product Shipping enabled.
     *
     * Reads the `_per_product_shipping` meta directly. The plugin has no
     * product-only accessor either: its own global function
     * woocommerce_per_product_shipping_get_matching_rule() checks this same
     * meta key internally, but requires a shipping package/destination
     * context, so it cannot be used as a simple per-product boolean check.
     *
     * @param \WC_Product $product The product to check.
     * @return bool
     */
    private function is_customized_by_shipping_per_product(\WC_Product $product): bool
    {
        return 'yes' === $product->get_meta('_per_product_shipping', \true);
    }
    /**
     * Checks whether the product has WooCommerce Deposits enabled.
     *
     * @param \WC_Product $product The product to check.
     * @return bool
     */
    private function is_customized_by_deposits(\WC_Product $product): bool
    {
        $this->assert_method_exists(\WC_Deposits_Product_Manager::class, 'deposits_enabled');
        return \WC_Deposits_Product_Manager::deposits_enabled($product->get_id());
    }
}
