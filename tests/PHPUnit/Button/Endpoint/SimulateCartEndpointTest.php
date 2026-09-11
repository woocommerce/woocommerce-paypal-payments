<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButton;
use WooCommerce\PayPalCommerce\Button\Helper\IsolatedCartSimulator;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\CartProductsHelper;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class SimulateCartEndpointTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * @var SmartButton&Mockery\MockInterface
	 */
	private $smart_button;

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

		$this->smart_button   = Mockery::mock( SmartButton::class );
		$this->request_data   = Mockery::mock( RequestData::class );
		$this->cart_products  = Mockery::mock( CartProductsHelper::class );
		$this->cart_simulator = Mockery::mock( IsolatedCartSimulator::class );
		$logger               = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		$this->sut = new SimulateCartEndpoint(
			$this->smart_button,
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
	 * Stubs WC() with no session, so prevent_session_persistence() takes its
	 * no-op path. Explicit because another test file may already have defined
	 * WC(), which would otherwise decide this test's outcome.
	 */
	private function stub_wc_without_session(): void {
		$wc          = Mockery::mock( 'WooCommerce' );
		$wc->session = null;
		when( 'WC' )->justReturn( $wc );
	}

	private function stub_smart_button_flags( array $context_data ): void {
		$this->smart_button->shouldReceive( 'is_pay_later_button_enabled_for_location' )
			->with( 'product', $context_data )
			->andReturn( true );
		$this->smart_button->shouldReceive( 'is_pay_later_messaging_enabled_for_location' )
			->with( 'product', $context_data )
			->andReturn( true );
		$this->smart_button->shouldReceive( 'is_button_disabled' )
			->with( 'product', $context_data )
			->andReturn( false );
	}

	/**
	 * GIVEN a posted product for which the merchant has cart simulation enabled
	 * WHEN the request is handled
	 * THEN the response contains the simulated total, shipping fee, and the shop's pay
	 * later and button-state flags
	 */
	public function test_responds_with_simulated_totals_and_button_state(): void {
		$this->stub_wc_without_session();
		when( 'apply_filters' )->justReturn( true );
		when( 'wc_get_base_location' )->justReturn( array( 'country' => 'US' ) );
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$posted_products = array( array( 'product' => 'a-product', 'quantity' => 2 ) );
		$this->stub_posted_products( $posted_products );

		$this->cart_simulator->shouldReceive( 'simulate' )
			->once()
			->with( $posted_products )
			->andReturn( array( 'total' => 19.99, 'shipping_fee' => 4.5 ) );

		$this->stub_smart_button_flags(
			array(
				'product'     => 'a-product',
				'order_total' => 19.99,
			)
		);

		expect( 'wp_send_json_success' )
			->once()
			->with(
				array(
					'total'         => 19.99,
					'shipping_fee'  => 4.5,
					'currency_code' => 'USD',
					'country_code'  => 'US',
					'funding'       => array(
						'paylater' => array(
							'enabled' => true,
						),
					),
					'button'        => array(
						'is_disabled' => false,
					),
					'messages'      => array(
						'is_hidden' => false,
					),
				)
			);

		$this->sut->handle_request();
	}

	/**
	 * GIVEN a WooCommerce session with WC_Session_Handler::save_data registered on the
	 * shutdown hook - the mechanism that overwrites the whole session row from a stale
	 * snapshot and can wipe a concurrent shopper's cart
	 * WHEN a simulate-cart request is handled
	 * THEN that shutdown persistence is removed before the simulation runs, so this
	 * read-only request has nothing left to write back to the session
	 */
	public function test_removes_session_persistence_before_simulating(): void {
		when( 'apply_filters' )->justReturn( true );
		when( 'wc_get_base_location' )->justReturn( array( 'country' => 'US' ) );
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );
		when( 'wp_send_json_success' )->justReturn( null );

		$session  = Mockery::mock( \WC_Session::class );
		$callback = array( $session, 'save_data' );
		add_action( 'shutdown', $callback, 20 );

		$wc          = Mockery::mock();
		$wc->session = $session;
		when( 'WC' )->justReturn( $wc );

		$posted_products = array( array( 'product' => 'a-product', 'quantity' => 1 ) );
		$this->stub_posted_products( $posted_products );

		$this->cart_simulator->shouldReceive( 'simulate' )
			->once()
			->andReturn( array( 'total' => 19.99, 'shipping_fee' => 0.0 ) );

		$this->stub_smart_button_flags(
			array(
				'product'     => 'a-product',
				'order_total' => 19.99,
			)
		);

		$this->sut->handle_request();

		$this->assertFalse( has_action( 'shutdown', $callback, 20 ) );
	}

	/**
	 * GIVEN the merchant has switched cart simulation off via the
	 * woocommerce_paypal_payments_simulate_cart_enabled filter
	 * WHEN the request is handled
	 * THEN no cart is simulated and an error response is sent
	 */
	public function test_skips_simulation_when_disabled_by_filter(): void {
		$this->stub_wc_without_session();
		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_simulate_cart_enabled', true )
			->andReturn( false );

		// The endpoint does not return after sending the disabled-error response, so
		// execution falls through; these stubs keep that fall-through from failing on
		// posted data, while proving cart simulation is never actually performed.
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
		$this->stub_wc_without_session();
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
