<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use Mockery;
use Psr\Log\NullLogger;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\Button\Session\CartDataTransientStorage;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Endpoint\CartEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Cart;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\CartResponse;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\CartTotals;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Money as StoreApiMoney;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\ShippingRate;
use WP_REST_Request;

use function Brain\Monkey\Functions\when;

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

		when( 'wp_json_encode' )->alias( 'json_encode' );

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

	/**
	 * GIVEN a PayPal shipping callback with a full address and a purchase unit reference
	 * WHEN handle_request() processes it and the Store API returns a shipping rate
	 * THEN the response is a 200 carrying the PayPal order id, the given reference_id
	 *      and the shipping options converted from the Store API rate
	 * AND the address forwarded to update_customer() never carries address_line_1/2,
	 *      which are PayPal field names the Store API does not understand
	 */
	public function test_handle_request_returns_success_payload_on_happy_path(): void
	{
		$this->stub_wc_states( [ 'US' => [ 'CA' => 'California', 'NY' => 'New York' ] ] );

		$request = $this->build_request(
			[
				'id'              => '5O190127TN364715T',
				'purchase_units'  => [ [ 'reference_id' => 'PUI-1' ] ],
				'shipping_address' => [
					'country_code' => 'US',
					'admin_area_1' => 'CA',
					'admin_area_2' => 'Beverly Hills',
					'postal_code'  => '90210',
					'address_line_1' => '1 Hollywood Blvd',
					'address_line_2' => 'Suite 1',
				],
			]
		);

		$this->cart_endpoint
			->shouldReceive( 'update_customer' )
			->once()
			->with(
				'wc-cart-token',
				[
					'shipping_address' => [
						'country'  => 'US',
						'state'    => 'CA',
						'city'     => 'Beverly Hills',
						'postcode' => '90210',
					],
				]
			)
			->andReturn( $this->cart_response_with_rates( [ $this->shipping_rate( 'flat_rate:1', 'Flat rate', 500 ) ] ) );

		$this->cart_endpoint->shouldNotReceive( 'select_shipping_rate' );

		$this->amount_factory
			->shouldReceive( 'from_store_api_cart' )
			->andReturn( Mockery::mock( Amount::class, [ 'to_array' => [ 'currency_code' => 'USD', 'value' => '5.00' ] ] ) );

		$response = $this->sut->handle_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( '5O190127TN364715T', $data['id'] );
		$this->assertSame( 'PUI-1', $data['purchase_units'][0]['reference_id'] );
		$this->assertSame( 'flat_rate:1', $data['purchase_units'][0]['shipping_options'][0]['id'] );
	}

	/**
	 * GIVEN a purchase unit that carries admin_area_1 "CA" for country_code "IE" (the state
	 *       value that used to crash the callback because Ireland has no such state)
	 * WHEN handle_request() converts the address for the Store API
	 * THEN the unrecognised state is dropped rather than forwarded, and the request still
	 *      succeeds instead of surfacing the Store API's invalid_state rejection as a fatal
	 */
	public function test_handle_request_drops_unknown_state_instead_of_failing(): void
	{
		$this->stub_wc_states( [ 'IE' => [ 'CW' => 'Carlow', 'CO' => 'Cork' ] ] );

		$request = $this->build_request(
			[
				'id'               => '5O190127TN364715T',
				'shipping_address' => [
					'country_code' => 'IE',
					'admin_area_1' => 'CA',
				],
			]
		);

		$this->cart_endpoint
			->shouldReceive( 'update_customer' )
			->once()
			->with(
				'wc-cart-token',
				[
					'shipping_address' => [
						'country'  => 'IE',
						'state'    => '',
						'city'     => '',
						'postcode' => '',
					],
				]
			)
			->andReturn( $this->cart_response_with_rates( [ $this->shipping_rate( 'flat_rate:1', 'Flat rate', 500 ) ] ) );

		$this->amount_factory
			->shouldReceive( 'from_store_api_cart' )
			->andReturn( Mockery::mock( Amount::class, [ 'to_array' => [] ] ) );

		$response = $this->sut->handle_request( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * GIVEN a state value that matches the full name of a WooCommerce state for the country
	 * WHEN handle_request() converts the address for the Store API
	 * THEN the state is converted to its WooCommerce code before being forwarded
	 *
	 * @dataProvider state_conversion_provider
	 */
	public function test_handle_request_converts_state_for_wc(
		array $states,
		string $country,
		string $admin_area_1,
		string $expected_state
	): void
	{
		$this->stub_wc_states( [ $country => $states ] );

		$request = $this->build_request(
			[
				'id'               => '5O190127TN364715T',
				'shipping_address' => [
					'country_code' => $country,
					'admin_area_1' => $admin_area_1,
				],
			]
		);

		$this->cart_endpoint
			->shouldReceive( 'update_customer' )
			->once()
			->with(
				'wc-cart-token',
				[
					'shipping_address' => [
						'country'  => $country,
						'state'    => $expected_state,
						'city'     => '',
						'postcode' => '',
					],
				]
			)
			->andReturn( $this->cart_response_with_rates( [ $this->shipping_rate( 'flat_rate:1', 'Flat rate', 500 ) ] ) );

		$this->amount_factory
			->shouldReceive( 'from_store_api_cart' )
			->andReturn( Mockery::mock( Amount::class, [ 'to_array' => [] ] ) );

		$response = $this->sut->handle_request( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function state_conversion_provider(): array
	{
		return [
			'state code is upper-cased and passed through'  => [ [ 'CA' => 'California', 'NY' => 'New York' ], 'US', 'ca', 'CA' ],
			'state name is converted to its code'           => [ [ 'CA' => 'California', 'NY' => 'New York' ], 'US', 'California', 'CA' ],
			'state name is matched case-insensitively'      => [ [ 'CA' => 'California', 'NY' => 'New York' ], 'US', 'california', 'CA' ],
		];
	}

	/**
	 * GIVEN a country that has no WooCommerce state list (e.g. Germany)
	 * WHEN handle_request() converts the address for the Store API
	 * THEN the state value is forwarded unchanged, since there is nothing to validate it against
	 */
	public function test_handle_request_leaves_state_untouched_when_country_has_no_state_list(): void
	{
		$this->stub_wc_states( [ 'DE' => [] ] );

		$request = $this->build_request(
			[
				'id'               => '5O190127TN364715T',
				'shipping_address' => [
					'country_code' => 'DE',
					'admin_area_1' => 'Bayern',
				],
			]
		);

		$this->cart_endpoint
			->shouldReceive( 'update_customer' )
			->once()
			->with(
				'wc-cart-token',
				[
					'shipping_address' => [
						'country'  => 'DE',
						'state'    => 'Bayern',
						'city'     => '',
						'postcode' => '',
					],
				]
			)
			->andReturn( $this->cart_response_with_rates( [ $this->shipping_rate( 'flat_rate:1', 'Flat rate', 500 ) ] ) );

		$this->amount_factory
			->shouldReceive( 'from_store_api_cart' )
			->andReturn( Mockery::mock( Amount::class, [ 'to_array' => [] ] ) );

		$response = $this->sut->handle_request( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * GIVEN a callback payload with no shipping_address at all
	 * WHEN handle_request() builds the address for the Store API
	 * THEN an address of empty fields is forwarded instead of a fatal on a missing array key
	 */
	public function test_handle_request_defaults_shipping_address_when_missing(): void
	{
		$request = $this->build_request(
			[
				'id' => '5O190127TN364715T',
			]
		);

		$this->cart_endpoint
			->shouldReceive( 'update_customer' )
			->once()
			->with(
				'wc-cart-token',
				[
					'shipping_address' => [
						'country'  => '',
						'state'    => '',
						'city'     => '',
						'postcode' => '',
					],
				]
			)
			->andReturn( $this->cart_response_with_rates( [ $this->shipping_rate( 'flat_rate:1', 'Flat rate', 500 ) ] ) );

		$this->amount_factory
			->shouldReceive( 'from_store_api_cart' )
			->andReturn( Mockery::mock( Amount::class, [ 'to_array' => [] ] ) );

		$response = $this->sut->handle_request( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * GIVEN a callback payload with no purchase_units entry
	 * WHEN handle_request() builds the success response
	 * THEN the purchase unit falls back to the reference_id "default"
	 */
	public function test_handle_request_defaults_reference_id_when_purchase_units_missing(): void
	{
		$request = $this->build_request(
			[
				'id' => '5O190127TN364715T',
			]
		);

		$this->cart_endpoint
			->shouldReceive( 'update_customer' )
			->once()
			->andReturn( $this->cart_response_with_rates( [ $this->shipping_rate( 'flat_rate:1', 'Flat rate', 500 ) ] ) );

		$this->amount_factory
			->shouldReceive( 'from_store_api_cart' )
			->andReturn( Mockery::mock( Amount::class, [ 'to_array' => [] ] ) );

		$response = $this->sut->handle_request( $request );

		$data = $response->get_data();
		$this->assertSame( 'default', $data['purchase_units'][0]['reference_id'] );
	}

	/**
	 * Builds a WP_REST_Request stub carrying the given decoded body params and a fixed
	 * cart_token, the way the PayPal shipping callback sends them.
	 */
	private function build_request( array $params ): WP_REST_Request
	{
		$params['cart_token'] = 'wc-cart-token';

		$request = new WP_REST_Request();
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$request->set_body( (string) json_encode( $params ) );

		return $request;
	}

	/**
	 * Stubs WC()->countries->get_states() with a fixed map of country code to state list.
	 */
	private function stub_wc_states( array $states_by_country ): void
	{
		when( 'wc_strtoupper' )->alias(
			static function ( string $value ): string {
				return strtoupper( $value );
			}
		);

		$countries = Mockery::mock();
		$countries->shouldReceive( 'get_states' )
			->andReturnUsing(
				static function ( string $country ) use ( $states_by_country ) {
					return $states_by_country[ $country ] ?? array();
				}
			);

		$wc            = Mockery::mock();
		$wc->countries = $countries;

		when( 'WC' )->justReturn( $wc );
	}

	/**
	 * Builds a Store API CartResponse carrying the given shipping rates.
	 */
	private function cart_response_with_rates( array $shipping_rates ): CartResponse
	{
		$cart = new Cart( Mockery::mock( CartTotals::class ), $shipping_rates );

		return new CartResponse( $cart, 'wc-cart-token' );
	}

	/**
	 * Builds a Store API ShippingRate with the given rate id, name and price in cents.
	 */
	private function shipping_rate( string $rate_id, string $name, int $price_cents ): ShippingRate
	{
		return new ShippingRate(
			$rate_id,
			$name,
			true,
			new StoreApiMoney( (string) $price_cents, 'USD', 2 ),
			new StoreApiMoney( '0', 'USD', 2 )
		);
	}
}