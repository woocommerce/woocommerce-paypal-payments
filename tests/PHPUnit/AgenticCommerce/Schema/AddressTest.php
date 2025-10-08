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
	 * Tests that Address correctly validates and normalizes country codes.
	 *
	 * @dataProvider valid_country_code_provider
	 */
	public function test_valid_country_codes( string $input, string $expected ): void {
		$data    = array( 'country_code' => $input );
		$address = Address::from_array( $data );

		$this->assertEmpty( $address->validate() );
		$this->assertSame( $expected, $address->country_code() );
	}

	public function valid_country_code_provider(): array {
		return array(
			'uppercase_us'     => array(
				'input'    => 'US',
				'expected' => 'US',
			),
			'lowercase_us'     => array(
				'input'    => 'us',
				'expected' => 'US',
			),
			'lowercase_de'     => array(
				'input'    => 'de',
				'expected' => 'DE',
			),
			'with_whitespace'  => array(
				'input'    => '  GB  ',
				'expected' => 'GB',
			),
		);
	}

	/**
	 * Tests that country codes with invalid length produce validation issues.
	 */
	public function test_country_code_too_long_produces_validation_issue(): void {
		$data    = array( 'country_code' => 'USA' );
		$address = Address::from_array( $data );
		$issues  = $address->validate();

		$this->assertCount( 1, $issues );
		$this->assertSame( '', $address->country_code() );
	}
}
