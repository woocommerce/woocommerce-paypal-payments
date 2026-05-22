<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem
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
				'currency_code' => 'usd',
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
			'price.currency_code'         => 'USD',
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

		$this->assertFieldIsCaseSensitive( 'item_id', 'sample' );
		$this->assertFieldIsCaseSensitive( 'variant_id', 'sample' );
		$this->assertFieldIsCaseSensitive( 'parent_id', 'sample' );
		$this->assertFieldIsCaseSensitive( 'name', 'sample' );
		$this->assertFieldIsCaseSensitive( 'description', 'sample' );

		$this->assertFieldAcceptsSpecialCharacters( 'item_id' );
		$this->assertFieldAcceptsSpecialCharacters( 'variant_id' );
		$this->assertFieldAcceptsSpecialCharacters( 'parent_id' );
		$this->assertFieldAcceptsSpecialCharacters( 'name' );
		$this->assertFieldAcceptsSpecialCharacters( 'description' );
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

	// -------------------------------------------------------------------------
	// Group — to_array()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a CartItem with all optional fields populated
	 * WHEN to_array() is called
	 * THEN all populated fields appear in the result with correct values
	 * AND price appears as a nested array with currency_code and value
	 */
	public function test_to_array_includes_all_populated_fields(): void {
		$item = CartItem::from_array(
			array(
				'quantity'            => 2,
				'item_id'             => 'SHIRT-001',
				'variant_id'          => 'VAR-L',
				'name'                => 'Blue T-Shirt',
				'description'         => 'Cotton shirt',
				'price'               => array( 'currency_code' => 'USD', 'value' => '25.00' ),
				'selected_attributes' => array(
					array( 'name' => 'Size', 'value' => 'L' ),
				),
			),
			new \WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation()
		);

		$result = $item->to_array();

		$this->assertSame( 2, $result['quantity'] );
		$this->assertSame( 'SHIRT-001', $result['item_id'] );
		$this->assertSame( 'VAR-L', $result['variant_id'] );
		$this->assertSame( 'Blue T-Shirt', $result['name'] );
		$this->assertSame( 'Cotton shirt', $result['description'] );
		$this->assertSame( 'USD', $result['price']['currency_code'] );
		$this->assertSame( '25.00', $result['price']['value'] );
		$this->assertSame( 'Size', $result['selected_attributes'][0]['name'] );
		$this->assertSame( 'L', $result['selected_attributes'][0]['value'] );
	}

	/**
	 * GIVEN a CartItem with only the mandatory quantity field
	 * WHEN to_array() is called
	 * THEN only quantity is present; optional fields are absent from the result
	 */
	public function test_to_array_omits_absent_optional_fields(): void {
		$item = CartItem::from_array(
			array( 'quantity' => 3 ),
			new \WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation()
		);

		$result = $item->to_array();

		$this->assertSame( 3, $result['quantity'] );
		$this->assertArrayNotHasKey( 'item_id', $result );
		$this->assertArrayNotHasKey( 'variant_id', $result );
		$this->assertArrayNotHasKey( 'parent_id', $result );
		$this->assertArrayNotHasKey( 'name', $result );
		$this->assertArrayNotHasKey( 'description', $result );
		$this->assertArrayNotHasKey( 'price', $result );
		$this->assertArrayNotHasKey( 'selected_attributes', $result );
	}
}
