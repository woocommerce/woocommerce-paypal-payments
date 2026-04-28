<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use Brain\Monkey;
use Mockery;
use WC_Cart;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Helper\ShippingOptionsBuilder
 */
class ShippingOptionsBuilderTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		Monkey\Functions\when( 'get_woocommerce_currency' )->justReturn( 'USD' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Creates a WC shipping rate stub with the given id, label, and cost.
	 *
	 * @return \WC_Shipping_Rate|Mockery\MockInterface
	 */
	private function create_shipping_rate_stub( string $id, string $label, float $cost ): object {
		$rate = Mockery::mock( 'WC_Shipping_Rate' );
		$rate->allows( 'get_id' )->andReturn( $id );
		$rate->allows( 'get_label' )->andReturn( $label );
		$rate->allows( 'get_cost' )->andReturn( $cost );

		return $rate;
	}

	/**
	 * Wires up the WC() global stub, returning a fully-configured mock.
	 *
	 * @param array      $packages Value returned by WC()->shipping()->get_packages().
	 * @param array|null $chosen   Value returned by WC()->session->get('chosen_shipping_methods'),
	 *                             or null to simulate no session entry.
	 */
	private function stub_wc( array $packages, ?array $chosen ): void {
		$shipping_mock = Mockery::mock( 'WC_Shipping' );
		$shipping_mock->allows( 'get_packages' )->andReturn( $packages );

		$session_mock = Mockery::mock( 'WC_Session' );
		$session_mock->allows( 'get' )
			->with( 'chosen_shipping_methods' )
			->andReturn( $chosen );

		$wc_mock = Mockery::mock( 'WooCommerce' );
		$wc_mock->allows( 'shipping' )->andReturn( $shipping_mock );
		$wc_mock->session = $session_mock;

		Monkey\Functions\when( 'WC' )->justReturn( $wc_mock );
	}

	/**
	 * Builds one WC package array with the given rates.
	 *
	 * @param array $rates Keyed by rate id.
	 */
	private function make_package( array $rates ): array {
		return array( 'rates' => $rates );
	}

	// -------------------------------------------------------------------------
	// Rule 1 — null cart
	// -------------------------------------------------------------------------

	/**
	 * @scenario Cart is null
	 *
	 * Given no active WooCommerce cart exists
	 * When build() is called with null
	 * Then an empty array is returned without consulting WC() at all
	 */
	public function test_build_returns_empty_array_when_cart_is_null(): void {
		$builder = new ShippingOptionsBuilder();

		$result = $builder->build( null );

		$this->assertSame( [], $result );
	}

	// -------------------------------------------------------------------------
	// Rule 2 — empty packages
	// -------------------------------------------------------------------------

	/**
	 * @scenario Shipping packages are empty
	 *
	 * Given a valid WC cart is present
	 * And WC()->shipping()->get_packages() returns an empty array
	 * When build() is called
	 * Then an empty array is returned
	 */
	public function test_build_returns_empty_array_when_no_shipping_packages(): void {
		$this->stub_wc( [], [ 'flat_rate:1' ] );

		$wc_cart = Mockery::mock( WC_Cart::class );
		$builder = new ShippingOptionsBuilder();

		$result = $builder->build( $wc_cart );

		$this->assertSame( [], $result );
	}

	// -------------------------------------------------------------------------
	// Rule 3 — one package, one rate
	// -------------------------------------------------------------------------

	/**
	 * @scenario Single package with a single shipping rate
	 *
	 * Given a cart with one shipping package containing one rate
	 * And that rate is selected via the session
	 * When build() is called
	 * Then exactly one shipping option is returned
	 * And it has the correct id, label, amount, currency, and is_selected=true
	 */
	public function test_build_returns_single_option_for_one_package_one_rate(): void {
		$rate     = $this->create_shipping_rate_stub( 'flat_rate:1', 'Flat Rate', 5.0 );
		$packages = array( $this->make_package( array( 'flat_rate:1' => $rate ) ) );

		$this->stub_wc( $packages, array( 'flat_rate:1' ) );

		$wc_cart = Mockery::mock( WC_Cart::class );
		$builder = new ShippingOptionsBuilder();

		$result = $builder->build( $wc_cart );

		$this->assertCount( 1, $result );
		$this->assertSame( 'flat_rate:1', $result[0]['id'] );
		$this->assertSame( 'Flat Rate', $result[0]['label'] );
		$this->assertSame( '5.00', $result[0]['amount'] );
		$this->assertSame( 'USD', $result[0]['currency'] );
		$this->assertTrue( $result[0]['is_selected'] );
	}

	// -------------------------------------------------------------------------
	// Rule 4 — exactly one is_selected per response
	// -------------------------------------------------------------------------

	/**
	 * @scenario Single package with multiple rates, one chosen
	 *
	 * Given a cart with one shipping package containing two rates
	 * And the session chosen method is the second rate
	 * When build() is called
	 * Then two options are returned
	 * And only the chosen rate has is_selected=true
	 * And the other rate has is_selected=false
	 */
	public function test_build_marks_only_chosen_rate_as_selected(): void {
		$rate_flat = $this->create_shipping_rate_stub( 'flat_rate:1', 'Flat Rate', 5.0 );
		$rate_free = $this->create_shipping_rate_stub( 'free_shipping:1', 'Free Shipping', 0.0 );

		$packages = array(
			$this->make_package(
				array(
					'flat_rate:1'     => $rate_flat,
					'free_shipping:1' => $rate_free,
				)
			),
		);

		$this->stub_wc( $packages, array( 'free_shipping:1' ) );

		$wc_cart = Mockery::mock( WC_Cart::class );
		$builder = new ShippingOptionsBuilder();

		$result = $builder->build( $wc_cart );

		$this->assertCount( 2, $result );

		$by_id = array();
		foreach ( $result as $option ) {
			$by_id[ $option['id'] ] = $option;
		}

		$this->assertFalse( $by_id['flat_rate:1']['is_selected'] );
		$this->assertTrue( $by_id['free_shipping:1']['is_selected'] );
	}

	// -------------------------------------------------------------------------
	// Rule 5 — default to first rate when no session value
	// -------------------------------------------------------------------------

	/**
	 * @scenario No chosen shipping method in session
	 *
	 * Given a cart with available shipping rates
	 * And the session has no chosen_shipping_methods entry (returns null)
	 * When build() is called
	 * Then the first available rate is marked as selected
	 * And all other rates are not selected
	 */
	public function test_build_selects_first_rate_when_no_chosen_shipping_methods_in_session(): void {
		$rate_first  = $this->create_shipping_rate_stub( 'flat_rate:1', 'Standard', 5.0 );
		$rate_second = $this->create_shipping_rate_stub( 'express:1', 'Express', 15.0 );

		$packages = array(
			$this->make_package(
				array(
					'flat_rate:1' => $rate_first,
					'express:1'   => $rate_second,
				)
			),
		);

		// No chosen methods in session
		$this->stub_wc( $packages, null );

		$wc_cart = Mockery::mock( WC_Cart::class );
		$builder = new ShippingOptionsBuilder();

		$result = $builder->build( $wc_cart );

		$this->assertCount( 2, $result );

		// First in iteration order should be selected
		$this->assertTrue( $result[0]['is_selected'] );
		$this->assertFalse( $result[1]['is_selected'] );
	}

	/**
	 * @scenario Chosen shipping methods session is an empty array
	 *
	 * Given a cart with available shipping rates
	 * And WC()->session->get('chosen_shipping_methods') returns an empty array
	 * When build() is called
	 * Then the first available rate is selected by default
	 */
	public function test_build_selects_first_rate_when_chosen_shipping_methods_is_empty_array(): void {
		$rate     = $this->create_shipping_rate_stub( 'flat_rate:1', 'Flat Rate', 5.0 );
		$packages = array( $this->make_package( array( 'flat_rate:1' => $rate ) ) );

		$this->stub_wc( $packages, array() );

		$wc_cart = Mockery::mock( WC_Cart::class );
		$builder = new ShippingOptionsBuilder();

		$result = $builder->build( $wc_cart );

		$this->assertCount( 1, $result );
		$this->assertTrue( $result[0]['is_selected'] );
	}

	// -------------------------------------------------------------------------
	// Rule 6 — amount formatted as 2-decimal string
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider amount_formatting_provider
	 *
	 * @scenario     Amount is formatted as a decimal string with exactly two places
	 *
	 * Given a shipping rate with a given float cost
	 * When build() is called
	 * Then the amount in the returned option is a string with exactly two decimal places
	 */
	public function test_build_formats_amount_as_two_decimal_string(
		float $cost,
		string $expected_amount
	): void {
		$rate     = $this->create_shipping_rate_stub( 'flat_rate:1', 'Test Rate', $cost );
		$packages = array( $this->make_package( array( 'flat_rate:1' => $rate ) ) );

		$this->stub_wc( $packages, array( 'flat_rate:1' ) );

		$wc_cart = Mockery::mock( WC_Cart::class );
		$builder = new ShippingOptionsBuilder();

		$result = $builder->build( $wc_cart );

		$this->assertCount( 1, $result );
		$this->assertIsString( $result[0]['amount'] );
		$this->assertSame( $expected_amount, $result[0]['amount'] );
	}

	/**
	 * @return array<string, array{float, string}>
	 */
	public function amount_formatting_provider(): array {
		return array(
			'whole number cost becomes two decimal string' => array( 5.0, '5.00' ),
			'one decimal place is padded'                  => array( 7.5, '7.50' ),
			'already two decimal places is preserved'      => array( 12.99, '12.99' ),
			'zero cost is formatted correctly'             => array( 0.0, '0.00' ),
			'three decimals are rounded to two'            => array( 9.999, '10.00' ),
			'large amount is formatted correctly'          => array( 100.0, '100.00' ),
		);
	}

	// -------------------------------------------------------------------------
	// Rule 7 — currency from get_woocommerce_currency()
	// -------------------------------------------------------------------------

	/**
	 * @scenario Currency is taken from the WooCommerce store setting
	 *
	 * Given the store currency is set to EUR
	 * And a shipping package with one rate exists
	 * When build() is called
	 * Then the currency field of every returned option reflects the store currency
	 */
	public function test_build_uses_currency_from_get_woocommerce_currency(): void {
		// Override the default USD stub set in setUp()
		Monkey\Functions\when( 'get_woocommerce_currency' )->justReturn( 'EUR' );

		$rate     = $this->create_shipping_rate_stub( 'flat_rate:1', 'Standard', 8.0 );
		$packages = array( $this->make_package( array( 'flat_rate:1' => $rate ) ) );

		$this->stub_wc( $packages, array( 'flat_rate:1' ) );

		$wc_cart = Mockery::mock( WC_Cart::class );
		$builder = new ShippingOptionsBuilder();

		$result = $builder->build( $wc_cart );

		$this->assertCount( 1, $result );
		$this->assertSame( 'EUR', $result[0]['currency'] );
	}

	/**
	 * @scenario Currency is applied to all options when multiple rates are returned
	 *
	 * Given a store currency of GBP
	 * And a shipping package with two rates
	 * When build() is called
	 * Then every returned option has currency equal to GBP
	 */
	public function test_build_applies_currency_to_all_options(): void {
		Monkey\Functions\when( 'get_woocommerce_currency' )->justReturn( 'GBP' );

		$rate_a = $this->create_shipping_rate_stub( 'flat_rate:1', 'Standard', 5.0 );
		$rate_b = $this->create_shipping_rate_stub( 'express:1', 'Express', 15.0 );

		$packages = array(
			$this->make_package(
				array(
					'flat_rate:1' => $rate_a,
					'express:1'   => $rate_b,
				)
			),
		);

		$this->stub_wc( $packages, array( 'flat_rate:1' ) );

		$wc_cart = Mockery::mock( WC_Cart::class );
		$builder = new ShippingOptionsBuilder();

		$result = $builder->build( $wc_cart );

		$this->assertCount( 2, $result );
		foreach ( $result as $option ) {
			$this->assertSame( 'GBP', $option['currency'] );
		}
	}

	// -------------------------------------------------------------------------
	// Return-shape contract
	// -------------------------------------------------------------------------

	/**
	 * @scenario Each returned option contains all required keys
	 *
	 * Given a cart with one shipping package and one rate
	 * When build() is called
	 * Then each option array contains id, label, amount, currency, and is_selected
	 */
	public function test_build_option_contains_all_required_keys(): void {
		$rate     = $this->create_shipping_rate_stub( 'flat_rate:1', 'Flat Rate', 5.0 );
		$packages = array( $this->make_package( array( 'flat_rate:1' => $rate ) ) );

		$this->stub_wc( $packages, array( 'flat_rate:1' ) );

		$wc_cart = Mockery::mock( WC_Cart::class );
		$builder = new ShippingOptionsBuilder();

		$result = $builder->build( $wc_cart );

		$this->assertCount( 1, $result );
		$option = $result[0];

		$this->assertArrayHasKey( 'id', $option );
		$this->assertArrayHasKey( 'label', $option );
		$this->assertArrayHasKey( 'amount', $option );
		$this->assertArrayHasKey( 'currency', $option );
		$this->assertArrayHasKey( 'is_selected', $option );
		$this->assertIsBool( $option['is_selected'] );
		$this->assertIsString( $option['id'] );
		$this->assertIsString( $option['label'] );
		$this->assertIsString( $option['amount'] );
		$this->assertIsString( $option['currency'] );
	}
}
