<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers AppliedCoupon
 */
class AppliedCouponTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return AppliedCoupon::class;
	}

	protected function get_valid_data(): array {
		return array(
			'code'            => 'SAVE10',
			'description'     => '10% off entire order',
			'discount_amount' => array(
				'currency_code' => 'USD',
				'value'         => '4.00',
			),
		);
	}

	protected function get_expected_data(): array {
		return array(
			'code'                     => 'SAVE10',
			'description'              => '10% off entire order',
			'discount_amount.currency' => 'USD',
			'discount_amount.value'    => 4.0,
		);
	}

	public function test_required_fields(): void {
		// AppliedCoupon has no required fields - all fields are optional.
		$this->addToAssertionCount( 1 );
	}

	public function test_optional_fields(): void {
		$this->assertOptionalField( 'code' );
		$this->assertOptionalField( 'description' );
		$this->assertOptionalField( 'discount_amount' );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'code', 'SAVE10' );
		$this->assertWhitespaceTrimming( 'description', 'Discount' );

		$this->assertEmptyStringPreserved( 'code' );
		$this->assertEmptyStringPreserved( 'description' );
	}


	/**
	 * Tests that string fields reject invalid types.
	 *
	 * @dataProvider invalid_string_type_provider
	 */
	public function test_string_fields_reject_invalid_types( string $field_name, $invalid_value, string $accessor ): void {
		$data = array( $field_name => $invalid_value );

		$coupon = AppliedCoupon::from_array( $data );

		$this->assertNull( $coupon->$accessor() );
	}

	public function invalid_string_type_provider(): array {
		return array(
			'code with array'        => array( 'code', array( 'SAVE10' ), 'code' ),
			'code with integer'      => array( 'code', 123, 'code' ),
			'code with boolean'      => array( 'code', true, 'code' ),
			'description with array' => array( 'description', array( 'discount' ), 'description' ),
			'description with int'   => array( 'description', 10, 'description' ),
			'description with bool'  => array( 'description', false, 'description' ),
		);
	}

	/**
	 * Tests that discount_amount rejects invalid types (non-array values).
	 */
	public function test_discount_amount_rejects_invalid_types(): void {
		$data = array(
			'discount_amount' => '5.00', // String instead of array.
		);

		$coupon = AppliedCoupon::from_array( $data );

		$this->assertNull( $coupon->discount_amount() );
	}

	/**
	 * Tests that discount_amount rejects non-array types.
	 *
	 * @dataProvider invalid_discount_amount_type_provider
	 */
	public function test_discount_amount_rejects_non_array_types( $invalid_value ): void {
		$data = array( 'discount_amount' => $invalid_value );

		$coupon = AppliedCoupon::from_array( $data );

		$this->assertNull( $coupon->discount_amount() );
	}

	public function invalid_discount_amount_type_provider(): array {
		return array(
			'string value'  => array( '5.00' ),
			'integer value' => array( 500 ),
			'boolean value' => array( true ),
			'null value'    => array( null ),
		);
	}

	// === Validation Tests ===

	/**
	 * Tests that discount_amount with invalid Money structure is set to null.
	 */
	public function test_discount_amount_with_invalid_money_structure_is_null(): void {
		$data = array(
			'code'            => 'SAVE10',
			'discount_amount' => array( 'value' => '5.00' ), // Missing currency_code.
		);

		$coupon = AppliedCoupon::from_array( $data );

		$this->assertSame( 'SAVE10', $coupon->code() );
		$this->assertNull( $coupon->discount_amount() );
	}

	/**
	 * Tests that special characters in code and description are preserved.
	 */
	public function test_special_characters_in_fields(): void {
		$data = array(
			'code'        => 'SAVE-10%',
			'description' => '10% off! (Limited time only)',
		);

		$coupon = AppliedCoupon::from_array( $data );

		$this->assertSame( 'SAVE-10%', $coupon->code() );
		$this->assertSame( '10% off! (Limited time only)', $coupon->description() );
	}
}
