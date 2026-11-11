<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory;

use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money;
/**
 * Factory for the Store API money values.
 */
class MoneyFactory
{
    /**
     * Parses the specified money property from a Store API response object
     * containing fields like currency_minor_unit.
     *
     * @param array  $obj The object.
     * @param string $property_name The property name with the money value.
     */
    public function from_response_values(array $obj, string $property_name): Money
    {
        $value = (string) $obj[$property_name];
        $currency_code = (string) ($obj['currency_code'] ?? '');
        $currency_minor_unit = (int) ($obj['currency_minor_unit'] ?? 2);
        return new Money($value, $currency_code, $currency_minor_unit);
    }
}
