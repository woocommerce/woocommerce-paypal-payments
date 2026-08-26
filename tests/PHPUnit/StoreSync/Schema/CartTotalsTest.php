<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\CartTotals
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
		return array(
			'subtotal'          => array( 'type' => 'array', 'valid' => array() ),
			'discount'          => array( 'type' => 'array', 'valid' => array() ),
			'shipping'          => array( 'type' => 'array', 'valid' => array() ),
			'tax'               => array( 'type' => 'array', 'valid' => array() ),
			'handling'          => array( 'type' => 'array', 'valid' => array() ),
			'insurance'         => array( 'type' => 'array', 'valid' => array() ),
			'shipping_discount' => array( 'type' => 'array', 'valid' => array() ),
			'custom_charges'    => array( 'type' => 'array', 'valid' => array() ),
			'total'             => array( 'type' => 'array', 'valid' => array() ),
		);
	}

	protected function get_expected_data(): array {
		return array(
			'subtotal.currency_code'          => 'USD',
			'subtotal.value'                  => 25.00,
			'discount.currency_code'          => 'USD',
			'discount.value'                  => 2.50,
			'shipping.currency_code'          => 'USD',
			'shipping.value'                  => 5.99,
			'tax.currency_code'               => 'USD',
			'tax.value'                       => 2.70,
			'handling.currency_code'          => 'USD',
			'handling.value'                  => 1.50,
			'insurance.currency_code'         => 'USD',
			'insurance.value'                 => 0.50,
			'shipping_discount.currency_code' => 'USD',
			'shipping_discount.value'         => 1.00,
			'custom_charges.currency_code'    => 'USD',
			'custom_charges.value'            => 3.00,
			'total.currency_code'             => 'USD',
			'total.value'                     => 36.69,
		);
	}

	protected function mandatory_data(): array {
		return array(
			'total' => array( 'currency_code' => 'USD', 'value' => '2.50' ),
		);
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'total' );

		$this->assertOptionalField( 'subtotal' );
		$this->assertOptionalField( 'discount' );
		$this->assertOptionalField( 'shipping' );
		$this->assertOptionalField( 'tax' );
		$this->assertOptionalField( 'handling' );
		$this->assertOptionalField( 'insurance' );
		$this->assertOptionalField( 'shipping_discount' );
		$this->assertOptionalField( 'custom_charges' );
	}
}
