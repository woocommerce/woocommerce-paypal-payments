<?php
/**
 * Tests for StorePayPalCart.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\StoreData;

use Brain\Monkey\Functions;
use Mockery;
use WC_Cart;
use WP_Error;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCartBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Address;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Customer;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PaymentMethod;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\StoreSync\StoreSyncTestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart
 */
class StorePayPalCartTest extends StoreSyncTestCase {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Builds a minimal valid PayPalCart stub (no customer, no addresses, no payment method).
	 *
	 * @param array $overrides Key-value pairs to override specific stub method returns.
	 * @return PayPalCart&\Mockery\MockInterface
	 */
	private function make_paypal_cart( array $overrides = [] ): PayPalCart {
		$stub = Mockery::mock( PayPalCart::class );
		$stub->allows( 'customer' )->andReturn( $overrides['customer'] ?? null );
		$stub->allows( 'shipping_address' )->andReturn( $overrides['shipping_address'] ?? null );
		$stub->allows( 'billing_address' )->andReturn( $overrides['billing_address'] ?? null );
		$stub->allows( 'payment_method' )->andReturn( $overrides['payment_method'] ?? null );
		$stub->allows( 'items' )->andReturn( $overrides['items'] ?? array() );
		return $stub;
	}

	/**
	 * Builds a StoreCurrencyValue stub returning the given currency code.
	 *
	 * @param string $currency The currency code to return.
	 * @return StoreCurrencyValue&\Mockery\MockInterface
	 */
	private function make_currency( string $currency = 'USD' ): StoreCurrencyValue {
		$stub = Mockery::mock( StoreCurrencyValue::class );
		$stub->allows( 'value' )->andReturn( $currency );
		return $stub;
	}

	/**
	 * Builds an AgenticCartBuilder stub that returns the given WC_Cart or WP_Error.
	 *
	 * @param WC_Cart|WP_Error $result What paypal_cart_to_wc_cart() will return.
	 * @return AgenticCartBuilder&\Mockery\MockInterface
	 */
	private function make_cart_builder( $result ): AgenticCartBuilder {
		$stub = Mockery::mock( AgenticCartBuilder::class );
		$stub->allows( 'paypal_cart_to_wc_cart' )->andReturn( $result );
		return $stub;
	}

	/**
	 * Builds a StoreCartItem stub using the given CartItem schema and price.
	 *
	 * @param CartItem $schema     The schema to return from schema().
	 * @param float    $real_price The value to return from real_price().
	 * @return StoreCartItem&\Mockery\MockInterface
	 */
	private function make_store_item( CartItem $schema, float $real_price = 9.99 ): StoreCartItem {
		$stub = Mockery::mock( StoreCartItem::class );
		$stub->allows( 'schema' )->andReturn( $schema );
		$stub->allows( 'real_price' )->andReturn( $real_price );
		return $stub;
	}

	/**
	 * Creates a real CartItem schema from the given input array.
	 *
	 * @param array $input Fields for the CartItem.
	 * @return CartItem
	 */
	private function make_cart_item_schema( array $input ): CartItem {
		$validation = new StoreValidation();
		return CartItem::from_array( $input, $validation );
	}

	/**
	 * Creates a real PayPalCart schema from the given input array.
	 *
	 * @param array $input Fields for the PayPalCart.
	 * @return PayPalCart
	 */
	private function make_paypal_cart_schema( array $input ): PayPalCart {
		$validation = new StoreValidation();
		return PayPalCart::from_array( $input, $validation );
	}

	/**
	 * Builds a fully configured StorePayPalCart SUT with sensible defaults.
	 *
	 * @param array $overrides Keys: paypal_cart, validation, store_items, cart_builder, store_currency.
	 * @return StorePayPalCart
	 */
	private function make_sut( array $overrides = [] ): StorePayPalCart {
		// Default builder returns WP_Error so tests that don't focus on wc_cart/totals
		// are not required to stub WC_Cart total methods.
		return new StorePayPalCart(
			$overrides['paypal_cart'] ?? $this->make_paypal_cart(),
			$overrides['validation'] ?? new StoreValidation(),
			$overrides['store_items'] ?? array(),
			$overrides['cart_builder'] ?? $this->make_cart_builder( Mockery::mock( WP_Error::class ) ),
			$overrides['store_currency'] ?? $this->make_currency( 'USD' )
		);
	}

