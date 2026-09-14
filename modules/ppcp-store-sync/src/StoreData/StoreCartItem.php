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
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
class StoreCartItem
{
    private int $index;
    private CartItem $paypal_item;
    private WC_Product $product;
    private StoreCurrencyValue $store_currency;
    private ProductManager $product_manager;
    public function __construct(int $index, CartItem $schema_item, WC_Product $product, StoreCurrencyValue $store_currency, ProductManager $product_manager)
    {
        $this->index = $index;
        $this->paypal_item = $schema_item;
        $this->product = $product;
        $this->store_currency = $store_currency;
        $this->product_manager = $product_manager;
    }
    /**
     * The raw schema item, for access to variant_id, quantity, name, etc.
     */
    public function paypal_item(): CartItem
    {
        return $this->paypal_item;
    }
    public function product(): WC_Product
    {
        return $this->product;
    }
    /**
     * The unique identifier of the product variant (color, size, etc.)
     */
    public function id(): string
    {
        $assumed_id = $this->paypal_item->variant_id();
        if ($assumed_id) {
            return $assumed_id;
        }
        return (string) $this->product->get_id();
    }
    /**
     * Path (in dot-notation) to the current field.
     */
    public function field_path(string $child_path = ''): string
    {
        $child_path = trim($child_path, '.');
        return "items[{$this->index}]" . ($child_path ? ".{$child_path}" : '');
    }
    /**
     * The actual store price for this item, as a float.
     */
    public function real_price(): float
    {
        return (float) wc_get_price_excluding_tax($this->product);
    }
    public function real_price_as_money(): Money
    {
        return Money::create($this->real_price(), $this->store_currency->value());
    }
    /**
     * The price the agent provided for this item, or null if no price was given.
     */
    public function assumed_price_as_money(): ?Money
    {
        return $this->paypal_item->price();
    }
    /**
     * Currency that was assumed by agent, empty string is no assumption.
     */
    public function assumed_currency(): string
    {
        $assumed = $this->assumed_price_as_money();
        if (null === $assumed) {
            return '';
        }
        return (string) $assumed->currency_code('');
    }
    /**
     * True when no assumed price was provided, or the assumed value matches the store price.
     *
     * Comparison is done on formatted decimals to avoid floating-point precision drift.
     */
    public function is_price_correct(): bool
    {
        $assumed = $this->assumed_price_as_money();
        if (null === $assumed) {
            return \true;
        }
        return $this->real_price_as_money()->to_decimal() === $assumed->to_decimal();
    }
    /**
     * True when no assumed price was provided (no currency claim), or the assumed currency
     * matches the store currency.
     */
    public function is_currency_correct(): bool
    {
        $assumed = $this->assumed_currency();
        if (!$assumed) {
            return \true;
        }
        return $assumed === $this->store_currency->value();
    }
    /**
     * Quantity of items in cart, always provided by agent.
     */
    public function quantity(): int
    {
        return $this->paypal_item->quantity();
    }
    public function to_array(): array
    {
        $data = $this->paypal_item->to_array();
        // WooCommerce always provides the price and product name/description.
        $data['price'] = $this->real_price_as_money()->to_array();
        $data['name'] = $this->product_manager->get_product_title($this->product);
        $description = $this->product_manager->get_product_description($this->product, $this->product->get_short_description());
        if ($description) {
            $data['description'] = $description;
        } else {
            unset($data['description']);
        }
        $parent_id = $this->product->get_parent_id();
        if ($parent_id) {
            $data['parent_id'] = (string) $parent_id;
        } else {
            unset($data['parent_id']);
        }
        return $data;
    }
}
