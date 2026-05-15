<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ShippingOption;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money;
/**
 * ShippingRate object for the Store API.
 */
class ShippingRate
{
    private string $rate_id;
    private string $name;
    private bool $selected;
    private Money $price;
    private Money $taxes;
    public function __construct(string $rate_id, string $name, bool $selected, Money $price, Money $taxes)
    {
        $this->rate_id = $rate_id;
        $this->name = $name;
        $this->selected = $selected;
        $this->price = $price;
        $this->taxes = $taxes;
    }
    public function rate_id(): string
    {
        return $this->rate_id;
    }
    public function name(): string
    {
        return $this->name;
    }
    public function selected(): bool
    {
        return $this->selected;
    }
    public function price(): Money
    {
        return $this->price;
    }
    public function taxes(): Money
    {
        return $this->taxes;
    }
    /**
     * Returns the ShippingOption object for the PayPal API.
     */
    public function to_paypal(): ShippingOption
    {
        return new ShippingOption($this->rate_id, $this->name, $this->selected, $this->price->to_paypal(), ShippingOption::TYPE_SHIPPING);
    }
}