	// -------------------------------------------------------------------------
	// Group 1 — Getters
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a StorePayPalCart constructed with a PayPalCart instance
	 * WHEN paypal_cart() is called
	 * THEN the exact same PayPalCart instance is returned
	 */
	public function test_paypal_cart_returns_injected_instance(): void {
		$paypal_cart = $this->make_paypal_cart();
		$sut         = $this->make_sut( array( 'paypal_cart' => $paypal_cart ) );

		$this->assertSame( $paypal_cart, $sut->paypal_cart() );
	}

	/**
	 * GIVEN a StorePayPalCart constructed with an array of StoreCartItem objects
	 * WHEN items() is called
	 * THEN the exact same array is returned
	 */
	public function test_items_returns_injected_array(): void {
		$schema = $this->make_cart_item_schema( array( 'quantity' => 1 ) );
		$item1  = $this->make_store_item( $schema, 5.00 );
		$item2  = $this->make_store_item( $schema, 10.00 );
		$items  = array( $item1, $item2 );

		$sut = $this->make_sut( array( 'store_items' => $items ) );

		$this->assertSame( $items, $sut->items() );
	}

	/**
	 * GIVEN a StorePayPalCart constructed with a StoreCurrencyValue returning EUR
	 * WHEN currency() is called
	 * THEN EUR is returned
	 */
	public function test_currency_delegates_to_store_currency_value(): void {
		$sut = $this->make_sut( array( 'store_currency' => $this->make_currency( 'EUR' ) ) );

		$this->assertSame( 'EUR', $sut->currency() );
	}

	/**
	 * GIVEN a StorePayPalCart constructed with a StoreValidation instance
	 * WHEN validation() is called
	 * THEN the exact same StoreValidation instance is returned
	 */
	public function test_validation_returns_injected_instance(): void {
		$validation = new StoreValidation();
		$sut        = $this->make_sut( array( 'validation' => $validation ) );

		$this->assertSame( $validation, $sut->validation() );
	}

	// -------------------------------------------------------------------------
	// Group 2 — wc_cart(): lazy build
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a cart builder that returns a WC_Cart
	 * WHEN wc_cart() is called
	 * THEN the WC_Cart returned by the builder is returned
	 */
	public function test_wc_cart_returns_cart_from_builder_on_success(): void {
		$wc_cart = Mockery::mock( WC_Cart::class );
		$sut     = $this->make_sut( array( 'cart_builder' => $this->make_cart_builder( $wc_cart ) ) );

		$this->assertSame( $wc_cart, $sut->wc_cart() );
	}

	/**
	 * GIVEN a cart builder that returns a WC_Cart
	 * WHEN wc_cart() is called twice
	 * THEN the builder is invoked only once and both calls return the same WC_Cart instance
	 */
	public function test_wc_cart_is_cached_and_builder_called_only_once(): void {
		$wc_cart = Mockery::mock( WC_Cart::class );

		$builder = Mockery::mock( AgenticCartBuilder::class );
		$builder->expects( 'paypal_cart_to_wc_cart' )
			->once()
			->andReturn( $wc_cart );

		$sut = $this->make_sut( array( 'cart_builder' => $builder ) );

		$first  = $sut->wc_cart();
		$second = $sut->wc_cart();

		$this->assertSame( $wc_cart, $first );
		$this->assertSame( $first, $second );
	}

	// -------------------------------------------------------------------------
	// Group 3 — wc_cart(): WP_Error path
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a cart builder that returns a WP_Error
	 * WHEN wc_cart() is called
	 * THEN null is returned
	 */
	public function test_wc_cart_returns_null_when_builder_returns_wp_error(): void {
		$wp_error = Mockery::mock( WP_Error::class );
		$sut      = $this->make_sut( array( 'cart_builder' => $this->make_cart_builder( $wp_error ) ) );

		$this->assertNull( $sut->wc_cart() );
	}

	/**
	 * GIVEN a cart builder that returned a WP_Error on the first call
	 * WHEN wc_cart() is called a second time
	 * THEN the builder is not invoked again and null is returned
	 */
	public function test_wc_cart_error_result_is_cached_and_builder_not_called_again(): void {
		$wp_error = Mockery::mock( WP_Error::class );

		$builder = Mockery::mock( AgenticCartBuilder::class );
		$builder->expects( 'paypal_cart_to_wc_cart' )
			->once()
			->andReturn( $wp_error );

		$sut = $this->make_sut( array( 'cart_builder' => $builder ) );

		$this->assertNull( $sut->wc_cart() );
		$this->assertNull( $sut->wc_cart() );
	}

