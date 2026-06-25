<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use Mockery;
use Psr\Log\NullLogger;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\Button\Session\CartDataTransientStorage;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Endpoint\CartEndpoint;
use WP_REST_Request;

/**
 * @covers \WooCommerce\PayPalCommerce\WcGateway\Endpoint\ShippingCallbackEndpoint
 */
class ShippingCallbackEndpointTest extends TestCase
{
	private $cart_endpoint;
	private $amount_factory;
	private $cart_data_storage;
	private ShippingCallbackEndpoint $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->cart_endpoint     = Mockery::mock( CartEndpoint::class );
		$this->amount_factory    = Mockery::mock( AmountFactory::class );
		$this->cart_data_storage = Mockery::mock( CartDataTransientStorage::class );

		$this->sut = new ShippingCallbackEndpoint(
			$this->cart_endpoint,
			$this->amount_factory,
			new NullLogger(),
			$this->cart_data_storage
		);
	}

	/**
	 * @scenario Given a request with a non-empty cart_token and a PayPal order ID
	 *           that exists in CartDataTransientStorage, verify_request() returns true.
	 */
	public function test_verify_request_returns_true_when_order_id_is_known(): void
	{
		// Arrange
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'cart_token' )
			->andReturn( 'some-cart-token' );
		$request->shouldReceive( 'get_param' )
			->with( 'id' )
			->andReturn( '5O190127TN364715T' );

		$this->cart_data_storage
			->shouldReceive( 'get_by_paypal_order_id' )
			->once()
			->with( '5O190127TN364715T' )
			->andReturn( Mockery::mock( CartData::class ) );

		// When
		$result = $this->sut->verify_request( $request );

		// Then
		$this->assertTrue( $result );
	}

	/**
	 * @scenario Given a request whose PayPal order ID is not in CartDataTransientStorage
	 *           (unknown or fabricated order), verify_request() returns false.
	 */
	public function test_verify_request_returns_false_when_order_id_is_unknown(): void
	{
		// Arrange
		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'cart_token' )
			->andReturn( 'some-cart-token' );
		$request->shouldReceive( 'get_param' )
			->with( 'id' )
			->andReturn( 'UNKNOWN_ORDER_ID' );

		$this->cart_data_storage
			->shouldReceive( 'get_by_paypal_order_id' )
			->once()
			->with( 'UNKNOWN_ORDER_ID' )
			->andReturn( null );

		// When
		$result = $this->sut->verify_request( $request );

		// Then
		$this->assertFalse( $result );
	}

	/**
	 * @scenario Given a request with an empty cart_token or empty PayPal order ID,
	 *           verify_request() returns false without consulting storage.
	 */
	public function test_verify_request_returns_false_when_required_params_are_empty(): void
	{
		$cases = [
			[ 'cart_token' => '',               'id' => '5O190127TN364715T' ],
			[ 'cart_token' => 'some-cart-token', 'id' => '' ],
			[ 'cart_token' => '',               'id' => '' ],
		];

		foreach ( $cases as $params ) {
			$request = Mockery::mock( WP_REST_Request::class );
			$request->shouldReceive( 'get_param' )
				->with( 'cart_token' )
				->andReturn( $params['cart_token'] );
			$request->shouldReceive( 'get_param' )
				->with( 'id' )
				->andReturn( $params['id'] );

			$this->cart_data_storage->shouldNotReceive( 'get_by_paypal_order_id' );

			$result = $this->sut->verify_request( $request );

			$this->assertFalse( $result, 'Expected false for params: ' . json_encode( $params ) );
		}
	}
}