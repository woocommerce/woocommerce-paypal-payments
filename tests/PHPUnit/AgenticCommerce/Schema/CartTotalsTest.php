<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers CartTotals
 */
class CartTotalsTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return CartTotals::class;
	}

	protected function get_valid_data(): array {
		return array(
			'subtotal'          => array( 'currency_code' => 'USD', 'value' => '25.00' ),
			'discount'          => array( 'currency_code' => 'USD', 'value' => '2.50' ),
			'shipping'          => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'tax'               => array( 'currency_code' => 'USD', 'value' => '2.70' ),
			'handling'          => array( 'currency_code' => 'USD', 'value' => '1.50' ),
			'insurance'         => array( 'currency_code' => 'USD', 'value' => '0.50' ),
			'shipping_discount' => array( 'currency_code' => 'USD', 'value' => '1.00' ),
			'custom_charges'    => array( 'currency_code' => 'USD', 'value' => '3.00' ),
			'total'             => array( 'currency_code' => 'USD', 'value' => '36.69' ),
		);
	}

	protected function get_expected_data(): array {
		return array(
			'subtotal.currency'          => 'USD',
			'subtotal.value'             => 25.00,
			'discount.currency'          => 'USD',
			'discount.value'             => 2.50,
			'shipping.currency'          => 'USD',
			'shipping.value'             => 5.99,
			'tax.currency'               => 'USD',
			'tax.value'                  => 2.70,
			'handling.currency'          => 'USD',
			'handling.value'             => 1.50,
			'insurance.currency'         => 'USD',
			'insurance.value'            => 0.50,
			'shipping_discount.currency' => 'USD',
			'shipping_discount.value'    => 1.00,
			'custom_charges.currency'    => 'USD',
			'custom_charges.value'       => 3.00,
			'total.currency'             => 'USD',
			'total.value'                => 36.69,
		);
	}

	protected function mandatory_data(): array {
		return array(
			'total' => array( 'currency_code' => 'USD', 'value' => '2.50' ),
		);
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'total' );
	}

	public function test_optional_fields(): void {
		$this->assertOptionalField( 'subtotal' );
		$this->assertOptionalField( 'discount' );
		$this->assertOptionalField( 'shipping' );
		$this->assertOptionalField( 'tax' );
		$this->assertOptionalField( 'handling' );
		$this->assertOptionalField( 'insurance' );
		$this->assertOptionalField( 'shipping_discount' );
		$this->assertOptionalField( 'custom_charges' );
	}

	// === Field Accessor Tests ===

	/**
	 * Tests that all Money fields store and return Money objects correctly.
	 *
	 * @dataProvider money_field_accessor_provider
	 */
	public function test_money_field_accessors( array $data, string $accessor ): void {
		$totals = CartTotals::from_array( $data );
		$money  = $totals->$accessor();

		$this->assertInstanceOf( Money::class, $money );
		$this->assertSame( 'USD', $money->currency() );
		$this->assertSame( 10.00, $money->value() );
	}

	public function money_field_accessor_provider(): array {
		$base_data  = array( 'total' => array( 'currency_code' => 'USD', 'value' => '100.00' ) );
		$test_money = array( 'currency_code' => 'USD', 'value' => '10.00' );

		return array(
			'total field'             => array(
				array_merge( $base_data, array( 'total' => $test_money ) ),
				'total',
			),
			'subtotal field'          => array(
				array_merge( $base_data, array( 'subtotal' => $test_money ) ),
				'subtotal',
			),
			'discount field'          => array(
				array_merge( $base_data, array( 'discount' => $test_money ) ),
				'discount',
			),
			'shipping field'          => array(
				array_merge( $base_data, array( 'shipping' => $test_money ) ),
				'shipping',
			),
			'tax field'               => array(
				array_merge( $base_data, array( 'tax' => $test_money ) ),
				'tax',
			),
			'handling field'          => array(
				array_merge( $base_data, array( 'handling' => $test_money ) ),
				'handling',
			),
			'insurance field'         => array(
				array_merge( $base_data, array( 'insurance' => $test_money ) ),
				'insurance',
			),
			'shipping_discount field' => array(
				array_merge( $base_data, array( 'shipping_discount' => $test_money ) ),
				'shipping_discount',
			),
			'custom_charges field'    => array(
				array_merge( $base_data, array( 'custom_charges' => $test_money ) ),
				'custom_charges',
			),
		);
	}

	/**
	 * Tests that optional Money fields return null when not provided.
	 *
	 * @dataProvider optional_money_field_provider
	 */
	public function test_optional_money_fields_return_null_when_missing( string $accessor ): void {
		$data   = array( 'total' => array( 'currency_code' => 'USD', 'value' => '100.00' ) );
		$totals = CartTotals::from_array( $data );

		$this->assertNull( $totals->$accessor() );
	}

	public function optional_money_field_provider(): array {
		return array(
			'subtotal'          => array( 'subtotal' ),
			'discount'          => array( 'discount' ),
			'shipping'          => array( 'shipping' ),
			'tax'               => array( 'tax' ),
			'handling'          => array( 'handling' ),
			'insurance'         => array( 'insurance' ),
			'shipping_discount' => array( 'shipping_discount' ),
			'custom_charges'    => array( 'custom_charges' ),
		);
	}

	// === Type Safety Tests ===

	/**
	 * Tests that Money fields reject invalid types (non-array values).
	 *
	 * @dataProvider invalid_type_provider
	 */
	public function test_money_fields_reject_invalid_types( string $field_name, $invalid_value, string $accessor ): void {
		$data                = array(
			'total' => array(
				'currency_code' => 'USD',
				'value'         => '100.00',
			),
		);
		$data[ $field_name ] = $invalid_value;

		$totals = CartTotals::from_array( $data );

		$this->assertNull( $totals->$accessor() );
	}

	public function invalid_type_provider(): array {
		return array(
			'subtotal with string'          => array( 'subtotal', '25.00', 'subtotal' ),
			'subtotal with integer'         => array( 'subtotal', 2500, 'subtotal' ),
			'discount with string'          => array( 'discount', '5.00', 'discount' ),
			'shipping with boolean'         => array( 'shipping', true, 'shipping' ),
			'tax with string'               => array( 'tax', 'ten dollars', 'tax' ),
			'handling with integer'         => array( 'handling', 150, 'handling' ),
			'insurance with string'         => array( 'insurance', '0.50', 'insurance' ),
			'shipping_discount with number' => array(
				'shipping_discount',
				100,
				'shipping_discount',
			),
			'custom_charges with string'    => array( 'custom_charges', '3.00', 'custom_charges' ),
		);
	}

	// === Validation Tests ===

	/**
	 * Tests that missing required total field produces validation error.
	 */
	public function test_missing_required_total_field(): void {
		$data       = array( 'subtotal' => array( 'currency_code' => 'USD', 'value' => '25.00' ) );
		$totals     = CartTotals::from_array( $data );
		$issues     = $totals->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'DATA_ERROR', $issue_data['code'] );
		$this->assertSame( 'MISSING_FIELD', $issue_data['type'] );
		$this->assertSame( 'total', $issue_data['field'] );
	}

	/**
	 * Tests that invalid types for required total field produce validation error.
	 *
	 * @dataProvider invalid_total_field_provider
	 */
	public function test_invalid_total_field_produces_validation_error( $invalid_value ): void {
		$data       = array( 'total' => $invalid_value );
		$totals     = CartTotals::from_array( $data );
		$issues     = $totals->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'DATA_ERROR', $issue_data['code'] );
		$this->assertSame( 'MISSING_FIELD', $issue_data['type'] );
		$this->assertSame( 'total', $issue_data['field'] );
	}

	public function invalid_total_field_provider(): array {
		return array(
			'total with string'  => array( '100.00' ),
			'total with integer' => array( 10000 ),
			'total with boolean' => array( true ),
			'total with null'    => array( null ),
		);
	}

	/**
	 * Tests that invalid Money structure produces validation errors.
	 *
	 * @dataProvider invalid_money_structure_provider
	 */
	public function test_invalid_money_structure_produces_validation_errors( string $field_name, array $invalid_money ): void {
		$data = array(
			'total'     => array( 'currency_code' => 'USD', 'value' => '100.00' ),
			$field_name => $invalid_money,
		);

		$totals = CartTotals::from_array( $data );
		$issues = $totals->validate();

		$this->assertGreaterThan( 0, count( $issues ), "Invalid Money structure in $field_name should produce validation issues" );
	}

	public function invalid_money_structure_provider(): array {
		return array(
			'subtotal missing currency_code' => array( 'subtotal', array( 'value' => '25.00' ) ),
			'subtotal missing value'         => array(
				'subtotal',
				array( 'currency_code' => 'USD' ),
			),
			'discount invalid currency'      => array(
				'discount',
				array( 'currency_code' => 'INVALID', 'value' => '5.00' ),
			),
			'shipping invalid value format'  => array(
				'shipping',
				array( 'currency_code' => 'USD', 'value' => 'not-a-number' ),
			),
			'total missing currency_code'    => array( 'total', array( 'value' => '100.00' ) ),
			'total missing value'            => array( 'total', array( 'currency_code' => 'USD' ) ),
			'total empty structure'          => array( 'total', array() ),
		);
	}

	/**
	 * Tests that optional fields with invalid Money structures are ignored (set to null).
	 */
	public function test_optional_fields_with_invalid_money_structure_are_ignored(): void {
		$data = array(
			'total'    => array( 'currency_code' => 'USD', 'value' => '100.00' ),
			'subtotal' => array( 'value' => '25.00' ), // Missing currency_code.
		);

		$totals = CartTotals::from_array( $data );

		// The subtotal should be null because Money validation failed.
		$this->assertNull( $totals->subtotal() );
	}

	/**
	 * Tests that multiple validation errors are returned together.
	 */
	public function test_multiple_validation_errors_returned_together(): void {
		$data = array(
			'total'    => array( 'value' => '100.00' ),
			// Missing currency_code.
			'subtotal' => array( 'currency_code' => 'USD' ),
			// Missing value.
			'tax'      => array( 'currency_code' => 'INVALID', 'value' => 'not-a-number' ),
			// Invalid structure.
		);

		$totals = CartTotals::from_array( $data );
		$issues = $totals->validate();

		// Should have validation issues from total, subtotal, and tax.
		$this->assertGreaterThanOrEqual( 2, count( $issues ), 'Should return multiple validation errors' );
	}

	/**
	 * Tests that valid CartTotals with all fields passes validation.
	 */
	public function test_valid_cart_totals_with_all_fields(): void {
		$data = array(
			'subtotal'          => array( 'currency_code' => 'USD', 'value' => '25.00' ),
			'discount'          => array( 'currency_code' => 'USD', 'value' => '2.50' ),
			'shipping'          => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'tax'               => array( 'currency_code' => 'USD', 'value' => '2.70' ),
			'handling'          => array( 'currency_code' => 'USD', 'value' => '1.50' ),
			'insurance'         => array( 'currency_code' => 'USD', 'value' => '0.50' ),
			'shipping_discount' => array( 'currency_code' => 'USD', 'value' => '1.00' ),
			'custom_charges'    => array( 'currency_code' => 'USD', 'value' => '3.00' ),
			'total'             => array( 'currency_code' => 'USD', 'value' => '36.69' ),
		);

		$totals = CartTotals::from_array( $data );
		$issues = $totals->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that CartTotals with only required total field is valid.
	 */
	public function test_minimal_cart_totals_with_only_total(): void {
		$data = array(
			'total' => array( 'currency_code' => 'USD', 'value' => '100.00' ),
		);

		$totals = CartTotals::from_array( $data );
		$issues = $totals->validate();

		$this->assertEmpty( $issues );
		$this->assertInstanceOf( Money::class, $totals->total() );
		$this->assertNull( $totals->subtotal() );
		$this->assertNull( $totals->discount() );
		$this->assertNull( $totals->shipping() );
	}

	/**
	 * Tests that different currencies can be used across fields.
	 */
	public function test_different_currencies_across_fields(): void {
		$data = array(
			'subtotal' => array( 'currency_code' => 'EUR', 'value' => '20.00' ),
			'shipping' => array( 'currency_code' => 'GBP', 'value' => '5.00' ),
			'total'    => array( 'currency_code' => 'USD', 'value' => '30.00' ),
		);

		$totals = CartTotals::from_array( $data );

		$this->assertSame( 'EUR', $totals->subtotal()->currency() );
		$this->assertSame( 'GBP', $totals->shipping()->currency() );
		$this->assertSame( 'USD', $totals->total()->currency() );
	}
}