	// -------------------------------------------------------------------------
	// Group 4 — is_ready_for_payment()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN no validation issues, at least one store item, and a payment method present
	 * WHEN is_ready_for_payment() is called
	 * THEN true is returned
	 */
	public function test_is_ready_for_payment_true_when_all_conditions_met(): void {
		$payment_method = Mockery::mock( PaymentMethod::class );
		$paypal_cart    = $this->make_paypal_cart( array( 'payment_method' => $payment_method ) );
		$schema         = $this->make_cart_item_schema( array( 'quantity' => 1 ) );

		$sut = $this->make_sut( array(
			'paypal_cart'  => $paypal_cart,
			'validation'   => new StoreValidation(),
			'store_items'  => array( $this->make_store_item( $schema ) ),
		) );

		$this->assertTrue( $sut->is_ready_for_payment() );
	}

	/**
	 * GIVEN a condition that prevents payment readiness
	 * WHEN is_ready_for_payment() is called
	 * THEN false is returned
	 *
	 * @dataProvider not_ready_for_payment_provider
	 */
	public function test_is_ready_for_payment_false_when_condition_is_missing(
		bool $has_validation_issue,
		bool $has_items,
		bool $has_payment_method
	): void {
		$validation = new StoreValidation();
		if ( $has_validation_issue ) {
			$validation->add_invalid_data( 'test', 'reason', 'message' );
		}

		$payment_method = $has_payment_method ? Mockery::mock( PaymentMethod::class ) : null;
		$paypal_cart    = $this->make_paypal_cart( array( 'payment_method' => $payment_method ) );

		$store_items = array();
		if ( $has_items ) {
			$schema      = $this->make_cart_item_schema( array( 'quantity' => 1 ) );
			$store_items = array( $this->make_store_item( $schema ) );
		}

		$sut = $this->make_sut( array(
			'paypal_cart' => $paypal_cart,
			'validation'  => $validation,
			'store_items' => $store_items,
		) );

		$this->assertFalse( $sut->is_ready_for_payment() );
	}

	public function not_ready_for_payment_provider(): array {
		return array(
			'validation has issues, items present, payment method present'     => array( true, true, true ),
			'no validation issues, no items, payment method present'           => array( false, false, true ),
			'no validation issues, items present, no payment method'           => array( false, true, false ),
			'all conditions missing'                                            => array( true, false, false ),
		);
	}

	// -------------------------------------------------------------------------
	// Group 5 — totals()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN the cart builder returned a WP_Error so wc_cart() is null
	 * WHEN totals() is called
	 * THEN null is returned
	 */
	public function test_totals_returns_null_when_wc_cart_is_null(): void {
		$wp_error = Mockery::mock( WP_Error::class );
		$sut      = $this->make_sut( array( 'cart_builder' => $this->make_cart_builder( $wp_error ) ) );

		$this->assertNull( $sut->totals() );
	}

	/**
	 * GIVEN a WC_Cart is available but validation has a pricing issue
	 * WHEN totals() is called
	 * THEN null is returned
	 */
	public function test_totals_returns_null_when_pricing_issue_exists(): void {
		$wc_cart    = Mockery::mock( WC_Cart::class );
		$validation = new StoreValidation();
		$validation->add_price_mismatch( 'Price changed.' );

		$sut = $this->make_sut( array(
			'cart_builder' => $this->make_cart_builder( $wc_cart ),
			'validation'   => $validation,
		) );

		$this->assertNull( $sut->totals() );
	}

