<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity;

/**
 * CartTotals object for the Store API.
 */
class CartTotals
{
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_items;
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_items_tax;
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_fees;
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_fees_tax;
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_discount;
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_discount_tax;
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_shipping;
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_shipping_tax;
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_price;
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_tax;
    public function __construct(\WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_items, \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_items_tax, \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_fees, \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_fees_tax, \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_discount, \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_discount_tax, \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_shipping, \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_shipping_tax, \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_price, \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money $total_tax)
    {
        $this->total_items = $total_items;
        $this->total_items_tax = $total_items_tax;
        $this->total_fees = $total_fees;
        $this->total_fees_tax = $total_fees_tax;
        $this->total_discount = $total_discount;
        $this->total_discount_tax = $total_discount_tax;
        $this->total_shipping = $total_shipping;
        $this->total_shipping_tax = $total_shipping_tax;
        $this->total_price = $total_price;
        $this->total_tax = $total_tax;
    }
    public function total_items(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money
    {
        return $this->total_items;
    }
    public function total_items_tax(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money
    {
        return $this->total_items_tax;
    }
    public function total_fees(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money
    {
        return $this->total_fees;
    }
    public function total_fees_tax(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money
    {
        return $this->total_fees_tax;
    }
    public function total_discount(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money
    {
        return $this->total_discount;
    }
    public function total_discount_tax(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money
    {
        return $this->total_discount_tax;
    }
    public function total_shipping(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money
    {
        return $this->total_shipping;
    }
    public function total_shipping_tax(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money
    {
        return $this->total_shipping_tax;
    }
    public function total_price(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money
    {
        return $this->total_price;
    }
    public function total_tax(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money
    {
        return $this->total_tax;
    }
}
