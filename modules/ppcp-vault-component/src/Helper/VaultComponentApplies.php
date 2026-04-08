<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\VaultComponent\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Helper\ReferenceTransactionStatus;

class VaultComponentApplies {

	private array $allowed_countries;
	private string $country;
	private ReferenceTransactionStatus $reference_transaction_status;

	public function __construct(
		array $allowed_countries,
		string $country,
		ReferenceTransactionStatus $reference_transaction_status
	) {
		$this->allowed_countries            = $allowed_countries;
		$this->country                      = $country;
		$this->reference_transaction_status = $reference_transaction_status;
	}

	public function for_country(): bool {
		return in_array( $this->country, $this->allowed_countries, true );
	}

	/**
	 * Checks PAYPAL_WALLET_VAULTING_ADVANCED capability.
	 */
	public function for_merchant(): bool {
		return $this->reference_transaction_status->reference_transaction_enabled();
	}
}
