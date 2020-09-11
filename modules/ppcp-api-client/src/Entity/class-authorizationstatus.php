<?php
/**
 * The AuthorizationStatus object.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class AuthorizationStatus
 */
class AuthorizationStatus {

	public const INTERNAL           = 'INTERNAL';
	public const CREATED            = 'CREATED';
	public const CAPTURED           = 'CAPTURED';
	public const COMPLETED          = 'COMPLETED';
	public const DENIED             = 'DENIED';
	public const EXPIRED            = 'EXPIRED';
	public const PARTIALLY_CAPTURED = 'PARTIALLY_CAPTURED';
	public const VOIDED             = 'VOIDED';
	public const PENDING            = 'PENDING';
	public const VALID_STATUS       = array(
		self::INTERNAL,
		self::CREATED,
		self::CAPTURED,
		self::COMPLETED,
		self::DENIED,
		self::EXPIRED,
		self::PARTIALLY_CAPTURED,
		self::VOIDED,
		self::PENDING,
	);

	/**
	 * The status.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * AuthorizationStatus constructor.
	 *
	 * @param string $status The status.
	 * @throws RuntimeException When the status is not valid.
	 */
	public function __construct( string $status ) {
		if ( ! in_array( $status, self::VALID_STATUS, true ) ) {
			throw new RuntimeException(
				sprintf(
					// translators: %s is the current status.
					__( '%s is not a valid status', 'paypal-payments-for-woocommerce' ),
					$status
				)
			);
		}
		$this->status = $status;
	}

	/**
	 * Returns an AuthorizationStatus as Internal.
	 *
	 * @return AuthorizationStatus
	 */
	public static function as_internal(): AuthorizationStatus {
		return new self( self::INTERNAL );
	}

	/**
	 * Compares the current status with a given one.
	 *
	 * @param string $status The status to compare with.
	 *
	 * @return bool
	 */
	public function is( string $status ): bool {
		return $this->status === $status;
	}

	/**
	 * Returns the status.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->status;
	}
}
