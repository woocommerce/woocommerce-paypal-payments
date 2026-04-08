<?php
/**
 * Properties of the Vault Component module.
 *
 * @package WooCommerce\PayPalCommerce\VaultComponent\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\VaultComponent\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Helper\ReferenceTransactionStatus;

/**
 * Class VaultComponentApplies
 *
 * Determines merchant-level eligibility for the Vault Component feature.
 */
class VaultComponentApplies {

	/**
	 * The countries where Vault Component is supported.
	 *
	 * @var array
	 */
	private array $allowed_countries;

	/**
	 * 2-letter country code of the shop.
	 *
	 * @var string
	 */
	private string $country;

	/**
	 * Reference transaction status checker.
	 *
	 * @var ReferenceTransactionStatus
	 */
	private ReferenceTransactionStatus $reference_transaction_status;

	/**
	 * VaultComponentApplies constructor.
	 *
	 * @param array                      $allowed_countries The countries where Vault Component is supported.
	 * @param string                     $country 2-letter country code of the shop.
	 * @param ReferenceTransactionStatus $reference_transaction_status Reference transaction status checker.
	 */
	public function __construct(
		array $allowed_countries,
		string $country,
		ReferenceTransactionStatus $reference_transaction_status
	) {
		$this->allowed_countries            = $allowed_countries;
		$this->country                      = $country;
		$this->reference_transaction_status = $reference_transaction_status;
	}

	/**
	 * Returns whether the Vault Component is available in the merchant's country.
	 *
	 * @return bool
	 */
	public function for_country(): bool {
		return in_array( $this->country, $this->allowed_countries, true );
	}

	/**
	 * Returns whether the merchant has the required vault capability (PAYPAL_WALLET_VAULTING_ADVANCED).
	 *
	 * @return bool
	 */
	public function for_merchant(): bool {
		return $this->reference_transaction_status->reference_transaction_enabled();
	}
}
