<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class Amount
{

    private $money;
    private $breakdown;

    public function __construct(Money $money, AmountBreakdown $breakdown = null)
    {
        $this->money = $money;
        $this->breakdown = $breakdown;
    }

    public function currencyCode(): string
    {
        return $this->money->currencyCode();
    }

    public function value(): float
    {
        return $this->money->value();
    }

    public function breakdown(): ?AmountBreakdown
    {
        return $this->breakdown;
    }

    public function toArray(): array
    {
        $amount = [
            'currency_code' => $this->currencyCode(),
            'value' => $this->value(),
        ];
        if ($this->breakdown() && count($this->breakdown()->toArray())) {
            $amount['breakdown'] = $this->breakdown()->toArray();
        }
        return $amount;
    }
}
