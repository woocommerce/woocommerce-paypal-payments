<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Helper\IsolatedCartSimulator;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\CartProductsHelper;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class SimulateCartEndpointTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * @var RequestData&Mockery\MockInterface
	 */
	private $request_data;

	/**
	 * @var CartProductsHelper&Mockery\MockInterface
	 */
	private $cart_products;

	/**
	 * @var IsolatedCartSimulator&Mockery\MockInterface
	 */
	private $cart_simulator;

	private SimulateCartEndpoint $sut;

	public function setUp(): void {
		parent::setUp();

		$this->request_data   = Mockery::mock( RequestData::class );
		$this->cart_products  = Mockery::mock( CartProductsHelper::class );
		$this->cart_simulator = Mockery::mock( IsolatedCartSimulator::class );
		$logger               = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		$this->sut = new SimulateCartEndpoint(
			$this->request_data,
			$this->cart_products,
			$this->cart_simulator,
			$logger
		);
	}

	private function stub_posted_products( array $products ): void {
		$this->request_data->shouldReceive( 'read_request' )
			->with( SimulateCartEndpoint::nonce() )
			->andReturn( array( 'products' => $products ) );

		$this->cart_products->shouldReceive( 'products_from_data' )
			->andReturn( $products );
	}

	/**
	 * GIVEN a posted product for which the merchant has cart simulation enabled
	 * WHEN the request is handled
	 * THEN the response contains only the simulated total and the shop's currency code,
	 * and nothing else, so this v6 endpoint stays free of the v5 pay-later and
	 * button-state flags that would force a dependency on the v5 SmartButton
	 */
	public function test_responds_with_total_and_currency_code_only(): void {
		when( 'apply_filters' )->justReturn( true );
		when( 'wc_get_price_decimals' )->justReturn( 2 );
		when( 'wc_format_decimal' )->justReturn( '19.99' );
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$posted_products = array( array( 'product' => 'a-product', 'quantity' => 2 ) );
		$this->stub_posted_products( $posted_products );

		$this->cart_simulator->shouldReceive( 'simulate' )
			->once()
			->with( $posted_products )
			->andReturn( array( 'total' => 19.99, 'shipping_fee' => 0.0 ) );

		expect( 'wp_send_json_success' )
			->once()
			->with(
				array(
					'total'         => '19.99',
					'currency_code' => 'USD',
				)
			);

		$this->sut->handle_request();
	}

	/**
	 * GIVEN a posted product
	 * WHEN the request is handled
	 * THEN the price is resolved through the isolated simulator, never through the
	 * shopper's real cart
	 */
	public function test_does_not_touch_the_real_cart(): void {
		when( 'apply_filters' )->justReturn( true );
		when( 'wc_get_price_decimals' )->justReturn( 2 );
		when( 'wc_format_decimal' )->justReturn( '19.99' );
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );
		when( 'wp_send_json_success' )->justReturn( null );

		$posted_products = array( array( 'product' => 'a-product', 'quantity' => 1 ) );
		$this->stub_posted_products( $posted_products );

		$this->cart_simulator->shouldReceive( 'simulate' )
			->once()
			->andReturn( array( 'total' => 19.99, 'shipping_fee' => 0.0 ) );

		expect( 'WC' )->never();

		$this->sut->handle_request();
	}

	/**
	 * GIVEN the merchant has switched cart simulation off via the
	 * woocommerce_paypal_payments_simulate_cart_enabled filter
	 * WHEN the request is handled
	 * THEN no cart is simulated and an error response is sent
	 */
	public function test_skips_simulation_when_disabled_by_filter(): void {
		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_simulate_cart_enabled', true )
			->andReturn( false );

		// The endpoint does not return after sending the disabled-error response, so
		// execution falls through to product parsing; these stubs keep that fall-through
		// from posted data, while proving cart simulation is never actually performed.
		$this->request_data->shouldReceive( 'read_request' )->andReturn( array() );
		$this->cart_products->shouldReceive( 'products_from_data' )->andReturn( null );

		$this->cart_simulator->shouldReceive( 'simulate' )->never();

		expect( 'wp_send_json_error' )->atLeast()->once();

		$this->sut->handle_request();
	}

	/**
	 * GIVEN no usable products were posted
	 * WHEN the request is handled
	 * THEN no cart is simulated and an error response is sent
	 */
	public function test_skips_simulation_when_no_products_are_posted(): void {
		when( 'apply_filters' )->justReturn( true );

		$this->request_data->shouldReceive( 'read_request' )
			->with( SimulateCartEndpoint::nonce() )
			->andReturn( array() );

		$this->cart_products->shouldReceive( 'products_from_data' )->andReturn( null );

		$this->cart_simulator->shouldReceive( 'simulate' )->never();

		expect( 'wp_send_json_error' )->atLeast()->once();

		$this->sut->handle_request();
	}
}
