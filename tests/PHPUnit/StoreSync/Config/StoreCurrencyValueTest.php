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
