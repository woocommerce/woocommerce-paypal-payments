<?php

/**
 * Factory for store-enriched schema objects.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\StoreData
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\StoreData;

use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
class StoreData
{
    private ProductManager $product_manager;
    private StoreCurrencyValue $store_currency;
    public function __construct(ProductManager $product_manager, StoreCurrencyValue $store_currency)
    {
        $this->product_manager = $product_manager;
        $this->store_currency = $store_currency;
    }
    /**
     * Creates a StoreCartItem by resolving the WC product for the given schema item.
     *
     * Returns null when no matching product exists in the store.
     */
    public function cart_item(CartItem $schema_item): ?\WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem
    {
        $product = $this->product_manager->find_product($schema_item);
        if (null === $product) {
            return null;
        }
        return new \WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem($schema_item, $product, $this->store_currency);
    }
}
