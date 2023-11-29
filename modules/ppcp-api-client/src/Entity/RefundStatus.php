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

	const COMPLETED = 'COMPLETED';
	const CANCELLED = 'CANCELLED';
	const FAILED    = 'FAILED';
	const PENDING   = 'PENDING';

	/**
	 * The status.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * The details.
	 *
	 * @var RefundStatusDetails|null
	 */
	private $details;

	/**
	 * RefundStatus constructor.
	 *
	 * @param string                   $status The status.
	 * @param RefundStatusDetails|null $details The details.
	 */
	public function __construct( string $status, ?RefundStatusDetails $details = null ) {
		$this->status  = $status;
		$this->details = $details;
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

	/**
	 * Returns the details.
	 *
	 * @return RefundStatusDetails|null
	 */
	public function details(): ?RefundStatusDetails {
		return $this->details;
	}
}
