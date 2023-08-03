<?php
/**
 * The RefundStatus object.
 *
 * @see https://developer.paypal.com/docs/api/orders/v2/#definition-refund_status
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class RefundStatus
 */
class RefundStatus {

	const COMPLETED          = 'COMPLETED';
	const CANCELLED          = 'CANCELLED';
	const FAILED             = 'FAILED';
	const PENDING            = 'PENDING';

	/**
	 * The status.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * RefundStatus constructor.
	 *
	 * @param string $status The status.
	 */
	public function __construct( string $status ) {
		$this->status  = $status;
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
