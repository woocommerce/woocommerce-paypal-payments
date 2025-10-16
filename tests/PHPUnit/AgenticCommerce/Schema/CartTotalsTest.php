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
			'subtotal'          => array( 'currency_code' => 'usd', 'value' => '25.00' ),
			'discount'          => array( 'currency_code' => 'usd', 'value' => '2.50' ),
			'shipping'          => array( 'currency_code' => 'usd', 'value' => '5.99' ),
			'tax'               => array( 'currency_code' => 'usd', 'value' => '2.70' ),
			'handling'          => array( 'currency_code' => 'usd', 'value' => '1.50' ),
			'insurance'         => array( 'currency_code' => 'usd', 'value' => '0.50' ),
			'shipping_discount' => array( 'currency_code' => 'usd', 'value' => '1.00' ),
			'custom_charges'    => array( 'currency_code' => 'usd', 'value' => '3.00' ),
			'total'             => array( 'currency_code' => 'usd', 'value' => '36.69' ),
		);
	}

	protected function get_data_types(): array {
		return array();
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
}
