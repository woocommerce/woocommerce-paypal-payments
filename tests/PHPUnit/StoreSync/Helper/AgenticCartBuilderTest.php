<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use Brain\Monkey;
use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Button\Session\CartDataFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Address;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Schema\ShippingOption;
use Psr\Log\NullLogger;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCartBuilder
 */
class AgenticCartBuilderTest extends TestCase {

	/**
	 * @scenario Cart contains a selected shipping option
	 *
	 * Given a PayPalCart with one available_shipping_options entry where isSelected is true
	 * And the WC session is available via WC()->session
	 * When paypal_cart_to_wc_cart() is called
	 * Then WC()->session->set('chosen_shipping_methods', ['flat_rate:4']) must be called
	 * So that WooCommerce knows which shipping method to apply during calculate_totals()
	 */
	public function test_selected_shipping_option_is_applied_to_wc_session(): void {
		// Arrange: a ShippingOption stub reporting isSelected = true.
		$shipping_option = Mockery::mock( ShippingOption::class );
		$shipping_option->allows( 'is_selected' )->andReturn( true );
		$shipping_option->allows( 'id' )->andReturn( 'flat_rate:4' );

		// Arrange: a PayPalCart stub returning the option and minimal empty data.
		$paypal_cart = Mockery::mock( PayPalCart::class );
		$paypal_cart->allows( 'available_shipping_options' )
			->andReturn( array( $shipping_option ) );
		$paypal_cart->allows( 'items' )->andReturn( array() );
		$paypal_cart->allows( 'coupons' )->andReturn( null );
		$paypal_cart->allows( 'customer' )->andReturn( null );
		$paypal_cart->allows( 'shipping_address' )->andReturn( null );
		$paypal_cart->allows( 'billing_address' )->andReturn( null );

		// Arrange: WC session mock — assert that set() is called with the right method.
		$session_mock = Mockery::mock( 'WC_Session' );
		$session_mock->expects( 'set' )
			->once()
			->with( 'chosen_shipping_methods', array( 'flat_rate:4' ) );

		// Arrange: wire up WC_Cart and WC_Customer stubs.
		$wc_cart_mock = Mockery::mock( 'WC_Cart' );
		$wc_cart_mock->allows( 'empty_cart' );
		$wc_cart_mock->allows( 'calculate_totals' );

		$wc_customer_mock = Mockery::mock( 'WC_Customer' );

		$wc_mock           = Mockery::mock( 'WooCommerce' );
		$wc_mock->cart     = $wc_cart_mock;
		$wc_mock->customer = $wc_customer_mock;
		$wc_mock->session  = $session_mock;

		Monkey\Functions\when( 'WC' )->justReturn( $wc_mock );
		Monkey\Functions\when( 'is_wp_error' )->justReturn( false );

		// Arrange: product manager stub — returns false so add_items_to_cart will treat
		// the cart as populated (items array is empty, so no loop iterations, is_empty stays
		// true — but we stub add_items_to_cart result via WP_Error path).
		// Since items() returns [], add_items_to_cart will hit the "empty cart" WP_Error branch.
		// Stub is_wp_error to return false so the flow continues past the error check.
		$product_manager_stub = Mockery::mock( ProductManager::class );

		$cart_data_factory_stub     = Mockery::mock( CartDataFactory::class );
		$purchase_unit_factory_stub = Mockery::mock( PurchaseUnitFactory::class );

		$sut = new AgenticCartBuilder(
			$wc_mock,
			$product_manager_stub,
			$cart_data_factory_stub,
			$purchase_unit_factory_stub,
			new NullLogger()
		);

		$sut->paypal_cart_to_wc_cart( $paypal_cart );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a PayPalCart whose shipping_address() returns an Address with specific field values
	 * AND billing_address() returns null
	 * WHEN paypal_cart_to_wc_cart() is called
	 * THEN WC_Customer::set_shipping_address_1() is called with the value from address_line_1('')
	 * AND the other five shipping setters receive the matching values from the Address mock
	 */
	public function test_shipping_address_fields_are_applied_to_wc_customer(): void {
		// Arrange: an Address mock returning known test values for each field.
		$address = Mockery::mock( Address::class );
		$address->allows( 'address_line_1' )->with( '' )->andReturn( '123 Main St' );
		$address->allows( 'address_line_2' )->with( '' )->andReturn( 'Apt 4B' );
		$address->allows( 'admin_area_2' )->with( '' )->andReturn( 'Springfield' );
		$address->allows( 'admin_area_1' )->with( '' )->andReturn( 'IL' );
		$address->allows( 'postal_code' )->with( '' )->andReturn( '62701' );
		$address->allows( 'country_code' )->with( '' )->andReturn( 'US' );

		// Arrange: PayPalCart stub returning the shipping address and null for billing.
		$paypal_cart = Mockery::mock( PayPalCart::class );
		$paypal_cart->allows( 'available_shipping_options' )->andReturn( array() );
		$paypal_cart->allows( 'items' )->andReturn( array() );
		$paypal_cart->allows( 'coupons' )->andReturn( null );
		$paypal_cart->allows( 'customer' )->andReturn( null );
		$paypal_cart->allows( 'shipping_address' )->andReturn( $address );
		$paypal_cart->allows( 'billing_address' )->andReturn( null );

		// Arrange: WC_Customer mock — verify all six shipping setters are called correctly.
		$wc_customer_mock = Mockery::mock( 'WC_Customer' );
		$wc_customer_mock->allows( 'get_first_name' )->andReturn( '' );
		$wc_customer_mock->allows( 'get_last_name' )->andReturn( '' );
		$wc_customer_mock->expects( 'set_shipping_first_name' )->once()->with( '' );
		$wc_customer_mock->expects( 'set_shipping_last_name' )->once()->with( '' );
		$wc_customer_mock->expects( 'set_shipping_address_1' )->once()->with( '123 Main St' );
		$wc_customer_mock->expects( 'set_shipping_address_2' )->once()->with( 'Apt 4B' );
		$wc_customer_mock->expects( 'set_shipping_city' )->once()->with( 'Springfield' );
		$wc_customer_mock->expects( 'set_shipping_state' )->once()->with( 'IL' );
		$wc_customer_mock->expects( 'set_shipping_postcode' )->once()->with( '62701' );
		$wc_customer_mock->expects( 'set_shipping_country' )->once()->with( 'US' );

		// Arrange: wire up WC_Cart stub and WC global.
		$wc_cart_mock = Mockery::mock( 'WC_Cart' );
		$wc_cart_mock->allows( 'empty_cart' );
		$wc_cart_mock->allows( 'calculate_totals' );

		$wc_mock           = Mockery::mock( 'WooCommerce' );
		$wc_mock->cart     = $wc_cart_mock;
		$wc_mock->customer = $wc_customer_mock;
		$wc_mock->session  = null;

		Monkey\Functions\when( 'WC' )->justReturn( $wc_mock );
		Monkey\Functions\when( 'is_wp_error' )->justReturn( false );

		$product_manager_stub       = Mockery::mock( ProductManager::class );
		$cart_data_factory_stub     = Mockery::mock( CartDataFactory::class );
		$purchase_unit_factory_stub = Mockery::mock( PurchaseUnitFactory::class );

		$sut = new AgenticCartBuilder(
			$wc_mock,
			$product_manager_stub,
			$cart_data_factory_stub,
			$purchase_unit_factory_stub,
			new NullLogger()
		);

		$sut->paypal_cart_to_wc_cart( $paypal_cart );

		$this->addToAssertionCount( 1 );
	}
}
