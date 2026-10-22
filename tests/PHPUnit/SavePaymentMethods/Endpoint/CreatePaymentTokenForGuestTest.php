<?php

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Service\SetupTokenBinding;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class CreatePaymentTokenForGuestTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/** @var RequestData&\Mockery\MockInterface */
	private $request_data;

	/** @var PaymentMethodTokensEndpoint&\Mockery\MockInterface */
	private $tokens_endpoint;

	/** @var SetupTokenBinding&\Mockery\MockInterface */
	private $setup_token_binding;

	/** @var \Mockery\MockInterface */
	private $session;

	/** @var CreatePaymentTokenForGuest */
	private $sut;

	public function setUp(): void {
		parent::setUp();

		$this->request_data        = Mockery::mock( RequestData::class );
		$this->tokens_endpoint     = Mockery::mock( PaymentMethodTokensEndpoint::class );
		$this->setup_token_binding = Mockery::mock( SetupTokenBinding::class );

		$this->sut = new CreatePaymentTokenForGuest(
			$this->request_data,
			$this->tokens_endpoint,
			$this->setup_token_binding,
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing()
		);

		$this->session = Mockery::mock();
		when( 'WC' )->justReturn( (object) array( 'session' => $this->session ) );
	}

	/**
	 * GIVEN the setup token was not created in the guest's current session
	 * WHEN handle_request() is called with that token
	 * THEN the request is rejected with HTTP 403
	 * AND PayPal is never asked to exchange the token
	 * AND nothing is stored in the session
	 */
	public function test_token_not_created_in_current_session_is_rejected_without_exchange(): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->once()
			->with( CreatePaymentTokenForGuest::ENDPOINT )
			->andReturn( array( 'vault_setup_token' => 'foreign-token' ) );

		$this->setup_token_binding->shouldReceive( 'consume' )->with( 'foreign-token' )->andReturn( false );

		$this->tokens_endpoint->shouldNotReceive( 'create_payment_token' );
		$this->session->shouldNotReceive( 'set' );

		expect( 'wp_send_json_success' )->never();
		expect( 'wp_send_json_error' )
			->once()
			->with( null, 403 )
			->andReturnUsing( $this->abort_request() );

		$this->expectExceptionMessage( 'wp_send_json_error' );

		$this->sut->handle_request();
	}

	/**
	 * Makes wp_send_json_error() end the request the way it does in production.
	 */
	private function abort_request(): callable {
		return static function (): void {
			throw new \Error( 'wp_send_json_error' );
		};
	}

	/**
	 * GIVEN the setup token was created in the guest's current session
	 * WHEN handle_request() is called
	 * THEN PayPal exchanges the token as a SETUP_TOKEN payment source
	 * AND the exchange result is stored in the session for the free trial checkout
	 * AND the request succeeds
	 */
	public function test_bound_token_is_exchanged_and_result_stored_in_session(): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->once()
			->andReturn( array( 'vault_setup_token' => 'guest-token' ) );

		$this->setup_token_binding->shouldReceive( 'consume' )->with( 'guest-token' )->andReturn( true );

		$result = (object) array(
			'id'       => 'vault-id',
			'customer' => (object) array( 'id' => 'GuestCust01' ),
		);

		/** @var PaymentSource|null $captured_source */
		$captured_source = null;
		$this->tokens_endpoint
			->shouldReceive( 'create_payment_token' )
			->once()
			->withArgs(
				function ( PaymentSource $source ) use ( &$captured_source ): bool {
					$captured_source = $source;
					return true;
				}
			)
			->andReturn( $result );

		$this->session->shouldReceive( 'set' )->once()->with( 'ppcp_guest_payment_for_free_trial', $result );

		expect( 'wp_send_json_success' )->once();
		expect( 'wp_send_json_error' )->never();

		$this->sut->handle_request();

		$this->assertSame( 'token', $captured_source->name() );
		$this->assertSame( 'guest-token', $captured_source->properties()->id );
		$this->assertSame( 'SETUP_TOKEN', $captured_source->properties()->type );
	}

	/**
	 * GIVEN the setup token was created in the guest's current session
	 * AND PayPal fails to exchange it
	 * WHEN handle_request() is called
	 * THEN an error is sent
	 * AND nothing is stored in the session
	 */
	public function test_paypal_failure_sends_error_and_stores_nothing(): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->once()
			->andReturn( array( 'vault_setup_token' => 'guest-token' ) );

		$this->setup_token_binding->shouldReceive( 'consume' )->andReturn( true );

		$this->tokens_endpoint
			->shouldReceive( 'create_payment_token' )
			->once()
			->andThrow( new PayPalApiException() );

		$this->session->shouldNotReceive( 'set' );

		expect( 'wp_send_json_success' )->never();
		expect( 'wp_send_json_error' )->once()->andReturnUsing( $this->abort_request() );

		$this->expectExceptionMessage( 'wp_send_json_error' );

		$this->sut->handle_request();
	}

	/**
	 * GIVEN the request fails nonce validation
	 * WHEN handle_request() is called
	 * THEN the request is rejected with HTTP 400
	 * AND PayPal is never called
	 */
	public function test_invalid_nonce_is_rejected_with_400(): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->once()
			->andThrow( new NonceValidationException( 'Invalid nonce.' ) );

		$this->setup_token_binding->shouldNotReceive( 'consume' );
		$this->tokens_endpoint->shouldNotReceive( 'create_payment_token' );

		expect( 'wp_send_json_error' )
			->once()
			->with( Mockery::on( 'is_array' ), 400 )
			->andReturnUsing( $this->abort_request() );

		$this->expectExceptionMessage( 'wp_send_json_error' );

		$this->sut->handle_request();
	}
}
