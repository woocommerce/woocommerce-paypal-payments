<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers Address
 */
class AddressTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return Address::class;
	}

	protected function get_valid_data(): array {
		return array(
			'country_code' => 'US',
		);
	}

	/**
	 * Tests that Address has a getter for country_code that returns the normalized uppercase value.
	 */
	public function test_country_code_getter_returns_uppercase(): void {
		$data    = array( 'country_code' => 'US' );
		$address = Address::from_array( $data );

		$this->assertSame( 'US', $address->country_code() );
	}

	/**
	 * Tests that lowercase country codes are normalized to uppercase.
	 */
	public function test_country_code_normalized_to_uppercase(): void {
		$data    = array( 'country_code' => 'us' );
		$address = Address::from_array( $data );

		$this->assertEmpty( $address->validate() );
		$this->assertSame( 'US', $address->country_code() );
	}

	/**
	 * Tests that different country codes are properly stored.
	 */
	public function test_country_code_de_is_stored(): void {
		$data    = array( 'country_code' => 'de' );
		$address = Address::from_array( $data );

		$this->assertEmpty( $address->validate() );
		$this->assertSame( 'DE', $address->country_code() );
	}
}
