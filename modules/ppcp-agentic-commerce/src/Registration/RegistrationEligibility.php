<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Registration;

use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadataProvider;

class RegistrationEligibility {

	private MerchantMetadataProvider $metadata_provider;

	public function __construct( MerchantMetadataProvider $metadata_provider ) {
		$this->metadata_provider = $metadata_provider;
	}

	public function is_eligible(): bool {
		$merchant = $this->metadata_provider->get_metadata();

		if ( ! $merchant->paypal_merchant_id ) {
			return false;
		}

		$country = strtoupper( trim( $merchant->country ) );

		return 'US' === $country;
	}
}
