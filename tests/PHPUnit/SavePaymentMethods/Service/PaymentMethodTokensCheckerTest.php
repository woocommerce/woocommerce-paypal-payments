<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Service;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;

class PaymentMethodTokensCheckerTest extends TestCase {

	public function test_handle_exception_when_resource_not_found() {
		$endpoint = mockery::mock( PaymentTokensEndpoint::class );
		$endpoint
			->expects( 'payment_tokens_for_customer' )
			->andThrow( new PayPalApiException() );

		$checker = new PaymentMethodTokensChecker( $endpoint );

		$this->assertFalse( $checker->has_paypal_payment_token( 'abc123' ) );
	}

	public function test_handle_runtime_exception_when_network_error() {
		$endpoint = mockery::mock( PaymentTokensEndpoint::class );
		$endpoint
			->expects( 'payment_tokens_for_customer' )
			->andThrow( new RuntimeException() );

		$checker = new PaymentMethodTokensChecker( $endpoint );

		$this->assertFalse( $checker->has_paypal_payment_token( 'abc123' ) );
	}
}
