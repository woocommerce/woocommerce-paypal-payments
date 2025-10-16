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
			'type'    => 'age_verification_21_plus',
			'status'  => 'completed',
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

	protected function get_expected_data(): array {
		return array(
			'type'                      => 'AGE_VERIFICATION_21_PLUS',
			'status'                    => 'COMPLETED',
			'value.confirmed'           => true,
			'value.verification_method' => 'self_declaration',
			'value.verification_date'   => '2024-06-24T14:30:00Z',
			'context.display_name'      => 'Age Verification (21+)',
			'context.min_age'           => 21,
			'context.compliance_note'   => 'Required by state law',
		);
	}

	protected function get_data_types(): array {
		return array(
			'type'    => 'string',
			'status'  => array( 'type' => 'string', 'valid' => 'completed', 'default' => 'ERROR' ),
			'value'   => array( 'type' => 'array', 'valid' => array() ),
			'context' => array( 'type' => 'array', 'valid' => array() ),
		);
	}

	protected function mandatory_data(): array {
		return array(
			'type'   => 'AGE_VERIFICATION_21_PLUS',
			'status' => 'PENDING',
		);
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'type' );
		$this->assertRequiredField( 'status' );
	}

	public function test_optional_fields(): void {
		$this->assertOptionalField( 'value' );
		$this->assertOptionalField( 'context' );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'type', 'GIFT_MESSAGE' );
		$this->assertWhitespaceTrimming( 'status', 'PENDING' );

		$this->assertFieldNormalizesToUppercase( 'type', 'sample', 'SAMPLE' );
		$this->assertFieldNormalizesToUppercase( 'status', 'pending', 'PENDING' );
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

}
