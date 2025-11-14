<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadataProvider;

class AuthServiceProvider {

	private ?JwtAuthService $instance = null;

	protected PayPalJwkProvider $jwk_provider;

	protected MerchantMetadataProvider $metadata_provider;

	public function __construct( PayPalJwkProvider $jwk_provider, MerchantMetadataProvider $metadata_provider ) {
		$this->jwk_provider      = $jwk_provider;
		$this->metadata_provider = $metadata_provider;
	}

	public function auth_service(): JwtAuthService {
		if ( ! $this->instance ) {
			$this->instance = $this->choose_auth_service();
		}

		return $this->instance;
	}

	private function choose_auth_service(): JwtAuthService {
		return new JwtAuthService( $this->jwk_provider, $this->metadata_provider );
	}
}
