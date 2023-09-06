<?php
/**
 * The RefundStatusDetails object.
 *
 * @see https://developer.paypal.com/docs/api/payments/v2/#definition-refund_status_details
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class RefundStatusDetails
 */
class RefundStatusDetails {

	const ECHECK = 'ECHECK';

	/**
	 * The reason.
	 *
	 * @var string
	 */
	private $reason;

	/**
	 * RefundStatusDetails constructor.
	 *
	 * @param string $reason The reason explaining refund status.
	 */
	public function __construct( string $reason ) {
		$this->reason = $reason;
	}

	/**
	 * Compares the current reason with a given one.
	 *
	 * @param string $reason The reason to compare with.
	 *
	 * @return bool
	 */
	public function is( string $reason ): bool {
		return $this->reason === $reason;
	}

	/**
	 * Returns the reason explaining refund status.
	 * One of RefundStatusDetails constants.
	 *
	 * @return string
	 */
	public function reason(): string {
		return $this->reason;
	}

	/**
	 * Returns the human-readable reason text explaining refund status.
	 *
	 * @return string
	 */
	public function text(): string {
		switch ( $this->reason ) {
			case self::ECHECK:
				return __( 'The payer paid by an eCheck that has not yet cleared.', 'woocommerce-paypal-payments' );
			default:
				return $this->reason;
		}
	}
}
