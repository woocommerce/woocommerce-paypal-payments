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
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;
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

	/** @var FreeTrialSubscriptionHelper&\Mockery\MockInterface */
	private $free_trial_helper;

	/** @var \Mockery\MockInterface */
	private $session;

	/** @var array<string, mixed> */
	private array $store = array();

	/** @var SessionHandler */
	private $session_handler;

	/** @var CreatePaymentTokenForGuest */
	private $sut;

	public function setUp(): void {
		parent::setUp();

		$this->session = Mockery::mock();
		$this->session->shouldReceive( 'get' )
			->andReturnUsing(
				function ( string $key ) {
					return $this->store[ $key ] ?? null;
				}
			)
			->byDefault();
		$this->session->shouldReceive( 'set' )
			->andReturnUsing(
				function ( string $key, $value ): void {
					$this->store[ $key ] = $value;
				}
			)
			->byDefault();
		when( 'WC' )->justReturn( (object) array( 'session' => $this->session ) );

		$this->session_handler     = new SessionHandler();
		$this->request_data       = Mockery::mock( RequestData::class );
		$this->tokens_endpoint     = Mockery::mock( PaymentMethodTokensEndpoint::class );
		$this->setup_token_binding = Mockery::mock( SetupTokenBinding::class );
		$this->free_trial_helper   = Mockery::mock( FreeTrialSubscriptionHelper::class );
		$this->free_trial_helper->shouldReceive( 'is_free_trial_cart' )->andReturn( true )->byDefault();

		$this->sut = new CreatePaymentTokenForGuest(
			$this->request_data,
			$this->tokens_endpoint,
			$this->setup_token_binding,
			$this->free_trial_helper,
			$this->session_handler,
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing()
		);
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
	 * AND the request is the free-trial continuation on a free-trial cart
	 * WHEN handle_request() is called
	 * THEN PayPal exchanges the token as a SETUP_TOKEN payment source
	 * AND the exchange result is stored in the session for the free trial checkout
	 * AND the PayPal account is marked as vaulted for the free trial
	 */
	public function test_bound_token_is_exchanged_and_result_stored_in_session(): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->andReturn(
				array(
					'vault_setup_token'       => 'guest-token',
					'free_trial_continuation' => '1',
				)
			);

		$this->setup_token_binding->shouldReceive( 'consume' )->andReturn( true );

		$result = $this->vault_result( 'paypal' );

		/** @var PaymentSource|null $captured_source */
		$captured_source = null;
		$this->tokens_endpoint
			->shouldReceive( 'create_payment_token' )
			->withArgs(
				function ( PaymentSource $source ) use ( &$captured_source ): bool {
					$captured_source = $source;
					return true;
				}
			)
			->andReturn( $result );

		when( 'wp_send_json_success' )->justReturn( null );
		expect( 'wp_send_json_error' )->never();

		$this->sut->handle_request();

		$this->assertSame( 'token', $captured_source->name() );
		$this->assertSame( 'guest-token', $captured_source->properties()->id );
		$this->assertSame( 'SETUP_TOKEN', $captured_source->properties()->type );
		$this->assertSame( $result, $this->store['ppcp_guest_payment_for_free_trial'] );
		$this->assertTrue( $this->session_handler->is_free_trial_vaulted() );
	}

	/**
	 * GIVEN a guest vaulted a PayPal account or a card (Blocks card fields)
	 * AND either the request does not ask for the free-trial continuation, the cart is not a free trial,
	 *     or the vaulted source is a card
	 * WHEN handle_request() is called
	 * THEN the exchange result is still stored in the session
	 * AND the free trial is not marked as vaulted
	 *
	 * @dataProvider no_free_trial_continuation_provider
	 */
	public function test_result_is_stored_without_marking_free_trial_vaulted( array $extra_request, bool $free_trial_cart, string $payment_source ): void {
		$this->request_data
			->shouldReceive( 'read_request' )
			->andReturn( array_merge( array( 'vault_setup_token' => 'guest-token' ), $extra_request ) );

		$this->setup_token_binding->shouldReceive( 'consume' )->andReturn( true );
		$this->free_trial_helper->shouldReceive( 'is_free_trial_cart' )->andReturn( $free_trial_cart );

		$result = $this->vault_result( $payment_source );

		$this->tokens_endpoint->shouldReceive( 'create_payment_token' )->andReturn( $result );

		when( 'wp_send_json_success' )->justReturn( null );
		expect( 'wp_send_json_error' )->never();

		$this->sut->handle_request();

		$this->assertSame( $result, $this->store['ppcp_guest_payment_for_free_trial'] );
		$this->assertFalse( $this->session_handler->is_free_trial_vaulted() );
	}

	public function no_free_trial_continuation_provider(): array {
		return array(
			'paypal: request lacks the continuation flag on a free-trial cart' => array( array(), true, 'paypal' ),
			'paypal: continuation requested on a cart that is not a free trial' => array( array( 'free_trial_continuation' => '1' ), false, 'paypal' ),
			'card: continuation requested on a free-trial cart' => array( array( 'free_trial_continuation' => '1' ), true, 'card' ),
		);
	}

	private function vault_result( string $payment_source ): \stdClass {
		$source = 'card' === $payment_source
			? (object) array( 'card' => (object) array( 'last_digits' => '1111' ) )
			: (object) array( 'paypal' => (object) array() );

		return (object) array(
			'id'             => 'vault-id',
			'customer'       => (object) array( 'id' => 'GuestCust01' ),
			'payment_source' => $source,
		);
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
