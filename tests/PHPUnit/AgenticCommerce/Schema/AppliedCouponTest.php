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

	// === Field Accessor Tests ===

	/**
	 * Tests that string fields store and return values correctly.
	 *
	 * @dataProvider string_field_accessor_provider
	 */
	public function test_string_field_accessors( string $field_name, string $field_value, string $accessor ): void {
		$data = array( $field_name => $field_value );

		$coupon = AppliedCoupon::from_array( $data );

		$this->assertSame( $field_value, $coupon->$accessor() );
	}

	public function string_field_accessor_provider(): array {
		return array(
			'code field'        => array( 'code', 'SAVE10', 'code' ),
			'description field' => array( 'description', '10% off entire order', 'description' ),
		);
	}

	/**
	 * Tests that discount_amount returns Money object.
	 */
	public function test_discount_amount_accessor(): void {
		$data = array(
			'discount_amount' => array(
				'currency_code' => 'USD',
				'value'         => '5.50',
			),
		);

		$coupon          = AppliedCoupon::from_array( $data );
		$discount_amount = $coupon->discount_amount();

		$this->assertInstanceOf( Money::class, $discount_amount );
		$this->assertSame( 'USD', $discount_amount->currency_code() );
		$this->assertSame( '5.50', $discount_amount->value() );
	}

	/**
	 * Tests that all fields return null when not provided.
	 *
	 * @dataProvider optional_field_provider
	 */
	public function test_optional_fields_return_null_when_missing( string $accessor ): void {
		$data   = array();
		$coupon = AppliedCoupon::from_array( $data );

		$this->assertNull( $coupon->$accessor() );
	}

	public function optional_field_provider(): array {
		return array(
			'code'            => array( 'code' ),
			'description'     => array( 'description' ),
			'discount_amount' => array( 'discount_amount' ),
		);
	}

	// === Type Safety Tests ===

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

	// === Whitespace Handling Tests ===

	/**
	 * Tests that empty strings are treated as null.
	 *
	 * @dataProvider empty_string_provider
	 */
	public function test_empty_strings_treated_as_null( string $field_name, string $accessor ): void {
		$data = array( $field_name => '' );

		$coupon = AppliedCoupon::from_array( $data );

		$this->assertNull( $coupon->$accessor() );
	}

	public function empty_string_provider(): array {
		return array(
			'code'        => array( 'code', 'code' ),
			'description' => array( 'description', 'description' ),
		);
	}

	/**
	 * Tests that whitespace-only strings are treated as null.
	 *
	 * @dataProvider whitespace_string_provider
	 */
	public function test_whitespace_only_strings_treated_as_null( string $field_name, string $accessor ): void {
		$data = array( $field_name => '   ' );

		$coupon = AppliedCoupon::from_array( $data );

		$this->assertNull( $coupon->$accessor() );
	}

	public function whitespace_string_provider(): array {
		return array(
			'code'        => array( 'code', 'code' ),
			'description' => array( 'description', 'description' ),
		);
	}

	/**
	 * Tests that string values are trimmed.
	 *
	 * @dataProvider string_trimming_provider
	 */
	public function test_string_values_are_trimmed( string $field_name, string $input_value, string $expected_value, string $accessor ): void {
		$data = array( $field_name => $input_value );

		$coupon = AppliedCoupon::from_array( $data );

		$this->assertSame( $expected_value, $coupon->$accessor() );
	}

	public function string_trimming_provider(): array {
		return array(
			'code with leading space'       => array( 'code', ' SAVE10', 'SAVE10', 'code' ),
			'code with trailing space'      => array( 'code', 'SAVE10 ', 'SAVE10', 'code' ),
			'code with both spaces'         => array( 'code', '  SAVE10  ', 'SAVE10', 'code' ),
			'description with leading'      => array( 'description', ' 10% off', '10% off', 'description' ),
			'description with trailing'     => array( 'description', '10% off ', '10% off', 'description' ),
			'description with both'         => array( 'description', '  10% off entire order  ', '10% off entire order', 'description' ),
		);
	}

	// === Validation Tests ===

	/**
	 * Tests that invalid Money structure in discount_amount produces validation errors.
	 *
	 * @dataProvider invalid_discount_amount_structure_provider
	 */
	public function test_invalid_discount_amount_structure_produces_validation_errors( array $invalid_money ): void {
		$data = array( 'discount_amount' => $invalid_money );

		$coupon = AppliedCoupon::from_array( $data );
		$issues = $coupon->validate();

		$this->assertGreaterThan( 0, count( $issues ), 'Invalid Money structure should produce validation issues' );
	}

	public function invalid_discount_amount_structure_provider(): array {
		return array(
			'missing currency_code' => array( array( 'value' => '5.00' ) ),
			'missing value'         => array( array( 'currency_code' => 'USD' ) ),
			'invalid currency'      => array( array( 'currency_code' => 'INVALID', 'value' => '5.00' ) ),
			'invalid value format'  => array( array( 'currency_code' => 'USD', 'value' => 'not-a-number' ) ),
			'empty structure'       => array( array() ),
		);
	}

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
	 * Tests that AppliedCoupon with all valid fields passes validation.
	 */
	public function test_valid_applied_coupon_with_all_fields(): void {
		$data = array(
			'code'            => 'SAVE10',
			'description'     => '10% off entire order',
			'discount_amount' => array(
				'currency_code' => 'USD',
				'value'         => '4.00',
			),
		);

		$coupon = AppliedCoupon::from_array( $data );
		$issues = $coupon->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that AppliedCoupon with no fields is valid (all fields optional).
	 */
	public function test_empty_applied_coupon_is_valid(): void {
		$data = array();

		$coupon = AppliedCoupon::from_array( $data );
		$issues = $coupon->validate();

		$this->assertEmpty( $issues );
		$this->assertNull( $coupon->code() );
		$this->assertNull( $coupon->description() );
		$this->assertNull( $coupon->discount_amount() );
	}

	/**
	 * Tests that AppliedCoupon with only code is valid.
	 */
	public function test_applied_coupon_with_only_code(): void {
		$data = array( 'code' => 'SAVE10' );

		$coupon = AppliedCoupon::from_array( $data );
		$issues = $coupon->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'SAVE10', $coupon->code() );
		$this->assertNull( $coupon->description() );
		$this->assertNull( $coupon->discount_amount() );
	}

	/**
	 * Tests that AppliedCoupon with only discount_amount is valid.
	 */
	public function test_applied_coupon_with_only_discount_amount(): void {
		$data = array(
			'discount_amount' => array(
				'currency_code' => 'USD',
				'value'         => '5.00',
			),
		);

		$coupon = AppliedCoupon::from_array( $data );
		$issues = $coupon->validate();

		$this->assertEmpty( $issues );
		$this->assertNull( $coupon->code() );
		$this->assertNull( $coupon->description() );
		$this->assertInstanceOf( Money::class, $coupon->discount_amount() );
	}

	/**
	 * Tests that different currencies work in discount_amount.
	 */
	public function test_different_currencies_in_discount_amount(): void {
		$data_eur = array(
			'discount_amount' => array( 'currency_code' => 'EUR', 'value' => '3.50' ),
		);

		$coupon = AppliedCoupon::from_array( $data_eur );
		$this->assertSame( 'EUR', $coupon->discount_amount()->currency_code() );

		$data_gbp = array(
			'discount_amount' => array( 'currency_code' => 'GBP', 'value' => '2.75' ),
		);

		$coupon = AppliedCoupon::from_array( $data_gbp );
		$this->assertSame( 'GBP', $coupon->discount_amount()->currency_code() );
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
