<?php
/**
 * The CaptureStatus object.
 *
 * @see https://developer.paypal.com/docs/api/orders/v2/#definition-capture_status
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class CaptureStatus
 */
class CaptureStatus {

	const COMPLETED          = 'COMPLETED';
	const DECLINED           = 'DECLINED';
	const PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';
	const REFUNDED           = 'REFUNDED';
	const FAILED             = 'FAILED';
	const PENDING            = 'PENDING';

	/**
	 * The status.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * The details.
	 *
	 * @var CaptureStatusDetails|null
	 */
	private $details;

	/**
	 * CaptureStatus constructor.
	 *
	 * @param string                    $status The status.
	 * @param CaptureStatusDetails|null $details The details.
	 */
	public function __construct( string $status, ?CaptureStatusDetails $details = null ) {
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
	 * @return CaptureStatusDetails|null
	 */
	public function details(): ?CaptureStatusDetails {
		return $this->details;
	}
}
