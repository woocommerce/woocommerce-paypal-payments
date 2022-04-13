<?php
/**
 * The links from CUSTOMER_ACTION_REQUIRED v2/vault/payment-tokens response.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class PaymentTokenActionLinks
 */
class PaymentTokenActionLinks {
	/**
	 * The URL for customer PayPal hosted contingency flow.
	 *
	 * @var string
	 */
	private $approve_link;

	/**
	 * The URL for a POST request to save an approved approval token and vault the underlying instrument.
	 *
	 * @var string
	 */
	private $confirm_link;

	/**
	 * The URL for a GET request to get the state of the approval token.
	 *
	 * @var string
	 */
	private $status_link;

	/**
	 * PaymentTokenActionLinks constructor.
	 *
	 * @param string $approve_link The URL for customer PayPal hosted contingency flow.
	 * @param string $confirm_link The URL for a POST request to save an approved approval token and vault the underlying instrument.
	 * @param string $status_link The URL for a GET request to get the state of the approval token.
	 */
	public function __construct( string $approve_link, string $confirm_link, string $status_link ) {
		$this->approve_link = $approve_link;
		$this->confirm_link = $confirm_link;
		$this->status_link  = $status_link;
	}

	/**
	 * Returns the URL for customer PayPal hosted contingency flow.
	 *
	 * @return string
	 */
	public function approve_link(): string {
		return $this->approve_link;
	}

	/**
	 * Returns the URL for a POST request to save an approved approval token and vault the underlying instrument.
	 *
	 * @return string
	 */
	public function confirm_link(): string {
		return $this->confirm_link;
	}

	/**
	 * Returns the URL for a GET request to get the state of the approval token.
	 *
	 * @return string
	 */
	public function status_link(): string {
		return $this->status_link;
	}
}
