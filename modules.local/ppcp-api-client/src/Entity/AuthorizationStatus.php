<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class AuthorizationStatus
{
    public const INTERNAL = 'INTERNAL';
    public const CREATED = 'CREATED';
    public const CAPTURED = 'CAPTURED';
    public const COMPLETED = 'COMPLETED';
    public const DENIED = 'DENIED';
    public const EXPIRED = 'EXPIRED';
    public const PARTIALLY_CAPTURED = 'PARTIALLY_CAPTURED';
    public const VOIDED = 'VOIDED';
    public const PENDING = 'PENDING';
    public const VALID_STATUS = [
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
                sprintf(
                    // translators: %s is the current status.
                    __("%s is not a valid status", 'woocmmerce-paypal-commerce-gateway'),
                    $status
                )
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
