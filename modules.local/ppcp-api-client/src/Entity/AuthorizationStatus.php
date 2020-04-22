<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class AuthorizationStatus
{
    const INTERNAL = 'INTERNAL';
    const CREATED = 'CREATED';
    const CAPTURED = 'CAPTURED';
    const COMPLETED = 'COMPLETED';
    const DENIED = 'DENIED';
    const EXPIRED = 'EXPIRED';
    const PARTIALLY_CAPTURED = 'PARTIALLY_CAPTURED';
    const VOIDED = 'VOIDED';
    const PENDING = 'PENDING';
    const VALID_STATUS = [
        self::INTERNAL,
        self::CREATED,
        self::CAPTURED,
        self::COMPLETED,
        self::DENIED,
        self::EXPIRED,
        self::PARTIALLY_CAPTURED,
        self::VOIDED,
        self::PENDING,
    ];
    private $status;

    public function __construct(string $status)
    {
        if (!in_array($status, self::VALID_STATUS, true)) {
            throw new RuntimeException(
                // translators: %s is the current status.
                sprintf(__("%s is not a valid status", 'woocmmerce-paypal-commerce-gateway'), $status)
            );
        }
        $this->status = $status;
    }

    public static function asInternal(): AuthorizationStatus
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