	/**
	 * GIVEN a WC_Cart with valid totals and no pricing issue
	 * WHEN totals() is called
	 * THEN the totals array built from WC_Cart method values is returned
	 * AND each key (subtotal, shipping, tax, total) contains a Money structure
	 *     with the correct currency_code and decimal-formatted value
	 */
	public function test_totals_returns_array_when_wc_cart_available_and_no_pricing_issue(): void {
		$wc_cart = Mockery::mock( WC_Cart::class );
		$wc_cart->allows( 'get_cart_contents_total' )->andReturn( '50.00' );
		$wc_cart->allows( 'get_discount_total' )->andReturn( '0.00' );
		$wc_cart->allows( 'get_shipping_total' )->andReturn( '5.00' );
		$wc_cart->allows( 'get_total_tax' )->andReturn( '3.50' );
		$wc_cart->allows( 'get_total' )->with( 'edit' )->andReturn( '55.00' );

		$sut = $this->make_sut( array(
			'cart_builder'   => $this->make_cart_builder( $wc_cart ),
			'store_currency' => $this->make_currency( 'USD' ),
		) );

		$totals = $sut->totals();

		$this->assertIsArray( $totals );
		$this->assertArrayHasKey( 'subtotal', $totals );
		$this->assertArrayHasKey( 'shipping', $totals );
		$this->assertArrayHasKey( 'tax', $totals );
		$this->assertArrayHasKey( 'total', $totals );
		$this->assertMoneyValue( $totals['subtotal'], 50.00, 'USD' );
		$this->assertMoneyValue( $totals['shipping'], 5.00, 'USD' );
		$this->assertMoneyValue( $totals['tax'], 3.50, 'USD' );
		$this->assertMoneyValue( $totals['total'], 55.00, 'USD' );
	}

	/**
	 * GIVEN a WC_Cart where the discount total is greater than zero
	 * WHEN totals() is called
	 * THEN the totals array includes a discount key with the correct Money structure
	 */
	public function test_totals_includes_discount_when_discount_total_is_positive(): void {
		$wc_cart = Mockery::mock( WC_Cart::class );
		$wc_cart->allows( 'get_cart_contents_total' )->andReturn( '100.00' );
		$wc_cart->allows( 'get_discount_total' )->andReturn( '10.00' );
		$wc_cart->allows( 'get_shipping_total' )->andReturn( '0.00' );
		$wc_cart->allows( 'get_total_tax' )->andReturn( '0.00' );
		$wc_cart->allows( 'get_total' )->with( 'edit' )->andReturn( '90.00' );

		$sut = $this->make_sut( array(
			'cart_builder'   => $this->make_cart_builder( $wc_cart ),
			'store_currency' => $this->make_currency( 'EUR' ),
		) );

		$totals = $sut->totals();

		$this->assertIsArray( $totals );
		$this->assertArrayHasKey( 'discount', $totals );
		$this->assertMoneyValue( $totals['discount'], 10.00, 'EUR' );
	}

	/**
	 * GIVEN a WC_Cart where the discount total is zero
	 * WHEN totals() is called
	 * THEN the totals array does not include a discount key
	 */
	public function test_totals_omits_discount_when_discount_total_is_zero(): void {
		$wc_cart = Mockery::mock( WC_Cart::class );
		$wc_cart->allows( 'get_cart_contents_total' )->andReturn( '50.00' );
		$wc_cart->allows( 'get_discount_total' )->andReturn( '0.00' );
		$wc_cart->allows( 'get_shipping_total' )->andReturn( '0.00' );
		$wc_cart->allows( 'get_total_tax' )->andReturn( '0.00' );
		$wc_cart->allows( 'get_total' )->with( 'edit' )->andReturn( '50.00' );

		$sut = $this->make_sut( array(
			'cart_builder'   => $this->make_cart_builder( $wc_cart ),
			'store_currency' => $this->make_currency( 'USD' ),
		) );

		$totals = $sut->totals();

		$this->assertIsArray( $totals );
		$this->assertArrayNotHasKey( 'discount', $totals );
	}

	// -------------------------------------------------------------------------
	// Group 6 — to_array(): items
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a single StoreCartItem with known schema fields and real_price
	 * WHEN to_array() is called
	 * THEN the items array contains one entry with correct item_id, variant_id, name,
	 *      quantity, and a price with the store currency and formatted decimal value
	 */
	public function test_to_array_items_contain_schema_fields_and_store_price(): void {
		$schema = $this->make_cart_item_schema( array(
			'item_id'    => 'sku-42',
			'variant_id' => 'var-7',
			'name'       => 'Test Product',
			'quantity'   => 3,
		) );

		$store_item = $this->make_store_item( $schema, 12.50 );

		$sut = $this->make_sut( array(
			'store_items'    => array( $store_item ),
			'store_currency' => $this->make_currency( 'USD' ),
		) );

		$result = $sut->to_array();

		$this->assertArrayHasKey( 'items', $result );
		$this->assertCount( 1, $result['items'] );

		$item = $result['items'][0];
		$this->assertSame( 'sku-42', $item['item_id'] );
		$this->assertSame( 'var-7', $item['variant_id'] );
		$this->assertSame( 'Test Product', $item['name'] );
		$this->assertSame( 3, $item['quantity'] );
		$this->assertMoneyValue( $item['price'], 12.50, 'USD' );
	}

