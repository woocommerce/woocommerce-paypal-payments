<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class PaymentSourceCard
{

    private $lastDigits;
    private $brand;
    private $type;
    private $authenticationResult;
    public function __construct(
        string $lastDigits,
        string $brand,
        string $type,
        CardAuthenticationResult $authenticationResult = null
    ) {

        $this->lastDigits = $lastDigits;
        $this->brand = $brand;
        $this->type = $type;
        $this->authenticationResult = $authenticationResult;
    }

    public function lastDigits(): string
    {

        return $this->lastDigits;
    }

    public function brand(): string
    {

        return $this->brand;
    }

    public function type(): string
    {

        return $this->type;
    }

    public function authenticationResult(): ?CardAuthenticationResult
    {

        return $this->authenticationResult;
    }

    public function toArray(): array
    {

        $data = [
            'last_digits' => $this->lastDigits(),
            'brand' => $this->brand(),
            'type' => $this->type(),
        ];
        if ($this->authenticationResult()) {
            $data['authentication_result'] = $this->authenticationResult()->toArray();
        }
        return $data;
    }
}
