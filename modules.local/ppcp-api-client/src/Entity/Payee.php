<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

/**
 * Class Payee
 * The entity, which receives the money.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Entity
 */
class Payee
{

    private $email;
    private $merchantId;

    public function __construct(
        string $email,
        string $merchantId
    ) {

        $this->email = $email;
        $this->merchantId = $merchantId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function merchantId(): string
    {
        return $this->merchantId;
    }

    public function toArray(): array
    {
        $data = [
            'email_address' => $this->email(),
        ];
        if ($this->merchantId) {
            $data['merchant_id'] = $this->merchantId();
        }
        return $data;
    }
}
