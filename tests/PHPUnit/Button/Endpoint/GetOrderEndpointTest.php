<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\Button\Session\CartDataTransientStorage;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;

/**
 * @covers \WooCommerce\PayPalCommerce\Button\Endpoint\GetOrderEndpoint
 */
class GetOrderEndpointTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	private RequestData $request_data;
	private OrderEndpoint $api_endpoint;
	private LoggerInterface $logger;
	private CartDataTransientStorage $cart_data_storage;
	private GetOrderEndpoint $sut;

	public function setUp(): void
	{
		parent::setUp();
		$this->request_data      = Mockery::mock( RequestData::class );
		$this->api_endpoint      = Mockery::mock( OrderEndpoint::class );
		$this->logger            = Mockery::mock( LoggerInterface::class );
		$this->cart_data_storage = Mockery::mock( CartDataTransientStorage::class );

		$this->sut = new GetOrderEndpoint(
			$this->request_data,
			$this->api_endpoint,
			$this->logger,
			$this->cart_data_storage
		);
	}

	/**
	 * @scenario GetOrderEndpoint::handle_request() retrieves the stored session identifier
	 *           and compares it to the current request session before returning order data.
	 */
	public function test_handle_returns_order_data_when_session_identifier_matches(): void
	{
		// Arrange
		$user_id   = 42;
		$order_id  = 'ORDER-MATCH-123';
		$cart_data = new CartData( array(), array(), false, $user_id, 'hash' );
		$cart_data->set_paypal_order_id( $order_id );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'to_array' )->andReturn( array( 'id' => $order_id ) );

		$this->request_data->shouldReceive( 'read_request' )
			->with( GetOrderEndpoint::nonce() )
			->andReturn( array( 'order_id' => $order_id ) );

		$this->cart_data_storage->shouldReceive( 'get_by_paypal_order_id' )
			->with( $order_id )
			->andReturn( $cart_data );

		$this->api_endpoint->shouldReceive( 'order' )
			->with( $order_id )
			->once()
			->andReturn( $order );

		expect( 'get_current_user_id' )->once()->andReturn( $user_id );
		expect( 'wp_send_json_success' )->once();

		// When
		$this->sut->handle_request();

		// Then — order data disclosed to the session that owns the transient binding
	}

	/**
	 * @scenario If the session identifier does not match, the endpoint returns an error
	 *           with no sensitive data disclosed, regardless of transient existence or nonce validity.
	 */
	public function test_handle_blocks_disclosure_and_returns_error_when_session_does_not_match(): void
	{
		// Arrange
		$order_id  = 'ORDER-MISMATCH-456';
		$cart_data = new CartData( array(), array(), false, 42, 'hash' ); // owned by user 42
		$cart_data->set_paypal_order_id( $order_id );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'to_array' )->andReturn( array() );

		$this->request_data->shouldReceive( 'read_request' )
			->with( GetOrderEndpoint::nonce() )
			->andReturn( array( 'order_id' => $order_id ) );

		$this->cart_data_storage->shouldReceive( 'get_by_paypal_order_id' )
			->with( $order_id )
			->andReturn( $cart_data );

		$this->api_endpoint->shouldReceive( 'order' )->andReturn( $order );

		$this->logger->shouldReceive( 'warning' )->once();

		when( 'get_current_user_id' )->justReturn( 99 ); // attacker session
		when( 'wp_send_json_success' )->justReturn( null );
		expect( 'wp_send_json_error' )
			->once()
			->with(
				Mockery::on(
					static function ( array $data ): bool {
						$message = strtolower( $data['message'] ?? '' );
						return strpos( $message, 'session' ) === false
							&& strpos( $message, 'owner' ) === false
							&& strpos( $message, 'belong' ) === false
							&& strpos( $message, 'user' ) === false;
					}
				)
			);

		// When
		$this->sut->handle_request();

		// Then — warning logged, error response sent, no ownership-revealing phrase in message
	}

	/**
	 * @scenario Transient still expires after 2 hours; session binding does not extend
	 *           the authorization window.
	 */
	public function test_handle_does_not_extend_transient_expiry_on_access(): void
	{
		// Arrange
		$user_id   = 7;
		$order_id  = 'ORDER-TTL-789';
		$cart_data = new CartData( array(), array(), false, $user_id, 'hash' );
		$cart_data->set_paypal_order_id( $order_id );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'to_array' )->andReturn( array( 'id' => $order_id ) );

		$this->request_data->shouldReceive( 'read_request' )
			->with( GetOrderEndpoint::nonce() )
			->andReturn( array( 'order_id' => $order_id ) );

		$this->cart_data_storage->shouldReceive( 'get_by_paypal_order_id' )
			->with( $order_id )
			->andReturn( $cart_data );

		$this->cart_data_storage->shouldReceive( 'save' )->never();

		$this->api_endpoint->shouldReceive( 'order' )
			->with( $order_id )
			->andReturn( $order );

		expect( 'get_current_user_id' )->once()->andReturn( $user_id );
		expect( 'wp_send_json_success' )->once();

		// When
		$this->sut->handle_request();

		// Then — save() never called; transient TTL is not reset on read
	}

	/**
	 * @scenario For guest requests (user_id=0), GetOrderEndpoint checks WC session customer ID
	 *           against the stored session_customer_id; a match allows disclosure.
	 */
	public function test_handle_returns_order_data_for_guest_when_session_customer_id_matches(): void
	{
		// Arrange
		$order_id         = 'ORDER-GUEST-MATCH-001';
		$session_customer = 'sess-guest-abc';

		$cart_data = Mockery::mock( CartData::class );
		$cart_data->shouldReceive( 'user_id' )->andReturn( 0 );
		$cart_data->shouldReceive( 'session_customer_id' )->once()->andReturn( $session_customer );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'to_array' )->andReturn( array( 'id' => $order_id ) );

		$this->request_data->shouldReceive( 'read_request' )
			->with( GetOrderEndpoint::nonce() )
			->andReturn( array( 'order_id' => $order_id ) );

		$this->cart_data_storage->shouldReceive( 'get_by_paypal_order_id' )
			->with( $order_id )
			->andReturn( $cart_data );

		$this->api_endpoint->shouldReceive( 'order' )
			->with( $order_id )
			->once()
			->andReturn( $order );

		$session = Mockery::mock( \stdClass::class );
		$session->shouldReceive( 'get_customer_id' )->andReturn( $session_customer );
		$wc          = new \stdClass();
		$wc->session = $session;
		when( 'WC' )->justReturn( $wc );

		expect( 'get_current_user_id' )->once()->andReturn( 0 );
		expect( 'wp_send_json_success' )->once();

		// When
		$this->sut->handle_request();

		// Then — guest session matches; order data disclosed
	}

	/**
	 * @scenario For guest requests (user_id=0), a mismatched WC session customer ID blocks
	 *           disclosure regardless of transient existence.
	 */
	public function test_handle_blocks_disclosure_for_guest_when_session_customer_id_does_not_match(): void
	{
		// Arrange
		$order_id = 'ORDER-GUEST-MISMATCH-002';

		$cart_data = Mockery::mock( CartData::class );
		$cart_data->shouldReceive( 'user_id' )->andReturn( 0 );
		$cart_data->shouldReceive( 'session_customer_id' )->once()->andReturn( 'sess-guest-abc' );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'to_array' )->andReturn( array() );

		$this->request_data->shouldReceive( 'read_request' )
			->with( GetOrderEndpoint::nonce() )
			->andReturn( array( 'order_id' => $order_id ) );

		$this->cart_data_storage->shouldReceive( 'get_by_paypal_order_id' )
			->with( $order_id )
			->andReturn( $cart_data );

		$this->api_endpoint->shouldReceive( 'order' )->andReturn( $order );

		$this->logger->shouldReceive( 'warning' )->once();

		$session = Mockery::mock( \stdClass::class );
		$session->shouldReceive( 'get_customer_id' )->andReturn( 'sess-DIFFERENT' );
		$wc          = new \stdClass();
		$wc->session = $session;
		when( 'WC' )->justReturn( $wc );

		when( 'get_current_user_id' )->justReturn( 0 );
		when( 'wp_send_json_success' )->justReturn( null );
		expect( 'wp_send_json_error' )
			->once()
			->with(
				Mockery::on(
					static function ( array $data ): bool {
						$message = strtolower( $data['message'] ?? '' );
						return strpos( $message, 'session' ) === false
							&& strpos( $message, 'owner' ) === false
							&& strpos( $message, 'belong' ) === false
							&& strpos( $message, 'user' ) === false;
					}
				)
			);

		// When
		$this->sut->handle_request();

		// Then — guest session mismatch; warning logged, error response sent, no ownership-revealing phrase
	}
}
