<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class OrderStatus
{

    public const INTERNAL = 'INTERNAL';
    public const CREATED = 'CREATED';
    public const SAVED = 'SAVED';
    public const APPROVED = 'APPROVED';
    public const VOIDED = 'VOIDED';
    public const COMPLETED = 'COMPLETED';
    public const VALID_STATI = [
        self::INTERNAL,
        self::CREATED,
        self::SAVED,
        self::APPROVED,
        self::VOIDED,
        self::COMPLETED,
    ];
    private $status;

    public function __construct(string $status)
    {
        if (! in_array($status, self::VALID_STATI, true)) {
            throw new RuntimeException(sprintf(
                // translators: %s is the current status.
                __("%s is not a valid status", "woocmmerce-paypal-commerce-gateway"),
                $status
            ));
        }
        $this->status = $status;
    }

    public static function asInternal(): OrderStatus
    {
        return new self(self::INTERNAL);
    }

    public function is(string $status): bool
    {
        return $this->status === $status;
    }

    public function name(): string
    {
        return $this->status;
    }
}
