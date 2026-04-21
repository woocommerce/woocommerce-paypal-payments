<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button;

class IsolatedCartSimulatorTest extends SimulateCartTestCase {

	public function testSimulateSimpleProduct(): void {
		$product  = $this->createSimpleProduct( 30.00 );
		$products = [ $this->productData( $product ) ];

		$result = $this->cart_simulator->simulate( $products );

		self::assertArrayHasKey( 'total', $result );
		self::assertArrayHasKey( 'shipping_fee', $result );
		self::assertIsFloat( $result['total'] );
		self::assertIsFloat( $result['shipping_fee'] );
		self::assertGreaterThan( 0.0, $result['total'] );
	}

	public function testSimulateDoesNotModifyRealCart(): void {
		// Add a product to the real cart.
		$cart_product = $this->createSimpleProduct( 50.00 );
		WC()->cart->add_to_cart( $cart_product->get_id(), 2 );
		WC()->cart->calculate_totals();

		$original_count = WC()->cart->get_cart_contents_count();
		$original_total = (float) WC()->cart->get_total( 'numeric' );

		self::assertGreaterThan( 0, $original_count );
		self::assertGreaterThan( 0.0, $original_total );

		// Simulate a different product.
		$sim_product = $this->createSimpleProduct( 99.00 );
		$products    = [ $this->productData( $sim_product, 5 ) ];

		$this->cart_simulator->simulate( $products );

		// Real cart must be unchanged.
		self::assertEquals( $original_count, WC()->cart->get_cart_contents_count() );
		self::assertEquals( $original_total, (float) WC()->cart->get_total( 'numeric' ) );
	}

	public function testSimulateDoesNotClearSessionCart(): void {
		$product = $this->createSimpleProduct( 25.00 );
		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();

		// Force a session write so there is something to preserve.
		WC()->cart->session->set_session();
		self::assertNotEmpty( WC()->session->get( 'cart' ) );

		$sim_product = $this->createSimpleProduct( 99.00 );
		$this->cart_simulator->simulate( [ $this->productData( $sim_product ) ] );

		self::assertNotEmpty(
			WC()->session->get( 'cart' ),
			'Real session cart must survive a simulate call (guests rely on this).'
		);
	}

	public function testSimulateDoesNotRemoveShutdownHooks(): void {
		$callback = function () {};

		add_action( 'shutdown', $callback, 999 );

		$product  = $this->createSimpleProduct( 10.00 );
		$products = [ $this->productData( $product ) ];

		$this->cart_simulator->simulate( $products );

		// The shutdown hook must still be registered (old code removed ALL shutdown hooks).
		self::assertTrue( has_action( 'shutdown', $callback ) !== false );

		remove_action( 'shutdown', $callback, 999 );
	}

	public function testSimulationFilterIsActiveOnlyDuringSimulation(): void {
		// Before simulation: filter should return default (false).
		self::assertFalse( apply_filters( 'woocommerce_paypal_payments_is_simulating_cart', false ) );

		$filter_value_during_simulation = null;
		add_action( 'woocommerce_before_calculate_totals', function () use ( &$filter_value_during_simulation ) {
			$filter_value_during_simulation = apply_filters( 'woocommerce_paypal_payments_is_simulating_cart', false );
		} );

		$product  = $this->createSimpleProduct( 10.00 );
		$products = [ $this->productData( $product ) ];

		$this->cart_simulator->simulate( $products );

		// During simulation it should have been true.
		self::assertTrue( $filter_value_during_simulation );

		// After simulation: filter should return default again.
		self::assertFalse( apply_filters( 'woocommerce_paypal_payments_is_simulating_cart', false ) );
	}

	public function testSimulateMatchesRealCartTotal(): void {
		$product  = $this->createSimpleProduct( 42.50 );
		$products = [ $this->productData( $product, 3 ) ];

		// Get total via isolated simulation.
		$sim_result = $this->cart_simulator->simulate( $products );

		// Get total via real cart for comparison.
		WC()->cart->add_to_cart( $product->get_id(), 3 );
		WC()->cart->calculate_totals();
		$real_total = (float) WC()->cart->get_total( 'numeric' );

		self::assertEquals( $real_total, $sim_result['total'] );
	}
}
