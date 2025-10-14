<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;

/**
 * @covers CheckoutField
 */
class CheckoutFieldTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return CheckoutField::class;
	}

	protected function get_valid_data(): array {
		return array(
			'type'    => 'AGE_VERIFICATION_21_PLUS',
			'status'  => 'COMPLETED',
			'value'   => array(
				'confirmed'           => true,
				'verification_method' => 'self_declaration',
				'verification_date'   => '2024-06-24T14:30:00Z',
			),
			'context' => array(
				'display_name'    => 'Age Verification (21+)',
				'min_age'         => 21,
				'compliance_note' => 'Required by state law',
			),
		);
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'type' );
		$this->assertRequiredField( 'status' );
	}

	public function test_optional_fields(): void {
		$mandatory = array(
			'type'   => 'AGE_VERIFICATION_21_PLUS',
			'status' => 'PENDING',
		);

		$this->assertOptionalField( 'value', $mandatory );
		$this->assertOptionalField( 'context', $mandatory );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'type', 'GIFT_MESSAGE' );
		$this->assertWhitespaceTrimming( 'status', 'PENDING' );
	}

	/**
	 * Tests that CheckoutField stores and returns the type.
	 */
	public function test_type_accessor(): void {
		$data  = array(
			'type'   => 'AGE_VERIFICATION_21_PLUS',
			'status' => 'PENDING',
		);
		$field = CheckoutField::from_array( $data );

		$this->assertSame( 'AGE_VERIFICATION_21_PLUS', $field->type() );
	}

	/**
	 * Tests that CheckoutField stores and returns the status.
	 */
	public function test_status_accessor(): void {
		$data  = array(
			'type'   => 'GIFT_MESSAGE',
			'status' => 'COMPLETED',
		);
		$field = CheckoutField::from_array( $data );

		$this->assertSame( 'COMPLETED', $field->status() );
	}

	/**
	 * Tests that CheckoutField stores and returns the value object.
	 */
	public function test_value_accessor(): void {
		$data  = array(
			'type'   => 'AGE_VERIFICATION_21_PLUS',
			'status' => 'COMPLETED',
			'value'  => array(
				'confirmed'           => true,
				'verification_method' => 'self_declaration',
			),
		);
		$field = CheckoutField::from_array( $data );

		$value = $field->value();

		$this->assertIsArray( $value );
		$this->assertTrue( $value['confirmed'] );
		$this->assertSame( 'self_declaration', $value['verification_method'] );
	}

	/**
	 * Tests that CheckoutField stores and returns the context object.
	 */
	public function test_context_accessor(): void {
		$data  = array(
			'type'    => 'GIFT_MESSAGE',
			'status'  => 'PENDING',
			'context' => array(
				'display_name' => 'Gift Message',
				'max_length'   => 500,
			),
		);
		$field = CheckoutField::from_array( $data );

		$context = $field->context();

		$this->assertIsArray( $context );
		$this->assertSame( 'Gift Message', $context['display_name'] );
		$this->assertSame( 500, $context['max_length'] );
	}

	/**
	 * @dataProvider optional_field_provider
	 */
	public function test_optional_fields_return_null_when_missing( string $field_name, string $getter_method ): void {
		$data  = array(
			'type'   => 'GIFT_MESSAGE',
			'status' => 'PENDING',
		);
		$field = CheckoutField::from_array( $data );

		$this->assertNull( $field->$getter_method() );
	}

	public function optional_field_provider(): array {
		return array(
			'value'   => array( 'value', 'value' ),
			'context' => array( 'context', 'context' ),
		);
	}

	/**
	 * Tests that type field is required and produces validation issue when missing.
	 */
	public function test_type_missing_produces_validation_issue(): void {
		$data  = array( 'status' => 'PENDING' );
		$field = CheckoutField::from_array( $data );

		$issues     = $field->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'type', $issue_data['field'] );
	}

	/**
	 * Tests that status field is required and produces validation issue when missing.
	 */
	public function test_status_missing_produces_validation_issue(): void {
		$data  = array( 'type' => 'GIFT_MESSAGE' );
		$field = CheckoutField::from_array( $data );

		$issues     = $field->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'status', $issue_data['field'] );
	}

	/**
	 * @dataProvider valid_type_provider
	 */
	public function test_valid_types_accepted( string $type ): void {
		$data  = array(
			'type'   => $type,
			'status' => 'PENDING',
		);
		$field = CheckoutField::from_array( $data );

		$issues = $field->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( $type, $field->type() );
	}

	public function valid_type_provider(): array {
		return array(
			'age verification'      => array( 'AGE_VERIFICATION_21_PLUS' ),
			'gift message'          => array( 'GIFT_MESSAGE' ),
			'delivery instructions' => array( 'DELIVERY_INSTRUCTIONS' ),
			'gift recipient email'  => array( 'GIFT_RECIPIENT_EMAIL' ),
			'custom engraving'      => array( 'CUSTOM_ENGRAVING' ),
			'allergy information'   => array( 'ALLERGY_INFORMATION' ),
		);
	}

	/**
	 * @dataProvider valid_status_provider
	 */
	public function test_valid_status_values_accepted( string $status ): void {
		$data  = array(
			'type'   => 'GIFT_MESSAGE',
			'status' => $status,
		);
		$field = CheckoutField::from_array( $data );

		$issues = $field->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( $status, $field->status() );
	}

	public function valid_status_provider(): array {
		return array(
			'pending'   => array( 'PENDING' ),
			'completed' => array( 'COMPLETED' ),
			'rejected'  => array( 'REJECTED' ),
			'error'     => array( 'ERROR' ),
		);
	}

	/**
	 * Tests that invalid status value produces validation issue.
	 */
	public function test_invalid_status_value(): void {
		$data  = array(
			'type'   => 'GIFT_MESSAGE',
			'status' => 'INVALID_STATUS',
		);
		$field = CheckoutField::from_array( $data );

		$issues     = $field->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'status', $issue_data['field'] );
	}

	/**
	 * @dataProvider invalid_type_provider
	 */
	public function test_fields_reject_invalid_types( string $field_name, $invalid_value, string $getter_method, $expected_default ): void {
		$data  = array(
			'type'      => 'GIFT_MESSAGE',
			'status'    => 'PENDING',
			$field_name => $invalid_value,
		);
		$field = CheckoutField::from_array( $data );

		$this->assertSame( $expected_default, $field->$getter_method() );
	}

	public function invalid_type_provider(): array {
		return array(
			'type with array'      => array( 'type', array( 'type' ), 'type', '' ),
			'type with integer'    => array( 'type', 123, 'type', '' ),
			'status with array'    => array( 'status', array( 'status' ), 'status', 'ERROR' ),
			'status with integer'  => array( 'status', 456, 'status', 'ERROR' ),
			'value with string'    => array( 'value', 'value-string', 'value', null ),
			'value with integer'   => array( 'value', 789, 'value', null ),
			'context with string'  => array( 'context', 'context-string', 'context', null ),
			'context with integer' => array( 'context', 123, 'context', null ),
		);
	}

	/**
	 * Tests that empty strings are treated as invalid and return null.
	 *
	 * @dataProvider empty_string_provider
	 */
	public function test_empty_strings_are_treated_as_null( string $field_name, string $getter_method, string $default_value ): void {
		$data  = array(
			'type'      => 'GIFT_MESSAGE',
			'status'    => 'PENDING',
			$field_name => '',
		);
		$field = CheckoutField::from_array( $data );

		$this->assertEquals( $default_value, $field->$getter_method() );
	}

	public function empty_string_provider(): array {
		return array(
			'type'   => array( 'type', 'type', '' ),
			'status' => array( 'status', 'status', 'ERROR' ),
		);
	}

	/**
	 * Tests that leading/trailing whitespace is trimmed from string values.
	 *
	 * @dataProvider whitespace_trimming_provider
	 */
	public function test_string_values_are_trimmed( string $field_name, string $input_value, string $expected_value ): void {
		$data  = array(
			'type'      => 'GIFT_MESSAGE',
			'status'    => 'PENDING',
			$field_name => $input_value,
		);
		$field = CheckoutField::from_array( $data );

		$this->assertSame( $expected_value, $field->$field_name() );
	}

	public function whitespace_trimming_provider(): array {
		return array(
			'type with leading space'    => array(
				'type',
				' AGE_VERIFICATION_21_PLUS',
				'AGE_VERIFICATION_21_PLUS',
			),
			'type with trailing space'   => array(
				'type',
				'AGE_VERIFICATION_21_PLUS ',
				'AGE_VERIFICATION_21_PLUS',
			),
			'type with both spaces'      => array( 'type', '  GIFT_MESSAGE  ', 'GIFT_MESSAGE' ),
			'status with leading space'  => array( 'status', ' PENDING', 'PENDING' ),
			'status with trailing space' => array( 'status', 'COMPLETED ', 'COMPLETED' ),
			'status with both spaces'    => array( 'status', '  REJECTED  ', 'REJECTED' ),
		);
	}

	/**
	 * Tests that multiple validation errors are all returned together.
	 */
	public function test_multiple_validation_errors_returned_together(): void {
		$data = array(
			'type'   => '',
			'status' => 'INVALID_STATUS',
		);

		$field  = CheckoutField::from_array( $data );
		$issues = $field->validate();

		$this->assertCount( 2, $issues, 'Should return all validation errors at once' );

		$fields = array_map(
			function ( $issue ) {
				return $issue->to_array()['field'];
			},
			$issues
		);

		$this->assertContains( 'type', $fields );
		$this->assertContains( 'status', $fields );
	}

	/**
	 * Tests age verification field with complete value structure.
	 */
	public function test_age_verification_complete_structure(): void {
		$data = array(
			'type'    => 'AGE_VERIFICATION_21_PLUS',
			'status'  => 'COMPLETED',
			'value'   => array(
				'confirmed'           => true,
				'verification_method' => 'self_declaration',
				'verification_date'   => '2024-06-24T14:30:00Z',
			),
			'context' => array(
				'display_name'    => 'Age Verification (21+)',
				'description'     => 'We need to verify you are 21 or older',
				'min_age'         => 21,
				'compliance_note' => 'Required by state law for alcohol purchases',
			),
		);

		$field  = CheckoutField::from_array( $data );
		$issues = $field->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'AGE_VERIFICATION_21_PLUS', $field->type() );
		$this->assertSame( 'COMPLETED', $field->status() );

		$value = $field->value();
		$this->assertTrue( $value['confirmed'] );
		$this->assertSame( 'self_declaration', $value['verification_method'] );
		$this->assertSame( '2024-06-24T14:30:00Z', $value['verification_date'] );

		$context = $field->context();
		$this->assertSame( 'Age Verification (21+)', $context['display_name'] );
		$this->assertSame( 21, $context['min_age'] );
	}

	/**
	 * Tests gift message field with complete structure.
	 */
	public function test_gift_message_complete_structure(): void {
		$data = array(
			'type'    => 'GIFT_MESSAGE',
			'status'  => 'COMPLETED',
			'value'   => array(
				'message'     => 'Happy Birthday! Hope you enjoy this gift card.',
				'sender_name' => 'John Smith',
			),
			'context' => array(
				'display_name'      => 'Gift Message',
				'description'       => 'Personal message for the gift recipient',
				'max_length'        => 500,
				'character_counter' => true,
			),
		);

		$field  = CheckoutField::from_array( $data );
		$issues = $field->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'GIFT_MESSAGE', $field->type() );
		$this->assertSame( 'COMPLETED', $field->status() );

		$value = $field->value();
		$this->assertSame( 'Happy Birthday! Hope you enjoy this gift card.', $value['message'] );
		$this->assertSame( 'John Smith', $value['sender_name'] );

		$context = $field->context();
		$this->assertSame( 'Gift Message', $context['display_name'] );
		$this->assertSame( 500, $context['max_length'] );
		$this->assertTrue( $context['character_counter'] );
	}

	/**
	 * Tests delivery instructions field with complete structure.
	 */
	public function test_delivery_instructions_complete_structure(): void {
		$data = array(
			'type'    => 'DELIVERY_INSTRUCTIONS',
			'status'  => 'COMPLETED',
			'value'   => array(
				'instructions'  => 'Leave at front door, ring doorbell',
				'access_code'   => '1234',
				'contact_phone' => '+1-555-123-4567',
			),
			'context' => array(
				'display_name' => 'Special Delivery Instructions',
				'description'  => 'Additional instructions for delivery driver',
				'max_length'   => 200,
				'examples'     => array(
					'Leave at front door',
					'Ring doorbell',
					'Call upon arrival',
				),
			),
		);

		$field  = CheckoutField::from_array( $data );
		$issues = $field->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'DELIVERY_INSTRUCTIONS', $field->type() );
		$this->assertSame( 'COMPLETED', $field->status() );

		$value = $field->value();
		$this->assertSame( 'Leave at front door, ring doorbell', $value['instructions'] );
		$this->assertSame( '1234', $value['access_code'] );
		$this->assertSame( '+1-555-123-4567', $value['contact_phone'] );

		$context = $field->context();
		$this->assertSame( 'Special Delivery Instructions', $context['display_name'] );
		$this->assertSame( 200, $context['max_length'] );
		$this->assertIsArray( $context['examples'] );
	}

	/**
	 * Tests pending field with no value.
	 */
	public function test_pending_field_without_value(): void {
		$data = array(
			'type'    => 'AGE_VERIFICATION_21_PLUS',
			'status'  => 'PENDING',
			'context' => array(
				'display_name' => 'Age Verification (21+)',
				'min_age'      => 21,
			),
		);

		$field  = CheckoutField::from_array( $data );
		$issues = $field->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'PENDING', $field->status() );
		$this->assertNull( $field->value() );
	}

	/**
	 * Tests rejected field with validation issue.
	 */
	public function test_rejected_field_with_validation_issue(): void {
		$data = array(
			'type'   => 'AGE_VERIFICATION_21_PLUS',
			'status' => 'REJECTED',
			'value'  => array(
				'confirmed' => false,
			),
		);

		$field  = CheckoutField::from_array( $data );
		$issues = $field->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'REJECTED', $field->status() );
	}

	/**
	 * Tests error field with validation issue.
	 */
	public function test_error_field_with_validation_issue(): void {
		$data = array(
			'type'   => 'GIFT_MESSAGE',
			'status' => 'ERROR',
		);

		$field  = CheckoutField::from_array( $data );
		$issues = $field->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'ERROR', $field->status() );
	}

	/**
	 * Tests minimal checkout field with only required fields.
	 */
	public function test_minimal_checkout_field(): void {
		$data = array(
			'type'   => 'GIFT_MESSAGE',
			'status' => 'PENDING',
		);

		$field  = CheckoutField::from_array( $data );
		$issues = $field->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'GIFT_MESSAGE', $field->type() );
		$this->assertSame( 'PENDING', $field->status() );
		$this->assertNull( $field->value() );
		$this->assertNull( $field->context() );
	}

	/**
	 * Tests value with empty array.
	 */
	public function test_value_empty_array(): void {
		$data = array(
			'type'   => 'GIFT_MESSAGE',
			'status' => 'PENDING',
			'value'  => array(),
		);

		$field = CheckoutField::from_array( $data );
		$value = $field->value();

		$this->assertIsArray( $value );
		$this->assertEmpty( $value );
	}

	/**
	 * Tests context with empty array.
	 */
	public function test_context_empty_array(): void {
		$data = array(
			'type'    => 'GIFT_MESSAGE',
			'status'  => 'PENDING',
			'context' => array(),
		);

		$field   = CheckoutField::from_array( $data );
		$context = $field->context();

		$this->assertIsArray( $context );
		$this->assertEmpty( $context );
	}

	/**
	 * Tests context with flexible additionalProperties structure.
	 */
	public function test_context_flexible_structure(): void {
		$data = array(
			'type'    => 'CUSTOM_ENGRAVING',
			'status'  => 'PENDING',
			'context' => array(
				'display_name'       => 'Custom Engraving',
				'max_length'         => 100,
				'font_options'       => array( 'arial', 'script', 'block' ),
				'position_options'   => array( 'front', 'back' ),
				'additional_cost'    => 15.00,
				'prohibited_content' => array( 'profanity', 'copyrighted_text' ),
				'custom_field_1'     => 'custom value',
				'nested_custom'      => array(
					'sub_field' => 'sub value',
				),
			),
		);

		$field   = CheckoutField::from_array( $data );
		$context = $field->context();

		$this->assertSame( 'Custom Engraving', $context['display_name'] );
		$this->assertSame( 100, $context['max_length'] );
		$this->assertIsArray( $context['font_options'] );
		$this->assertSame( 15.00, $context['additional_cost'] );
		$this->assertSame( 'custom value', $context['custom_field_1'] );
		$this->assertIsArray( $context['nested_custom'] );
	}

	/**
	 * Tests value with flexible structure for different field types.
	 */
	public function test_value_flexible_structure(): void {
		$data = array(
			'type'   => 'ALLERGY_INFORMATION',
			'status' => 'COMPLETED',
			'value'  => array(
				'allergies'         => array( 'peanuts', 'tree nuts' ),
				'severity'          => 'life_threatening',
				'emergency_contact' => array(
					'name'  => 'Jane Doe',
					'phone' => '+1-555-999-8888',
				),
				'medical_id'        => 'MED-12345',
			),
		);

		$field = CheckoutField::from_array( $data );
		$value = $field->value();

		$this->assertIsArray( $value['allergies'] );
		$this->assertContains( 'peanuts', $value['allergies'] );
		$this->assertSame( 'life_threatening', $value['severity'] );
		$this->assertIsArray( $value['emergency_contact'] );
		$this->assertSame( 'Jane Doe', $value['emergency_contact']['name'] );
		$this->assertSame( 'MED-12345', $value['medical_id'] );
	}

	/**
	 * Tests state transition from PENDING to COMPLETED.
	 */
	public function test_status_transition_pending_to_completed(): void {
		$data = array(
			'type'   => 'AGE_VERIFICATION_21_PLUS',
			'status' => 'PENDING',
		);

		$field_pending = CheckoutField::from_array( $data );
		$this->assertSame( 'PENDING', $field_pending->status() );

		// Simulate update with completed status
		$data_completed = array(
			'type'   => 'AGE_VERIFICATION_21_PLUS',
			'status' => 'COMPLETED',
			'value'  => array(
				'confirmed' => true,
			),
		);

		$field_completed = CheckoutField::from_array( $data_completed );
		$this->assertSame( 'COMPLETED', $field_completed->status() );
		$this->assertNotNull( $field_completed->value() );
	}

	/**
	 * Tests context with nested objects for complex field configurations.
	 */
	public function test_context_nested_validation_rules(): void {
		$data = array(
			'type'    => 'GIFT_RECIPIENT_EMAIL',
			'status'  => 'PENDING',
			'context' => array(
				'display_name'          => 'Gift Recipient Information',
				'email_validation'      => true,
				'notification_required' => true,
				'max_message_length'    => 500,
				'field_requirements'    => array(
					'email' => array(
						'format'     => 'valid_email',
						'max_length' => 254,
					),
				),
			),
		);

		$field   = CheckoutField::from_array( $data );
		$context = $field->context();

		$this->assertTrue( $context['email_validation'] );
		$this->assertIsArray( $context['field_requirements'] );
		$this->assertSame( 254, $context['field_requirements']['email']['max_length'] );
	}

	/**
	 * Tests that both required fields missing produces both validation issues.
	 */
	public function test_both_required_fields_missing(): void {
		$data = array();

		$field  = CheckoutField::from_array( $data );
		$issues = $field->validate();

		$this->assertCount( 2, $issues );

		$fields = array_map(
			function ( $issue ) {
				return $issue->to_array()['field'];
			},
			$issues
		);

		$this->assertContains( 'type', $fields );
		$this->assertContains( 'status', $fields );
	}
}
