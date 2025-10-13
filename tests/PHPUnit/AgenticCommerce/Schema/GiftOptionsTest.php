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

	/**
	 * @dataProvider boolean_field_provider
	 */
	public function test_boolean_fields_store_and_return_value( string $field_name, string $getter_method ): void {
		$data    = array( $field_name => true );
		$options = GiftOptions::from_array( $data );

		$this->assertTrue( $options->$getter_method() );
	}

	/**
	 * @dataProvider boolean_field_provider
	 */
	public function test_boolean_fields_return_false_when_missing( string $field_name, string $getter_method ): void {
		$data    = array();
		$options = GiftOptions::from_array( $data );

		$this->assertFalse( $options->$getter_method() );
	}

	public function boolean_field_provider(): array {
		return array(
			'is_gift'   => array( 'is_gift', 'is_gift' ),
			'gift_wrap' => array( 'gift_wrap', 'gift_wrap' ),
		);
	}

	/**
	 * Tests that GiftOptions stores and returns the sender_name.
	 */
	public function test_sender_name_accessor(): void {
		$data    = array( 'sender_name' => 'John Smith' );
		$options = GiftOptions::from_array( $data );

		$this->assertSame( 'John Smith', $options->sender_name() );
	}

	/**
	 * Tests that GiftOptions stores and returns the gift_message.
	 */
	public function test_gift_message_accessor(): void {
		$data    = array( 'gift_message' => 'Happy Birthday! Hope you enjoy this gift.' );
		$options = GiftOptions::from_array( $data );

		$this->assertSame( 'Happy Birthday! Hope you enjoy this gift.', $options->gift_message() );
	}

	/**
	 * Tests that GiftOptions stores and returns the delivery_date.
	 */
	public function test_delivery_date_accessor(): void {
		$data    = array( 'delivery_date' => '2024-12-25T09:00:00Z' );
		$options = GiftOptions::from_array( $data );

		$this->assertSame( '2024-12-25T09:00:00Z', $options->delivery_date() );
	}

	/**
	 * @dataProvider optional_field_provider
	 */
	public function test_optional_fields_return_null_when_missing( string $field_name, string $getter_method ): void {
		$data    = array();
		$options = GiftOptions::from_array( $data );

		$this->assertNull( $options->$getter_method() );
	}

	public function optional_field_provider(): array {
		return array(
			'sender_name'   => array( 'sender_name', 'sender_name' ),
			'gift_message'  => array( 'gift_message', 'gift_message' ),
			'delivery_date' => array( 'delivery_date', 'delivery_date' ),
			'recipient'     => array( 'recipient', 'recipient' ),
		);
	}

	/**
	 * Tests that GiftOptions stores and returns the recipient object.
	 */
	public function test_recipient_accessor(): void {
		$data    = array(
			'recipient' => array(
				'name'  => 'Mary Johnson',
				'email' => 'mary@example.com',
			),
		);
		$options = GiftOptions::from_array( $data );

		$recipient = $options->recipient();

		$this->assertIsArray( $recipient );
		$this->assertSame( 'Mary Johnson', $recipient['name'] );
		$this->assertSame( 'mary@example.com', $recipient['email'] );
	}

	/**
	 * Tests that gift_message exceeding 500 characters produces validation issue.
	 */
	public function test_gift_message_exceeds_max_length(): void {
		$data = array(
			'gift_message' => str_repeat( 'a', 501 ),
		);

		$options    = GiftOptions::from_array( $data );
		$issues     = $options->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'gift_message', $issue_data['field'] );
	}

	/**
	 * Tests that gift_message with exactly 500 characters is valid.
	 */
	public function test_gift_message_at_max_length_is_valid(): void {
		$data = array(
			'gift_message' => str_repeat( 'a', 500 ),
		);

		$options = GiftOptions::from_array( $data );
		$issues  = $options->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that invalid email format in recipient produces validation issue.
	 */
	public function test_recipient_invalid_email_format(): void {
		$data = array(
			'recipient' => array(
				'name'  => 'Mary Johnson',
				'email' => 'not-an-email',
			),
		);

		$options    = GiftOptions::from_array( $data );
		$issues     = $options->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'recipient.email', $issue_data['field'] );
	}

	/**
	 * Tests that invalid RFC3339 format in delivery_date produces validation issue.
	 */
	public function test_delivery_date_invalid_format(): void {
		$data = array(
			'delivery_date' => '2024-12-25',
		);

		$options    = GiftOptions::from_array( $data );
		$issues     = $options->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'delivery_date', $issue_data['field'] );
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
			'sender_name with array'  => array( 'sender_name', array( 'name' ), 'sender_name', null ),
			'sender_name with int'    => array( 'sender_name', 123, 'sender_name', null ),
			'gift_message with array' => array( 'gift_message', array( 'msg' ), 'gift_message', null ),
			'delivery_date with int'  => array( 'delivery_date', 20241225, 'delivery_date', null ),
			'recipient with string'   => array( 'recipient', 'not-an-array', 'recipient', null ),
		);
	}

	/**
	 * Tests that empty strings are treated as missing values.
	 *
	 * @dataProvider empty_string_provider
	 */
	public function test_empty_strings_treated_as_null( string $field_name, string $getter_method ): void {
		$data    = array( $field_name => '' );
		$options = GiftOptions::from_array( $data );

		$this->assertNull( $options->$getter_method() );
	}

	public function empty_string_provider(): array {
		return array(
			'sender_name'   => array( 'sender_name', 'sender_name' ),
			'gift_message'  => array( 'gift_message', 'gift_message' ),
			'delivery_date' => array( 'delivery_date', 'delivery_date' ),
		);
	}

	/**
	 * Tests that recipient with missing email field is stored without validation error.
	 */
	public function test_recipient_with_missing_email(): void {
		$data = array(
			'recipient' => array(
				'name' => 'Mary Johnson',
			),
		);

		$options   = GiftOptions::from_array( $data );
		$recipient = $options->recipient();
		$issues    = $options->validate();

		$this->assertIsArray( $recipient );
		$this->assertSame( 'Mary Johnson', $recipient['name'] );
		$this->assertNull( $recipient['email'] );
		$this->assertEmpty( $issues, 'Missing email should not produce validation error' );
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

	/**
	 * Tests that whitespace-only strings are treated as empty.
	 *
	 * @dataProvider whitespace_string_provider
	 */
	public function test_whitespace_only_strings_treated_as_null( string $field_name, string $getter_method ): void {
		$data    = array( $field_name => '   ' );
		$options = GiftOptions::from_array( $data );

		$this->assertNull( $options->$getter_method() );
	}

	public function whitespace_string_provider(): array {
		return array(
			'sender_name'   => array( 'sender_name', 'sender_name' ),
			'gift_message'  => array( 'gift_message', 'gift_message' ),
			'delivery_date' => array( 'delivery_date', 'delivery_date' ),
		);
	}

	/**
	 * Tests that to_array() reconstructs original invalid input exactly.
	 */
	public function test_to_array_preserves_invalid_data(): void {
		$data = array(
			'gift_message'  => str_repeat( 'a', 501 ),
			'recipient'     => array(
				'name'  => 'Mary Johnson',
				'email' => 'invalid-email',
			),
			'delivery_date' => 'not-a-date',
		);

		$options = GiftOptions::from_array( $data );

		$this->assertSame( $data, $options->to_array(), 'to_array() must preserve invalid input exactly' );
	}
}
