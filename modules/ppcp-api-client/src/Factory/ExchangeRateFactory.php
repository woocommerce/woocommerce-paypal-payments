<?php

/**
 * The ExchangeRateFactory Factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExchangeRate;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
/**
 * Class ExchangeRateFactory
 */
class ExchangeRateFactory
{
    /**
     * Returns an ExchangeRate object based off a PayPal Response.
     *
     * @param stdClass $data The JSON object.
     *
     * @return ExchangeRate|null
     * @throws RuntimeException When JSON object is malformed.
     */
    public function from_paypal_response(stdClass $data): ?ExchangeRate
    {
        // Looks like all fields in this object are optional, according to the docs,
        // and sometimes we get an empty object.
        $source_currency = $data->source_currency ?? '';
        $target_currency = $data->target_currency ?? '';
        $value = $data->value ?? '';
        if (!$source_currency && !$target_currency && !$value) {
            // Do not return empty object.
            return null;
        }
        return new ExchangeRate($source_currency, $target_currency, $value);
    }
}
