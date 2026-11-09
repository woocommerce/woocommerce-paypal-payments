<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity;

use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\ShippingRate;
/**
 * Cart object for the Store API.
 */
class Cart
{
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\CartTotals $totals;
    /**
     * @var ShippingRate[]
     */
    private array $shipping_rates;
    /**
     * @param CartTotals     $totals
     * @param ShippingRate[] $shipping_rates
     */
    public function __construct(\WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\CartTotals $totals, array $shipping_rates)
    {
        $this->totals = $totals;
        $this->shipping_rates = $shipping_rates;
    }
    public function totals(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\CartTotals
    {
        return $this->totals;
    }
    public function shipping_rates(): array
    {
        return $this->shipping_rates;
    }
}
