<?php

/**
 * Enriches a CartItem schema with resolved WooCommerce store data.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\StoreData
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\StoreData;

use WC_Product;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\CartHelper;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
class StoreCartItem
{
    private CartItem $schema_item;
    private WC_Product $product;
    private StoreCurrencyValue $store_currency;
    public function __construct(CartItem $schema_item, WC_Product $product, StoreCurrencyValue $store_currency)
    {
        $this->schema_item = $schema_item;
        $this->product = $product;
        $this->store_currency = $store_currency;
    }
    /**
     * The actual store price for this item, as a float.
     */
    public function real_price(): float
    {
        return (float) $this->product->get_price();
    }
    /**
     * The price the agent provided for this item, or null if no price was given.
     */
    public function assumed_price(): ?Money
    {
        return $this->schema_item->price();
    }
    /**
     * True when no assumed price was provided, or the assumed value matches the store price.
     *
     * Comparison is done on formatted decimals to avoid floating-point precision drift.
     */
    public function is_price_correct(): bool
    {
        $assumed = $this->assumed_price();
        if (null === $assumed) {
            return \true;
        }
        return CartHelper::format_decimal($this->real_price()) === CartHelper::format_decimal((float) $assumed->value());
    }
    /**
     * True when no assumed price was provided (no currency claim), or the assumed currency
     * matches the store currency.
     */
    public function is_currency_correct(): bool
    {
        $assumed = $this->assumed_price();
        if (null === $assumed) {
            return \true;
        }
        return $assumed->currency_code() === $this->store_currency->value();
    }
    /**
     * The raw schema item, for access to variant_id, quantity, name, etc.
     */
    public function schema(): CartItem
    {
        return $this->schema_item;
    }
}
