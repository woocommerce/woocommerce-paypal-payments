<?php

/**
 * Class MoneyFormatter.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

/**
 * Class MoneyFormatter
 */
class MoneyFormatter
{
    /**
     * Currencies that does not support decimals.
     *
     * @var array
     */
    private $currencies_without_decimals = array('HUF', 'JPY', 'TWD');
    /**
     * Returns the value formatted as string for API requests.
     *
     * @param float  $value The value.
     * @param string $currency The 3-letter currency code.
     *
     * @return string
     */
    public function format(float $value, string $currency): string
    {
        return in_array($currency, $this->currencies_without_decimals, \true) ? (string) round($value, 0) : number_format($value, 2, '.', '');
    }
    /**
     * Returns the minimum amount a currency can be incremented or decremented.
     *
     * @param string $currency The 3-letter currency code.
     * @return float
     */
    public function minimum_increment(string $currency): float
    {
        return (float) in_array($currency, $this->currencies_without_decimals, \true) ? 1.0 : 0.01;
    }
}
