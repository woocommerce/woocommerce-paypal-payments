<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers GiftOptions
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
		$this->addToAssertionCount( 1 );
	}

	public function test_optional_fields(): void {
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
		$this->assertFieldFormat( 'delivery_date', $this->getIsoDateFormatCases() );
		$this->assertFieldFormat( 'recipient.email', $this->getEmailAddressFormatCases( true ) );
	}


	/**
	 * @dataProvider invalid_type_provider
	 */
	public function test_fields_reject_invalid_types( string $field_name, $invalid_value, string $getter_method, $expected_default ): void {
		$data    = array( $field_name => $invalid_value );
		$options = GiftOptions::from_array( $data );

		$this->assertSame( $expected_default, $options->$getter_method() );
	}

	public function invalid_type_provider(): array {
		return array(
			'is_gift with string'     => array( 'is_gift', 'true', 'is_gift', false ),
			'is_gift with integer'    => array( 'is_gift', 1, 'is_gift', false ),
			'gift_wrap with string'   => array( 'gift_wrap', 'yes', 'gift_wrap', false ),
			'sender_name with array'  => array(
				'sender_name',
				array( 'name' ),
				'sender_name',
				null,
			),
			'sender_name with int'    => array( 'sender_name', 123, 'sender_name', null ),
			'gift_message with array' => array(
				'gift_message',
				array( 'msg' ),
				'gift_message',
				null,
			),
			'recipient with string'   => array( 'recipient', 'not-an-array', 'recipient', null ),
		);
	}

	/**
	 * Tests that multiple validation errors are all returned together.
	 */
	public function test_multiple_validation_errors_returned_together(): void {
		$data = array(
			'gift_message'  => str_repeat( 'a', 501 ),
			'recipient'     => array(
				'name'  => 'Mary Johnson',
				'email' => 'invalid-email',
			),
			'delivery_date' => 'not-a-date',
		);

		$options = GiftOptions::from_array( $data );
		$issues  = $options->validate();

		$this->assertCount( 3, $issues, 'Should return all validation errors at once' );

		$fields = array_map(
			function ( $issue ) {
				return $issue->to_array()['field'];
			},
			$issues
		);

		$this->assertContains( 'gift_message', $fields );
		$this->assertContains( 'recipient.email', $fields );
		$this->assertContains( 'delivery_date', $fields );
	}
}
