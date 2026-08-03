<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity;

/**
 * Money value for the Store API.
 */
class Money
{
    private string $value;
    private string $currency_code;
    private int $currency_minor_unit;
    /**
     * @param string $value
     * @param string $currency_code
     * @param int    $currency_minor_unit
     */
    public function __construct(string $value, string $currency_code, int $currency_minor_unit)
    {
        $this->value = $value;
        $this->currency_code = $currency_code;
        $this->currency_minor_unit = $currency_minor_unit;
    }
    public function value(): string
    {
        return $this->value;
    }
    public function currency_code(): string
    {
        return $this->currency_code;
    }
    /**
     * The number of digits after ".". For most currencies it is 2.
     */
    public function currency_minor_unit(): int
    {
        return $this->currency_minor_unit;
    }
    /**
     * Converts to float, e.g. value=123, currency_minor_unit=2 --> 1.23.
     */
    public function to_float(): float
    {
        return round((int) $this->value / 10 ** $this->currency_minor_unit, $this->currency_minor_unit);
    }
    /**
     * Returns the Money object for the PayPal API.
     */
    public function to_paypal(): \WooCommerce\PayPalCommerce\ApiClient\Entity\Money
    {
        return new \WooCommerce\PayPalCommerce\ApiClient\Entity\Money($this->to_float(), $this->currency_code);
    }
}
