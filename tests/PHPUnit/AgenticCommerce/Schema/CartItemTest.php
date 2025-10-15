<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers CartItem
 */
class CartItemTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return CartItem::class;
	}

	protected function get_valid_data(): array {
		return array(
			'item_id'             => 'SHIRT-BLUE-M',
			'variant_id'          => 'SHIRT-BLUE-M-COTTON',
			'parent_id'           => 'SHIRT-COLLECTION-001',
			'quantity'            => 2,
			'name'                => 'Blue Cotton T-Shirt (Medium)',
			'description'         => 'Comfortable cotton t-shirt in medium size',
			'price'               => array(
				'currency_code' => 'USD',
				'value'         => '25.00',
			),
			'selected_attributes' => array(
				array(
					'name'  => 'Color',
					'value' => 'Blue',
				),
				array(
					'name'  => 'Size',
					'value' => 'Medium',
				),
			),
			'gift_options'        => array(
				'is_gift'      => true,
				'sender_name'  => 'John Smith',
				'gift_message' => 'Happy Birthday!',
			),
		);
	}

	protected function get_expected_data(): array {
		return array(
			'item_id'                     => 'SHIRT-BLUE-M',
			'variant_id'                  => 'SHIRT-BLUE-M-COTTON',
			'parent_id'                   => 'SHIRT-COLLECTION-001',
			'quantity'                    => 2,
			'name'                        => 'Blue Cotton T-Shirt (Medium)',
			'description'                 => 'Comfortable cotton t-shirt in medium size',
			'price.currency'              => 'USD',
			'price.value'                 => 25.0,
			'selected_attributes.0.name'  => 'Color',
			'selected_attributes.0.value' => 'Blue',
			'selected_attributes.1.name'  => 'Size',
			'selected_attributes.1.value' => 'Medium',
			'gift_options.is_gift'        => true,
			'gift_options.sender_name'    => 'John Smith',
			'gift_options.gift_message'   => 'Happy Birthday!',
		);
	}

	protected function get_data_types(): array {
		return array(
			'item_id'     => 'string',
			'variant_id'  => 'string',
			'parent_id'   => 'string',
			'quantity'    => array( 'type' => 'number', 'default' => 0 ),
			'name'        => 'string',
			'description' => 'string',
		);
	}

	protected function mandatory_data(): array {
		return array( 'quantity' => 1 );
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'quantity' );
	}

	public function test_optional_fields(): void {
		$this->assertOptionalField( 'item_id' );
		$this->assertOptionalField( 'variant_id' );
		$this->assertOptionalField( 'parent_id' );
		$this->assertOptionalField( 'name' );
		$this->assertOptionalField( 'description' );
		$this->assertOptionalField( 'price' );
		$this->assertOptionalField( 'selected_attributes' );
		$this->assertOptionalField( 'gift_options' );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'item_id', 'SHIRT-001' );
		$this->assertWhitespaceTrimming( 'variant_id', 'VARIANT-001' );
		$this->assertWhitespaceTrimming( 'parent_id', 'PARENT-001' );
		$this->assertWhitespaceTrimming( 'name', 'Blue T-Shirt' );
		$this->assertWhitespaceTrimming( 'description', 'Cotton shirt' );

		$this->assertEmptyStringPreserved( 'item_id' );
		$this->assertEmptyStringPreserved( 'variant_id' );
		$this->assertEmptyStringPreserved( 'parent_id' );
		$this->assertEmptyStringPreserved( 'name' );
		$this->assertEmptyStringPreserved( 'description' );

		$this->assertStringFieldMaxLength( 'item_id', 127 );
		$this->assertStringFieldMaxLength( 'variant_id', 127 );
		$this->assertStringFieldMaxLength( 'parent_id', 127 );
		$this->assertStringFieldMaxLength( 'name', 127 );
		$this->assertStringFieldMaxLength( 'description', 255 );
	}

	public function test_quantity_range(): void {
		$this->assertIntegerFieldRange( 'quantity', 1, 999 );
	}

	public function test_selected_attributes_max_count(): void {
		$this->assertArrayFieldMaxCount(
			'selected_attributes',
			10,
			array(
				'name'  => 'Attribute {index}',
				'value' => 'Value {index}',
			)
		);
	}
}
