<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class PaymentToken
{

    public const TYPE_PAYMENT_METHOD_TOKEN = 'PAYMENT_METHOD_TOKEN';
    public const VALID_TYPES = [
        self::TYPE_PAYMENT_METHOD_TOKEN,
    ];

    private $id;
    private $type;
    public function __construct(string $id, string $type = self::TYPE_PAYMENT_METHOD_TOKEN)
    {
        if (! in_array($type, self::VALID_TYPES, true)) {
            throw new RuntimeException(
                __("Not a valid payment source type.", "woocommerce-paypal-commerce-gateway")
            );
        }
        $this->id = $id;
        $this->type = $type;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'type' => $this->type(),
        ];
    }
}
