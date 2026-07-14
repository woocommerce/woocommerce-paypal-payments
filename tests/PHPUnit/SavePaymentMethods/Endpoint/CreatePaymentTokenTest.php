<?php

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint;

use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcPaymentTokens\WooCommercePaymentTokens;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class CreatePaymentTokenTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/** @var RequestData&\Mockery\MockInterface */
	private $request_data;

	/** @var PaymentMethodTokensEndpoint&\Mockery\MockInterface */
	private $tokens_endpoint;

	/** @var WooCommercePaymentTokens&\Mockery\MockInterface */
	private $wc_payment_tokens;

	/** @var CreatePaymentToken */
	private $sut;

	/**
	 * Real captured exchange (VISA card vault token response from PayPal sandbox).
	 */
	private function card_result_fixture(): \stdClass {
		return json_decode( '{"id":"72k37380cm2997353","customer":{"id":"LYraJLIEfz"},"payment_source":{"card":{"name":"Frictionless","last_digits":"9038","brand":"VISA","expiry":"2029-01","verification_status":"VERIFIED","verification":{"network_transaction_id":"841006768893514","three_d_secure":{"eci_flag":"FULLY_AUTHENTICATED_TRANSACTION","cavv":"AAABAWFlmQAAAABjRWWZEEFgFz+=","enrolled":"Y","pares_status":"Y","three_ds_version":"2.2.0"}},"authentication_result":{"three_d_secure":{"authentication_status":"Y","enrollment_status":"Y","authentication_id":"22674300069036246"}}}},"links":[{"href":"https://api.sandbox.paypal.com/v3/vault/payment-tokens/72k37380cm2997353","rel":"self","method":"GET"}]}' );
	}

	/**
	 * A minimal PayPal result that carries a paypal payment_source.
	 */
	private function paypal_result_fixture( string $email = 'buyer@paypal.com' ): \stdClass {
		return (object) array(
			'id'             => 'paypal-token-id-999',
			'customer'       => (object) array( 'id' => 'CustPayPal01' ),
			'payment_source' => (object) array(
				'paypal' => (object) array( 'email_address' => $email ),
			),
		);
	}

	public function setUp(): void {
		parent::setUp();

		$this->request_data      = Mockery::mock( RequestData::class );
		$this->tokens_endpoint   = Mockery::mock( PaymentMethodTokensEndpoint::class );
		$this->wc_payment_tokens = Mockery::mock( WooCommercePaymentTokens::class );

		$this->sut = new CreatePaymentToken(
			$this->request_data,
			$this->tokens_endpoint,
			$this->wc_payment_tokens
		);

		// Common WP stubs.
		when( 'get_current_user_id' )->justReturn( 42 );
		when( 'get_user_meta' )->justReturn( 'cust-existing' );
		when( 'is_user_logged_in' )->justReturn( true );
	}

	// -------------------------------------------------------------------------
	// Tests
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a logged-in user and create_payment_token() returns a card vault result
	 * WHEN handle_request() is called with a vault_setup_token
	 * THEN create_payment_token_card() is called with the current user id and result
	 * AND update_user_meta() persists the PayPal customer id from the result
	 * AND wp_send_json_success() receives the WC token id returned by create_payment_token_card()
	 */
	public function test_card_success_calls_create_card_token_and_sends_wc_token_id(): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->once()
			->with( CreatePaymentToken::ENDPOINT )
			->andReturn( array( 'vault_setup_token' => 'setup-tok-001' ) );

		$fixture = $this->card_result_fixture();

		$this->tokens_endpoint
			->shouldReceive( 'create_payment_token' )
			->once()
			->andReturn( $fixture );

		$this->wc_payment_tokens
			->shouldReceive( 'create_payment_token_card' )
			->once()
			->with( 42, $fixture )
			->andReturn( 4242 );

		$this->wc_payment_tokens->shouldNotReceive( 'create_payment_token_paypal' );

		// Capture the value sent to wp_send_json_success.
		$sent_value = null;
		expect( 'wp_send_json_success' )
			->once()
			->andReturnUsing( function ( $value ) use ( &$sent_value ): void {
				$sent_value = $value;
			} );

		// update_user_meta must be called with the customer id from the result.
		expect( 'update_user_meta' )
			->once()
			->with( 42, '_ppcp_target_customer_id', 'LYraJLIEfz' );

		$this->sut->handle_request();

		$this->assertSame( 4242, $sent_value, 'wp_send_json_success should receive the WC token id from create_payment_token_card()' );
	}

	/**
	 * GIVEN a logged-in user and create_payment_token() returns a PayPal vault result
	 * WHEN handle_request() is called
	 * THEN create_payment_token_paypal() is called with user id, result id, and email
	 * AND create_payment_token_card() is NOT called
	 * AND wp_send_json_success() receives the WC token id returned by create_payment_token_paypal()
	 */
	public function test_paypal_success_calls_create_paypal_token_not_card(): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->once()
			->andReturn( array( 'vault_setup_token' => 'setup-tok-002' ) );

		$fixture = $this->paypal_result_fixture( 'buyer@paypal.com' );

		$this->tokens_endpoint
			->shouldReceive( 'create_payment_token' )
			->once()
			->andReturn( $fixture );

		$this->wc_payment_tokens
			->shouldReceive( 'create_payment_token_paypal' )
			->once()
			->with( 42, 'paypal-token-id-999', 'buyer@paypal.com' )
			->andReturn( 7777 );

		$this->wc_payment_tokens->shouldNotReceive( 'create_payment_token_card' );

		$sent_value = null;
		expect( 'wp_send_json_success' )
			->once()
			->andReturnUsing( function ( $value ) use ( &$sent_value ): void {
				$sent_value = $value;
			} );

		expect( 'update_user_meta' )
			->once()
			->with( 42, '_ppcp_target_customer_id', 'CustPayPal01' );

		$this->sut->handle_request();

		$this->assertSame( 7777, $sent_value );
	}

	/**
	 * GIVEN read_request() throws a NonceValidationException
	 * WHEN handle_request() is called
	 * THEN wp_send_json_error() is called with a message array and HTTP 400
	 * AND no token is persisted
	 */
	public function test_nonce_failure_returns_400_and_no_token_persisted(): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->once()
			->andThrow( new NonceValidationException( 'Invalid nonce.' ) );

		$this->tokens_endpoint->shouldNotReceive( 'create_payment_token' );
		$this->wc_payment_tokens->shouldNotReceive( 'create_payment_token_card' );
		$this->wc_payment_tokens->shouldNotReceive( 'create_payment_token_paypal' );

		$error_data   = null;
		$error_status = null;
		expect( 'wp_send_json_error' )
			->once()
			->andReturnUsing( function ( $data, $status ) use ( &$error_data, &$error_status ): void {
				$error_data   = $data;
				$error_status = $status;
			} );

		$this->sut->handle_request();

		$this->assertIsArray( $error_data );
		$this->assertArrayHasKey( 'message', $error_data );
		$this->assertSame( 400, $error_status );
	}

	/**
	 * GIVEN create_payment_token() throws a PayPalApiException
	 * WHEN handle_request() is called
	 * THEN wp_send_json_error() is called with no arguments
	 * AND no WC token is persisted
	 */
	public function test_api_exception_calls_wp_send_json_error_with_no_args(): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->once()
			->andReturn( array( 'vault_setup_token' => 'setup-tok-003' ) );

		$this->tokens_endpoint
			->shouldReceive( 'create_payment_token' )
			->once()
			->andThrow( new PayPalApiException() );

		$this->wc_payment_tokens->shouldNotReceive( 'create_payment_token_card' );
		$this->wc_payment_tokens->shouldNotReceive( 'create_payment_token_paypal' );

		expect( 'wp_send_json_error' )->once()->withNoArgs();

		$this->sut->handle_request();
	}
}
