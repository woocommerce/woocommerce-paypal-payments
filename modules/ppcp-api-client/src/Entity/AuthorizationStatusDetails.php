<?php
/**
 * The AuthorizationStatusDetails object.
 *
 * @see https://developer.paypal.com/docs/api/payments/v2/#definition-authorization_status_details
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class AuthorizationStatusDetails
 */
class AuthorizationStatusDetails {

	const PENDING_REVIEW = 'PENDING_REVIEW';

	/**
	 * The reason.
	 *
	 * @var string
	 */
	private $reason;

	/**
	 * AuthorizationStatusDetails constructor.
	 *
	 * @param string $reason The reason explaining authorization status.
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
	 * Returns the reason explaining authorization status.
	 * One of AuthorizationStatusDetails constants.
	 *
	 * @return string
	 */
	public function reason(): string {
		return $this->reason;
	}

	/**
	 * Returns the human-readable reason text explaining authorization status.
	 *
	 * @return string
	 */
	public function text(): string {
		switch ( $this->reason ) {
			case self::PENDING_REVIEW:
				return __( 'Authorization is pending manual review.', 'woocommerce-paypal-payments' );
			default:
				return $this->reason;
		}
	}
}
