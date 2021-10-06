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

	const BUYER_COMPLAINT                             = 'BUYER_COMPLAINT';
	const CHARGEBACK                                  = 'CHARGEBACK';
	const ECHECK                                      = 'ECHECK';
	const INTERNATIONAL_WITHDRAWAL                    = 'INTERNATIONAL_WITHDRAWAL';
	const OTHER                                       = 'OTHER';
	const PENDING_REVIEW                              = 'PENDING_REVIEW';
	const RECEIVING_PREFERENCE_MANDATES_MANUAL_ACTION = 'RECEIVING_PREFERENCE_MANDATES_MANUAL_ACTION';
	const REFUNDED                                    = 'REFUNDED';
	const TRANSACTION_APPROVED_AWAITING_FUNDING       = 'TRANSACTION_APPROVED_AWAITING_FUNDING';
	const UNILATERAL                                  = 'REFUNDED';
	const VERIFICATION_REQUIRED                       = 'VERIFICATION_REQUIRED';

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
	 *
	 * @return string
	 */
	public function reason(): string {
		return $this->reason;
	}
}