	/**
	 * GIVEN multiple StoreCartItems with different prices
	 * WHEN to_array() is called
	 * THEN each item entry uses its own real_price formatted to two decimal places
	 *
	 * @dataProvider item_price_formatting_provider
	 */
	public function test_to_array_item_price_is_formatted_as_two_decimal_string( float $real_price, string $expected_value ): void {
		$schema     = $this->make_cart_item_schema( array( 'quantity' => 1 ) );
		$store_item = $this->make_store_item( $schema, $real_price );

		$sut = $this->make_sut( array(
			'store_items'    => array( $store_item ),
			'store_currency' => $this->make_currency( 'USD' ),
		) );

		$result = $sut->to_array();
		$this->assertSame( $expected_value, $result['items'][0]['price']['value'] );
	}

	public function item_price_formatting_provider(): array {
		return array(
			'integer price formats to two decimals'     => array( 10.0, '10.00' ),
			'price with one decimal pads to two'        => array( 9.9, '9.90' ),
			'price already has two decimals'            => array( 12.50, '12.50' ),
			'price with many decimals is rounded'       => array( 7.999, '8.00' ),
			'zero price formats correctly'              => array( 0.0, '0.00' ),
		);
	}

	// -------------------------------------------------------------------------
	// Group 7 — to_array(): currency key always present
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a StorePayPalCart with EUR currency and one resolved item
	 * WHEN to_array() is called
	 * THEN each item price carries the store currency_code
	 * AND there is no root-level currency key
	 */
	public function test_to_array_currency_appears_in_item_prices(): void {
		$schema     = $this->make_cart_item_schema( array( 'quantity' => 1 ) );
		$store_item = $this->make_store_item( $schema, 15.0 );
		$sut        = $this->make_sut(
			array(
				'store_currency' => $this->make_currency( 'EUR' ),
				'store_items'    => array( $store_item ),
			)
		);

		$result = $sut->to_array();

		$this->assertArrayNotHasKey( 'currency', $result );
		$this->assertSame( 'EUR', $result['items'][0]['price']['currency_code'] );
	}

	// -------------------------------------------------------------------------
	// Group 8 — to_array(): customer conditional
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a PayPalCart with a customer that has a first and last name
	 * WHEN to_array() is called
	 * THEN customer is present in the result as a nested object
	 * AND customer.name contains given_name and surname
	 */
	public function test_to_array_includes_customer_when_present(): void {
		$customer_data = array(
			'name' => array(
				'given_name' => 'John',
				'surname'    => 'Doe',
			),
		);
		$validation    = new StoreValidation();
		$customer      = Customer::from_array( $customer_data, $validation );
		$paypal_cart   = $this->make_paypal_cart( array( 'customer' => $customer ) );

		$sut = $this->make_sut( array( 'paypal_cart' => $paypal_cart ) );

		$result = $sut->to_array();

		$this->assertArrayHasKey( 'customer', $result );
		$this->assertArrayHasKey( 'name', $result['customer'] );
		$this->assertSame( 'John', $result['customer']['name']['given_name'] );
		$this->assertSame( 'Doe', $result['customer']['name']['surname'] );
	}

	/**
	 * GIVEN a PayPalCart with no customer
	 * WHEN to_array() is called
	 * THEN customer is absent from the result
	 */
	public function test_to_array_omits_customer_when_absent(): void {
		$paypal_cart = $this->make_paypal_cart( array( 'customer' => null ) );
		$sut         = $this->make_sut( array( 'paypal_cart' => $paypal_cart ) );

		$result = $sut->to_array();

		$this->assertArrayNotHasKey( 'customer', $result );
	}

	// -------------------------------------------------------------------------
	// Group 9 — to_array(): shipping_address conditional
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a PayPalCart with a shipping address that has a non-empty country_code
	 * WHEN to_array() is called
	 * THEN shipping_address is present in the result
	 */
	public function test_to_array_includes_shipping_address_when_country_code_present(): void {
		$validation      = new StoreValidation();
		$address         = Address::from_array( array( 'country_code' => 'US', 'address_line_1' => '123 Main St' ), $validation );
		$paypal_cart     = $this->make_paypal_cart( array( 'shipping_address' => $address ) );

		$sut = $this->make_sut( array( 'paypal_cart' => $paypal_cart ) );

		$result = $sut->to_array();

		$this->assertArrayHasKey( 'shipping_address', $result );
		$this->assertSame( 'US', $result['shipping_address']['country_code'] );
	}

