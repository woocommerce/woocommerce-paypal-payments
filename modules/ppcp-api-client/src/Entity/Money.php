<?php

/**
 * The money object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Helper\MoneyFormatter;
/**
 * Class Money
 */
class Money
{
    /**
     * The currency code.
     *
     * @var string
     */
    private $currency_code;
    /**
     * The value.
     *
     * @var float
     */
    private $value;
    /**
     * The MoneyFormatter.
     *
     * @var MoneyFormatter
     */
    private $money_formatter;
    /**
     * Money constructor.
     *
     * @param float  $value The value.
     * @param string $currency_code The currency code.
     */
    public function __construct(float $value, string $currency_code)
    {
        $this->value = $value;
        $this->currency_code = $currency_code;
        $this->money_formatter = new MoneyFormatter();
    }
    /**
     * The value.
     *
     * @return float
     */
    public function value(): float
    {
        return $this->value;
    }
    /**
     * The value formatted as string for API requests.
     *
     * @return string
     */
    public function value_str(): string
    {
        return $this->money_formatter->format($this->value, $this->currency_code);
    }
    /**
     * The currency code.
     *
     * @return string
     */
    public function currency_code(): string
    {
        return $this->currency_code;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return array('currency_code' => $this->currency_code(), 'value' => $this->value_str());
    }
}
