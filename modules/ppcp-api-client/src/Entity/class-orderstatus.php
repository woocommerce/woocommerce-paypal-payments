<?php
/**
 * The OrderStatus object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class OrderStatus
 */
class OrderStatus {


	const INTERNAL    = 'INTERNAL';
	const CREATED     = 'CREATED';
	const SAVED       = 'SAVED';
	const APPROVED    = 'APPROVED';
	const VOIDED      = 'VOIDED';
	const COMPLETED   = 'COMPLETED';
	const VALID_STATI = array(
		self::INTERNAL,
		self::CREATED,
		self::SAVED,
		self::APPROVED,
		self::VOIDED,
		self::COMPLETED,
	);

	/**
	 * The status.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * OrderStatus constructor.
	 *
	 * @param string $status The status.
	 * @throws RuntimeException When the status is not valid.
	 */
	public function __construct( string $status ) {
		if ( ! in_array( $status, self::VALID_STATI, true ) ) {
			throw new RuntimeException(
				sprintf(
					// translators: %s is the current status.
					__( '%s is not a valid status', 'woocommerce-paypal-payments' ),
					$status
				)
			);
		}
		$this->status = $status;
	}

	/**
	 * Creates an OrderStatus "Internal"
	 *
	 * @return OrderStatus
	 */
	public static function as_internal(): OrderStatus {
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
