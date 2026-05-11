<?php
/**
 * Tests for StoreCartItem.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\StoreData;

use Mockery;
use WC_Product;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\StoreSync\StoreSyncTestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem
 */
class StoreCartItemTest extends StoreSyncTestCase {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Creates a CartItem schema stub with an optional price Money instance.
	 *
	 * @param Money|null $price The price to return from price().
	 * @return CartItem&\Mockery\MockInterface
	 */
	private function make_cart_item_schema( ?Money $price = null ): CartItem {
		$stub = Mockery::mock( CartItem::class );
		$stub->allows( 'price' )->andReturn( $price );
		return $stub;
	}

	/**
	 * Creates a WC_Product stub returning the given price string.
	 *
	 * @param string $price_string The value returned by get_price().
	 * @return WC_Product&\Mockery\MockInterface
	 */
	private function make_product( string $price_string ): WC_Product {
		$stub = Mockery::mock( WC_Product::class );
		$stub->allows( 'get_price' )->andReturn( $price_string );
		return $stub;
	}

	/**
	 * Creates a StoreCurrencyValue stub returning the given currency code.
	 *
	 * @param string $currency The currency code to return.
	 * @return StoreCurrencyValue&\Mockery\MockInterface
	 */
	private function make_currency( string $currency ): StoreCurrencyValue {
		$stub = Mockery::mock( StoreCurrencyValue::class );
		$stub->allows( 'value' )->andReturn( $currency );
		return $stub;
	}

	/**
	 * Creates a Money instance with the given value and currency via from_array().
	 *
	 * @param string $value    Decimal string (e.g., '9.99').
	 * @param string $currency ISO 4217 code.
	 * @return Money
	 */
	private function make_money( string $value, string $currency ): Money {
		return Money::from_array(
			array( 'currency_code' => $currency, 'value' => $value ),
			new StoreValidation()
		);
	}

	// -------------------------------------------------------------------------
	// Group 1 — real_price()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a WC_Product returning a price string
	 * WHEN real_price() is called
	 * THEN a float representation of the product price is returned
	 */
	public function test_real_price_returns_float_from_product_get_price(): void {
		$schema  = $this->make_cart_item_schema();
		$product = $this->make_product( '19.99' );
		$sut     = new StoreCartItem( $schema, $product, $this->make_currency( 'USD' ) );

		$this->assertSame( 19.99, $sut->real_price() );
	}

	// -------------------------------------------------------------------------
	// Group 2 — assumed_price()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a CartItem schema with no price
	 * WHEN assumed_price() is called
	 * THEN null is returned
	 */
	public function test_assumed_price_returns_null_when_schema_has_no_price(): void {
		$schema  = $this->make_cart_item_schema( null );
		$product = $this->make_product( '9.99' );
		$sut     = new StoreCartItem( $schema, $product, $this->make_currency( 'USD' ) );

		$this->assertNull( $sut->assumed_price() );
	}

	/**
	 * GIVEN a CartItem schema with a Money price
	 * WHEN assumed_price() is called
	 * THEN the Money instance from the schema is returned
	 */
	public function test_assumed_price_returns_money_from_schema(): void {
		$money   = $this->make_money( '9.99', 'USD' );
		$schema  = $this->make_cart_item_schema( $money );
		$product = $this->make_product( '9.99' );
		$sut     = new StoreCartItem( $schema, $product, $this->make_currency( 'USD' ) );

		$this->assertSame( $money, $sut->assumed_price() );
	}

	// -------------------------------------------------------------------------
	// Group 3 — is_price_correct()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a CartItem schema with no assumed price
	 * WHEN is_price_correct() is called
	 * THEN true is returned because no price claim was made
	 */
	public function test_is_price_correct_returns_true_when_no_assumed_price(): void {
		$schema  = $this->make_cart_item_schema( null );
		$product = $this->make_product( '9.99' );
		$sut     = new StoreCartItem( $schema, $product, $this->make_currency( 'USD' ) );

		$this->assertTrue( $sut->is_price_correct() );
	}

	/**
	 * GIVEN a CartItem with an assumed price that matches the store price
	 * WHEN is_price_correct() is called
	 * THEN true is returned
	 *
	 * @dataProvider matching_price_provider
	 *
	 * @param string $product_price  The WC_Product get_price() return value.
	 * @param string $assumed_value  The Money value the agent provided.
	 */
	public function test_is_price_correct_returns_true_when_assumed_price_matches_store_price(
		string $product_price,
		string $assumed_value
	): void {
		$money   = $this->make_money( $assumed_value, 'USD' );
		$schema  = $this->make_cart_item_schema( $money );
		$product = $this->make_product( $product_price );
		$sut     = new StoreCartItem( $schema, $product, $this->make_currency( 'USD' ) );

		$this->assertTrue( $sut->is_price_correct() );
	}

