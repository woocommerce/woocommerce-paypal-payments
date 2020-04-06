<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


class Money
{

    private $currencyCode;
    private $value;
    public function __construct(float $value, string $currencyCode)
    {
        $this->value = $value;
        $this->currencyCode = $currencyCode;
    }

    public function value() : float
    {
        return $this->value;
    }

    public function currencyCode() : string
    {
        return $this->currencyCode;
    }

    public function toArray() : array {
        return [
            'currency_code' => $this->currencyCode(),
            'value' => $this->value(),
        ];
    }
}