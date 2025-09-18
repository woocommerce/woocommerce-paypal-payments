<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Settings\Service;

use WooCommerce\PayPalCommerce\ApiClient\Helper\ReferenceTransactionStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;

class MerchantCapabilities {

	private ConnectionState $connection_state;
	private DCCProductStatus $dcc_product_status;
	private ReferenceTransactionStatus $reference_transaction_status;

	public function __construct(
		ConnectionState $connection_state,
		ReferenceTransactionStatus $reference_transaction_status,
		DCCProductStatus $dcc_product_status
	) {
		$this->connection_state             = $connection_state;
		$this->reference_transaction_status = $reference_transaction_status;
		$this->dcc_product_status           = $dcc_product_status;
	}

	public function can_save_paypal_methods(): bool {
		if ( $this->connection_state->is_connected() ) {
			return $this->reference_transaction_status->reference_transaction_enabled();
		}

		return false;
	}

	public function can_save_credit_cards(): bool {
		if ( $this->connection_state->is_connected() ) {
			return $this->dcc_product_status->is_active();
		}

		return false;
	}
}
