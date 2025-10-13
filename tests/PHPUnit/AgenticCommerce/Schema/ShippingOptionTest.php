<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers ShippingOption
 */
class ShippingOptionTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return ShippingOption::class;
	}

	protected function get_valid_data(): array {
		return array(
			'id'         => 'STANDARD_SHIPPING',
			'name'       => 'Standard Shipping (5-7 days)',
			'price'      => array(
				'currency_code' => 'USD',
				'value'         => '5.99',
			),
			'isSelected' => true,
		);
	}

	/**
	 * Tests that ShippingOption stores and returns the id.
	 */
	public function test_id_accessor(): void {
		$data   = array(
			'id'         => 'EXPRESS_SHIPPING',
			'name'       => 'Express',
			'price'      => array(
				'currency_code' => 'USD',
				'value'         => '12.99',
			),
			'isSelected' => false,
		);
		$option = ShippingOption::from_array( $data );

		$this->assertSame( 'EXPRESS_SHIPPING', $option->id() );
	}
}