	public function matching_price_provider(): array {
		return array(
			'exact match two decimals'                  => array( '9.99', '9.99' ),
			'product integer, assumed decimal'          => array( '10', '10.00' ),
			'product has trailing zeros, assumed clean' => array( '25.50', '25.50' ),
			'product float, assumed same float string'  => array( '0.99', '0.99' ),
		);
	}

	/**
	 * GIVEN a CartItem with an assumed price that differs from the store price
	 * WHEN is_price_correct() is called
	 * THEN false is returned
	 *
	 * @dataProvider mismatched_price_provider
	 *
	 * @param string $product_price  The WC_Product get_price() return value.
	 * @param string $assumed_value  The Money value the agent provided.
	 */
	public function test_is_price_correct_returns_false_when_assumed_price_differs_from_store_price(
		string $product_price,
		string $assumed_value
	): void {
		$money   = $this->make_money( $assumed_value, 'USD' );
		$schema  = $this->make_cart_item_schema( $money );
		$product = $this->make_product( $product_price );
		$sut     = new StoreCartItem( $schema, $product, $this->make_currency( 'USD' ) );

		$this->assertFalse( $sut->is_price_correct() );
	}

	public function mismatched_price_provider(): array {
		return array(
			'price raised by one cent'   => array( '10.00', '9.99' ),
			'price lowered by one cent'  => array( '9.99', '10.00' ),
			'significantly different'    => array( '49.99', '39.99' ),
			'zero vs non-zero'           => array( '0.00', '9.99' ),
		);
	}

	/**
	 * GIVEN a product price that would differ from an assumed price due to floating-point
	 *       representation (e.g., 0.1 + 0.2 != 0.3 as floats)
	 * WHEN is_price_correct() is called
	 * THEN the comparison uses formatted decimals, avoiding floating-point drift
	 */
	public function test_is_price_correct_avoids_floating_point_drift(): void {
		// 0.1 + 0.2 as a float would be 0.30000000000000004; the store returns '0.30'
		$money   = $this->make_money( '0.30', 'USD' );
		$schema  = $this->make_cart_item_schema( $money );
		$product = $this->make_product( '0.30' );
		$sut     = new StoreCartItem( $schema, $product, $this->make_currency( 'USD' ) );

		$this->assertTrue( $sut->is_price_correct() );
	}

	// -------------------------------------------------------------------------
	// Group 4 — is_currency_correct()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a CartItem schema with no assumed price (no currency claim)
	 * WHEN is_currency_correct() is called
	 * THEN true is returned
	 */
	public function test_is_currency_correct_returns_true_when_no_assumed_price(): void {
		$schema  = $this->make_cart_item_schema( null );
		$product = $this->make_product( '9.99' );
		$sut     = new StoreCartItem( $schema, $product, $this->make_currency( 'USD' ) );

		$this->assertTrue( $sut->is_currency_correct() );
	}

	/**
	 * GIVEN a CartItem with an assumed price in the same currency as the store
	 * WHEN is_currency_correct() is called
	 * THEN true is returned
	 */
	public function test_is_currency_correct_returns_true_when_assumed_currency_matches_store(): void {
		$money   = $this->make_money( '9.99', 'EUR' );
		$schema  = $this->make_cart_item_schema( $money );
		$product = $this->make_product( '9.99' );
		$sut     = new StoreCartItem( $schema, $product, $this->make_currency( 'EUR' ) );

		$this->assertTrue( $sut->is_currency_correct() );
	}

	/**
	 * GIVEN a CartItem with an assumed price in a different currency than the store
	 * WHEN is_currency_correct() is called
	 * THEN false is returned
	 */
	public function test_is_currency_correct_returns_false_when_assumed_currency_differs_from_store(): void {
		$money   = $this->make_money( '9.99', 'USD' );
		$schema  = $this->make_cart_item_schema( $money );
		$product = $this->make_product( '9.99' );
		$sut     = new StoreCartItem( $schema, $product, $this->make_currency( 'EUR' ) );

		$this->assertFalse( $sut->is_currency_correct() );
	}

	// -------------------------------------------------------------------------
	// Group 5 — schema()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a StoreCartItem constructed with a CartItem schema
	 * WHEN schema() is called
	 * THEN the exact same CartItem instance is returned
	 */
	public function test_schema_returns_injected_cart_item(): void {
		$schema  = $this->make_cart_item_schema();
		$product = $this->make_product( '9.99' );
		$sut     = new StoreCartItem( $schema, $product, $this->make_currency( 'USD' ) );

		$this->assertSame( $schema, $sut->schema() );
	}
}
