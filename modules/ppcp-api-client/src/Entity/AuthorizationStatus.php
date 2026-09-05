<?php

/**
 * The AuthorizationStatus object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
/**
 * Class AuthorizationStatus
 */
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
    const VALID_STATUS = array(self::INTERNAL, self::CREATED, self::CAPTURED, self::COMPLETED, self::DENIED, self::EXPIRED, self::PARTIALLY_CAPTURED, self::VOIDED, self::PENDING);
    /**
     * The status.
     *
     * @var string
     */
    private $status;
    /**
     * The details.
     *
     * @var AuthorizationStatusDetails|null
     */
    private $details;
    /**
     * AuthorizationStatus constructor.
     *
     * @param string                          $status The status.
     * @param AuthorizationStatusDetails|null $details The details.
     * @throws RuntimeException When the status is not valid.
     */
    public function __construct(string $status, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatusDetails $details = null)
    {
        if (!in_array($status, self::VALID_STATUS, \true)) {
            throw new RuntimeException(sprintf('%s is not a valid status', $status));
        }
        $this->status = $status;
        $this->details = $details;
    }
    /**
     * Returns an AuthorizationStatus as Internal.
     *
     * @return AuthorizationStatus
     */
    public static function as_internal(): \WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus
    {
        return new self(self::INTERNAL);
    }
    /**
     * Compares the current status with a given one.
     *
     * @param string $status The status to compare with.
     *
     * @return bool
     */
    public function is(string $status): bool
    {
        return $this->status === $status;
    }
    /**
     * Returns the status.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->status;
    }
    /**
     * Returns the details.
     *
     * @return AuthorizationStatusDetails|null
     */
    public function details(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatusDetails
    {
        return $this->details;
    }
}
