<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync;

use WooCommerce\PayPalCommerce\TestCase;

/**
 * Base class that provides some common assertions
 */
abstract class StoreSyncTestCase extends TestCase {
	/**
	 * Asserts that the provided value is a valid money-schema
	 *
	 * @param mixed       $entity   An array with the keys "value" and "currency".
	 * @param null|float  $value    The expected monetary value.
	 * @param null|string $currency The expected currency; only verified when a non-empty string
	 *                              is provided.
	 * @return void
	 */
	protected function assertMoneyValue( $entity, ?float $value = null, ?string $currency = null ): void {
		// Verify array structure.
		$this->assertIsArray( $entity, 'Expected an array with money entity.' );
		$this->assertArrayHasKey( 'value', $entity, 'Money entity has no "value" key.' );
		$this->assertArrayHasKey( 'currency_code', $entity, 'Money entity has no "currency_code" key.' );

		// Verify data types.
		$this->assertIsString( $entity['value'], 'The "value" item should be a numeric string.' );
		$this->assertIsString( $entity['currency_code'], 'The "currency_code" item should be a string.' );

		// Verify the number format of the value (2 decimals)
		// Will catch invalid conversions, eg 0.010000000000001563 instead of 0.01
		$this->assertMatchesRegularExpression( '/^\d+\.\d{2}$/', $entity['value'], 'The "value" should match format "XX.XX"' );

		// Verify the numerical value.
		if ( null !== $value ) {
			$value_string = number_format( $value, 2 );
			$this->assertEquals( $value_string, $entity['value'], 'Unexpected monetary value.' );
		}

		// Verify currency, if provided.
		if ( $currency ) {
			$this->assertEquals( $currency, $entity['currency_code'], 'Unexpected "currency_code" value.' );
		}
	}
}
