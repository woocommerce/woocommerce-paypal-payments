<?php

/**
 * Factory for store-enriched cart objects.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\StoreData
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\StoreData;

use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCartBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
class StoreData
{
    private ProductManager $product_manager;
    private StoreCurrencyValue $store_currency;
    private AgenticCartBuilder $cart_builder;
    public function __construct(ProductManager $product_manager, StoreCurrencyValue $store_currency, AgenticCartBuilder $cart_builder)
    {
        $this->product_manager = $product_manager;
        $this->store_currency = $store_currency;
        $this->cart_builder = $cart_builder;
    }
    /**
     * Creates a StorePayPalCart with all cart items eagerly resolved against the WC product
     * catalog.
     *
     * Items whose product cannot be found in the store are silently omitted from the result.
     */
    public function create_cart(PayPalCart $cart, StoreValidation $validation): \WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart
    {
        $store_items = array();
        foreach ($cart->items() as $index => $item) {
            $product = $this->product_manager->find_product($item);
            if ($product !== null) {
                $store_items[] = new \WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem($index, $item, $product, $this->store_currency, $this->product_manager);
            }
        }
        return new \WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart($cart, $validation, $store_items, $this->cart_builder, $this->store_currency);
    }
}
