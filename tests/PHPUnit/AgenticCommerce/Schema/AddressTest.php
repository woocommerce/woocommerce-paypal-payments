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

	/**
	 * Tests that country codes that are too short produce validation issues.
	 */
	public function test_country_code_too_short_produces_validation_issue(): void {
		$data    = array( 'country_code' => 'U' );
		$address = Address::from_array( $data );
		$issues  = $address->validate();

		$this->assertCount( 1, $issues );
		$this->assertSame( '', $address->country_code() );
	}

	/**
	 * Tests that missing country_code produces validation issue.
	 */
	public function test_missing_country_code(): void {
		$data    = array();
		$address = Address::from_array( $data );
		$issues  = $address->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( 'country_code', $issue_data['field'] );
	}

	/**
	 * Tests that address_line_1 is stored correctly.
	 */
	public function test_address_line_1_is_stored(): void {
		$data    = array(
			'country_code'   => 'US',
			'address_line_1' => '123 Main Street',
		);
		$address = Address::from_array( $data );

		$this->assertEmpty( $address->validate() );
		$this->assertSame( '123 Main Street', $address->address_line_1() );
	}

	/**
	 * Tests that address_line_1 returns null when not provided.
	 */
	public function test_address_line_1_returns_null_when_not_provided(): void {
		$data    = array( 'country_code' => 'US' );
		$address = Address::from_array( $data );

		$this->assertEmpty( $address->validate() );
		$this->assertNull( $address->address_line_1() );
	}

	/**
	 * Tests that address_line_1 exceeding max length produces validation issue.
	 */
	public function test_address_line_1_too_long_produces_validation_issue(): void {
		$data    = array(
			'country_code'   => 'US',
			'address_line_1' => str_repeat( 'A', 301 ),
		);
		$address = Address::from_array( $data );
		$issues  = $address->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( 'address_line_1', $issue_data['field'] );
	}

	/**
	 * Tests that address_line_2 is stored correctly.
	 */
	public function test_address_line_2_is_stored(): void {
		$data    = array(
			'country_code'   => 'US',
			'address_line_2' => 'Apt 4B',
		);
		$address = Address::from_array( $data );

		$this->assertEmpty( $address->validate() );
		$this->assertSame( 'Apt 4B', $address->address_line_2() );
	}
}
