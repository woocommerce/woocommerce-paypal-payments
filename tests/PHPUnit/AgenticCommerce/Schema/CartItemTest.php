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

	/**
	 * Tests that quantity less than 1 produces validation issue.
	 */
	public function test_quantity_less_than_minimum(): void {
		$data = array( 'quantity' => 0 );
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'quantity', $issue_data['field'] );
	}

	/**
	 * Tests that negative quantity produces validation issue.
	 */
	public function test_quantity_negative_value(): void {
		$data = array( 'quantity' => - 5 );
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'quantity', $issue_data['field'] );
	}

	/**
	 * Tests that quantity with exactly 1 is valid.
	 */
	public function test_quantity_at_minimum_is_valid(): void {
		$data = array( 'quantity' => 1 );
		$item = CartItem::from_array( $data );

		$issues = $item->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 1, $item->quantity() );
	}

	/**
	 * Tests that quantity exceeding 999 produces validation issue.
	 */
	public function test_quantity_exceeds_maximum(): void {
		$data = array( 'quantity' => 1000 );
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'quantity', $issue_data['field'] );
	}

	/**
	 * Tests that quantity with exactly 999 is valid.
	 */
	public function test_quantity_at_maximum_is_valid(): void {
		$data = array( 'quantity' => 999 );
		$item = CartItem::from_array( $data );

		$issues = $item->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 999, $item->quantity() );
	}

	/**
	 * Tests that selected_attributes exceeding 10 attributes produces validation issue.
	 */
	public function test_selected_attributes_exceeds_max_count(): void {
		$attributes = array();
		for ( $i = 0; $i < 11; $i ++ ) {
			$attributes[] = array(
				'name'  => "Attribute $i",
				'value' => "Value $i",
			);
		}

		$data = array(
			'selected_attributes' => $attributes,
			'quantity'            => 1,
		);
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'selected_attributes', $issue_data['field'] );
	}

	/**
	 * Tests that selected_attributes with exactly 10 attributes is valid.
	 */
	public function test_selected_attributes_at_max_count_is_valid(): void {
		$attributes = array();
		for ( $i = 0; $i < 10; $i ++ ) {
			$attributes[] = array(
				'name'  => "Attribute $i",
				'value' => "Value $i",
			);
		}

		$data = array(
			'selected_attributes' => $attributes,
			'quantity'            => 1,
		);
		$item = CartItem::from_array( $data );

		$issues = $item->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that price with negative value produces validation issue.
	 */
	public function test_price_negative_value(): void {
		$data = array(
			'price'    => array(
				'currency_code' => 'USD',
				'value'         => '-25.00',
			),
			'quantity' => 1,
		);
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'price', $issue_data['field'] );
	}

	/**
	 * Tests that price with zero value produces validation issue.
	 */
	public function test_price_zero_value(): void {
		$data = array(
			'price'    => array(
				'currency_code' => 'USD',
				'value'         => '0.00',
			),
			'quantity' => 1,
		);
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'price', $issue_data['field'] );
	}

	/**
	 * Tests that price with positive value is valid.
	 */
	public function test_price_positive_value_is_valid(): void {
		$data = array(
			'price'    => array(
				'currency_code' => 'USD',
				'value'         => '25.00',
			),
			'quantity' => 1,
		);
		$item = CartItem::from_array( $data );

		$issues = $item->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * @dataProvider invalid_type_provider
	 */
	public function test_fields_reject_invalid_types( string $field_name, $invalid_value, string $getter_method, $expected_default ): void {
		$data = array(
			$field_name => $invalid_value,
			'quantity'  => 1,
		);
		$item = CartItem::from_array( $data );

		$this->assertSame( $expected_default, $item->$getter_method() );
	}

	public function invalid_type_provider(): array {
		return array(
			'item_id with array'              => array( 'item_id', array( 'id' ), 'item_id', null ),
			'item_id with integer'            => array( 'item_id', 123, 'item_id', null ),
			'variant_id with array'           => array(
				'variant_id',
				array( 'id' ),
				'variant_id',
				null,
			),
			'variant_id with integer'         => array( 'variant_id', 456, 'variant_id', null ),
			'parent_id with array'            => array(
				'parent_id',
				array( 'id' ),
				'parent_id',
				null,
			),
			'parent_id with integer'          => array( 'parent_id', 789, 'parent_id', null ),
			'quantity with string'            => array( 'quantity', 'five', 'quantity', 1 ),
			'quantity with array'             => array( 'quantity', array( 5 ), 'quantity', 1 ),
			'name with array'                 => array( 'name', array( 'name' ), 'name', null ),
			'name with integer'               => array( 'name', 123, 'name', null ),
			'description with array'          => array(
				'description',
				array( 'desc' ),
				'description',
				null,
			),
			'description with integer'        => array( 'description', 123, 'description', null ),
			'selected_attributes with string' => array(
				'selected_attributes',
				'color:blue',
				'selected_attributes',
				null,
			),
			'gift_options with string'        => array(
				'gift_options',
				'is_gift',
				'gift_options',
				null,
			),
		);
	}

	/**
	 * Tests that multiple validation errors are all returned together.
	 */
	public function test_multiple_validation_errors_returned_together(): void {
		$attributes = array();
		for ( $i = 0; $i < 11; $i ++ ) {
			$attributes[] = array(
				'name'  => "Attribute $i",
				'value' => "Value $i",
			);
		}

		$data = array(
			'item_id'             => str_repeat( 'a', 128 ),
			'variant_id'          => str_repeat( 'b', 128 ),
			'parent_id'           => str_repeat( 'c', 128 ),
			'quantity'            => 1000,
			'name'                => str_repeat( 'd', 128 ),
			'description'         => str_repeat( 'e', 256 ),
			'price'               => array(
				'currency_code' => 'USD',
				'value'         => '-25.00',
			),
			'selected_attributes' => $attributes,
		);

		$item   = CartItem::from_array( $data );
		$issues = $item->validate();

		$this->assertCount( 8, $issues, 'Should return all validation errors at once' );

		$fields = array_map(
			function ( $issue ) {
				return $issue->to_array()['field'];
			},
			$issues
		);

		$this->assertContains( 'item_id', $fields );
		$this->assertContains( 'variant_id', $fields );
		$this->assertContains( 'parent_id', $fields );
		$this->assertContains( 'quantity', $fields );
		$this->assertContains( 'name', $fields );
		$this->assertContains( 'description', $fields );
		$this->assertContains( 'price', $fields );
		$this->assertContains( 'selected_attributes', $fields );
	}

	/**
	 * Tests cart item with empty selected_attributes array.
	 */
	public function test_empty_selected_attributes_array(): void {
		$data = array(
			'selected_attributes' => array(),
			'quantity'            => 1,
		);

		$item       = CartItem::from_array( $data );
		$issues     = $item->validate();
		$attributes = $item->selected_attributes();

		$this->assertEmpty( $issues );
		$this->assertIsArray( $attributes );
		$this->assertEmpty( $attributes );
	}
}
