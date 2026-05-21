<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\GiftOptions
 */
class GiftOptionsTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return GiftOptions::class;
	}

	protected function get_valid_data(): array {
		return array(
			'is_gift'       => true,
			'recipient'     => array(
				'name'  => 'Mary Johnson',
				'email' => 'mary@example.com',
			),
			'delivery_date' => '2024-12-25T09:00:00Z',
			'sender_name'   => 'John Smith',
			'gift_message'  => 'Happy Birthday! Hope you enjoy this gift.',
			'gift_wrap'     => true,
		);
	}

	protected function get_expected_data(): array {
		return array(
			'is_gift'         => true,
			'recipient.name'  => 'Mary Johnson',
			'recipient.email' => 'mary@example.com',
			'delivery_date'   => '2024-12-25T09:00:00Z',
			'sender_name'     => 'John Smith',
			'gift_message'    => 'Happy Birthday! Hope you enjoy this gift.',
			'gift_wrap'       => true,
		);
	}

	protected function get_data_types(): array {
		return array(
			'is_gift'       => array( 'type' => 'bool', 'default' => false ),
			'gift_wrap'     => array( 'type' => 'bool', 'default' => false ),
			'recipient'     => array( 'type' => 'array', 'valid' => array() ),
			'delivery_date' => 'timestamp',
			'sender_name'   => 'string',
			'gift_message'  => 'string',
		);
	}

	public function test_required_fields(): void {
		// GiftOptions has no required fields - all fields are optional.

		// Boolean fields have default behavior, so test separately.
		$this->assertBooleanFieldDefaultState( 'is_gift' );
		$this->assertBooleanFieldDefaultState( 'gift_wrap' );

		// Other optional fields return null.
		$this->assertOptionalField( 'recipient' );
		$this->assertOptionalField( 'delivery_date' );
		$this->assertOptionalField( 'sender_name' );
		$this->assertOptionalField( 'gift_message' );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'sender_name', 'John Smith' );
		$this->assertWhitespaceTrimming( 'gift_message', 'Happy Birthday' );
		$this->assertWhitespaceTrimming( 'delivery_date', '2024-12-25T09:00:00Z' );

		$this->assertEmptyStringPreserved( 'sender_name' );
		$this->assertEmptyStringPreserved( 'gift_message' );

		$this->assertStringFieldMaxLength( 'gift_message', 500 );

		// Nested fields
		$this->assertWhitespaceTrimming( 'recipient.name', 'John' );
		$this->assertWhitespaceTrimming( 'recipient.email', 'john@example.com' );
		$this->assertEmptyStringPreserved( 'recipient.name' );
		$this->assertEmptyStringPreserved( 'recipient.email' );
	}

	public function test_field_format_validation(): void {
		$this->assertFieldFormat( 'delivery_date', $this->get_iso_date_format_cases() );
		$this->assertFieldFormat( 'recipient.email', $this->get_email_address_format_cases( true ) );
	}
}
