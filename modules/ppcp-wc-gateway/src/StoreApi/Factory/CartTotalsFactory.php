<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory;

use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\CartTotals;
/**
 * Factory for the Store API cart totals.
 */
class CartTotalsFactory
{
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\MoneyFactory $money_factory;
    public function __construct(\WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\MoneyFactory $money_factory)
    {
        $this->money_factory = $money_factory;
    }
    /**
     * Parses the 'totals' object from the cart response.
     */
    public function from_response_obj(array $obj): CartTotals
    {
        return new CartTotals($this->money_factory->from_response_values($obj, 'total_items'), $this->money_factory->from_response_values($obj, 'total_items_tax'), $this->money_factory->from_response_values($obj, 'total_fees'), $this->money_factory->from_response_values($obj, 'total_fees_tax'), $this->money_factory->from_response_values($obj, 'total_discount'), $this->money_factory->from_response_values($obj, 'total_discount_tax'), $this->money_factory->from_response_values($obj, 'total_shipping'), $this->money_factory->from_response_values($obj, 'total_shipping_tax'), $this->money_factory->from_response_values($obj, 'total_price'), $this->money_factory->from_response_values($obj, 'total_tax'));
    }
}
