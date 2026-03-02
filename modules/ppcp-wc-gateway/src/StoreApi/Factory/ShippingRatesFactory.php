<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory;

/**
 * Factory for the Store API shipping rates.
 */
class ShippingRatesFactory
{
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\MoneyFactory $money_factory;
    public function __construct(\WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\MoneyFactory $money_factory)
    {
        $this->money_factory = $money_factory;
    }
    /**
     * Extracts shipping rates from the 'shipping_rates' object in the cart response.
     */
    public function from_response_obj(array $obj): array
    {
        $rates = array();
        foreach ($obj as $package) {
            foreach ($package['shipping_rates'] as $item) {
                $rates[] = $this->parse_shipping_rate($item);
            }
        }
        return $rates;
    }
    private function parse_shipping_rate(array $obj): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\ShippingRate
    {
        return new \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\ShippingRate($obj['rate_id'], $obj['name'], $obj['selected'], $this->money_factory->from_response_values($obj, 'price'), $this->money_factory->from_response_values($obj, 'taxes'));
    }
}
