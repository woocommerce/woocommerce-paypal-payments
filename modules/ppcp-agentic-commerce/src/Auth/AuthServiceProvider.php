<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadataProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;

class AuthServiceProvider {

	private ?JwtAuthService $instance = null;

	private ConnectionState $connection_state;

	private PayPalJwkProvider $jwk_provider;

	private MerchantMetadataProvider $metadata_provider;

	public function __construct( ConnectionState $connection_state, PayPalJwkProvider $jwk_provider, MerchantMetadataProvider $metadata_provider ) {
		$this->connection_state  = $connection_state;
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
