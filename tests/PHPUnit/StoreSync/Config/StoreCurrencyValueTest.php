<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Config;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue
 */
class StoreCurrencyValueTest extends TestCase {

	/**
	 * GIVEN the StoreCurrencyValue class with no required constructor arguments
	 * WHEN the class is instantiated
	 * THEN an instance is created successfully
	 */
	public function test_can_be_instantiated(): void {
		$testee = new StoreCurrencyValue();

		$this->assertInstanceOf( StoreCurrencyValue::class, $testee );
	}

	/**
	 * GIVEN a StoreCurrencyValue instance
	 * WHEN the value() method is inspected
	 * THEN it exists and is callable on the public API
	 */
	public function test_value_method_is_callable(): void {
		$testee = new StoreCurrencyValue();

		$this->assertTrue( is_callable( array( $testee, 'value' ) ) );
	}

	/**
	 * GIVEN the WooCommerce store is configured with USD as the active currency
	 * WHEN value() is called
	 * THEN it returns the string 'USD'
	 */
	public function test_value_returns_woocommerce_currency_code(): void {
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$testee = new StoreCurrencyValue();

		$this->assertSame( 'USD', $testee->value() );
	}
}