	/**
	 * GIVEN a PayPalCart with no shipping address
	 * WHEN to_array() is called
	 * THEN shipping_address is absent from the result
	 */
	public function test_to_array_omits_shipping_address_when_country_code_is_empty(): void {
		$paypal_cart = $this->make_paypal_cart( array( 'shipping_address' => null ) );
		$sut         = $this->make_sut( array( 'paypal_cart' => $paypal_cart ) );

		$result = $sut->to_array();

		$this->assertArrayNotHasKey( 'shipping_address', $result );
	}

	// -------------------------------------------------------------------------
	// Group 10 — to_array(): billing_address conditional
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a PayPalCart with a billing address that has a non-empty country_code
	 * WHEN to_array() is called
	 * THEN billing_address is present in the result
	 */
	public function test_to_array_includes_billing_address_when_country_code_present(): void {
		$validation  = new StoreValidation();
		$address     = Address::from_array( array( 'country_code' => 'DE', 'address_line_1' => 'Musterstr. 1' ), $validation );
		$paypal_cart = $this->make_paypal_cart( array( 'billing_address' => $address ) );

		$sut = $this->make_sut( array( 'paypal_cart' => $paypal_cart ) );

		$result = $sut->to_array();

		$this->assertArrayHasKey( 'billing_address', $result );
		$this->assertSame( 'DE', $result['billing_address']['country_code'] );
	}

	/**
	 * GIVEN a PayPalCart with no billing address
	 * WHEN to_cart() is called
	 * THEN billing_address is absent from the result
	 */
	public function test_to_array_omits_billing_address_when_country_code_is_empty(): void {
		$paypal_cart = $this->make_paypal_cart( array( 'billing_address' => null ) );
		$sut         = $this->make_sut( array( 'paypal_cart' => $paypal_cart ) );

		$result = $sut->to_array();

		$this->assertArrayNotHasKey( 'billing_address', $result );
	}

	// -------------------------------------------------------------------------
	// Group 11 — to_array(): totals conditional
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a valid WC_Cart with non-zero totals and no pricing issues
	 * WHEN to_array() is called
	 * THEN totals is present in the result
	 */
	public function test_to_array_includes_totals_when_totals_are_available(): void {
		$wc_cart = Mockery::mock( WC_Cart::class );
		$wc_cart->allows( 'get_cart_contents_total' )->andReturn( '100.00' );
		$wc_cart->allows( 'get_discount_total' )->andReturn( '0.00' );
		$wc_cart->allows( 'get_shipping_total' )->andReturn( '10.00' );
		$wc_cart->allows( 'get_total_tax' )->andReturn( '0.00' );
		$wc_cart->allows( 'get_total' )->with( 'edit' )->andReturn( '110.00' );

		$sut = $this->make_sut( array(
			'cart_builder'   => $this->make_cart_builder( $wc_cart ),
			'store_currency' => $this->make_currency( 'USD' ),
		) );

		$result = $sut->to_array();

		$this->assertArrayHasKey( 'totals', $result );
		$this->assertIsArray( $result['totals'] );
	}

	/**
	 * GIVEN the cart builder returned a WP_Error (no WC_Cart)
	 * WHEN to_array() is called
	 * THEN totals is absent from the result
	 */
	public function test_to_array_omits_totals_when_wc_cart_is_null(): void {
		$wp_error = Mockery::mock( WP_Error::class );
		$sut      = $this->make_sut( array( 'cart_builder' => $this->make_cart_builder( $wp_error ) ) );

		$result = $sut->to_array();

		$this->assertArrayNotHasKey( 'totals', $result );
	}

	/**
	 * GIVEN a WC_Cart is available but validation records a pricing issue
	 * WHEN to_array() is called
	 * THEN totals is absent from the result
	 */
	public function test_to_array_omits_totals_when_pricing_issue_exists(): void {
		$wc_cart    = Mockery::mock( WC_Cart::class );
		$validation = new StoreValidation();
		$validation->add_price_mismatch( 'Price mismatch.' );

		$sut = $this->make_sut( array(
			'cart_builder' => $this->make_cart_builder( $wc_cart ),
			'validation'   => $validation,
		) );

		$result = $sut->to_array();

		$this->assertArrayNotHasKey( 'totals', $result );
	}
}
