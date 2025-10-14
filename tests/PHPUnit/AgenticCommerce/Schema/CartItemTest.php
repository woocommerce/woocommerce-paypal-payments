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

	public function test_required_fields(): void {
		$this->assertRequiredField( 'quantity' );
	}

	public function test_optional_fields(): void {
		$base_data = array( 'quantity' => 1 );

		$this->assertOptionalField( 'item_id', $base_data );
		$this->assertOptionalField( 'variant_id', $base_data );
		$this->assertOptionalField( 'parent_id', $base_data );
		$this->assertOptionalField( 'name', $base_data );
		$this->assertOptionalField( 'description', $base_data );
		$this->assertOptionalField( 'price', $base_data );
		$this->assertOptionalField( 'selected_attributes', $base_data );
		$this->assertOptionalField( 'gift_options', $base_data );
	}

	public function test_string_fields(): void {
		$mandatory = array( 'quantity' => 1 );

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

		$this->assertStringFieldMaxLength( 'item_id', 127, $mandatory );
		$this->assertStringFieldMaxLength( 'variant_id', 127, $mandatory );
		$this->assertStringFieldMaxLength( 'parent_id', 127, $mandatory );
		$this->assertStringFieldMaxLength( 'name', 127, $mandatory );
		$this->assertStringFieldMaxLength( 'description', 255, $mandatory );
	}

	/**
	 * Tests that CartItem stores and returns the item_id.
	 */
	public function test_item_id_accessor(): void {
		$data = array( 'item_id' => 'SHIRT-BLUE-M' );
		$item = CartItem::from_array( $data );

		$this->assertSame( 'SHIRT-BLUE-M', $item->item_id() );
	}

	/**
	 * Tests that CartItem stores and returns the variant_id.
	 */
	public function test_variant_id_accessor(): void {
		$data = array( 'variant_id' => 'SHIRT-BLUE-M-COTTON' );
		$item = CartItem::from_array( $data );

		$this->assertSame( 'SHIRT-BLUE-M-COTTON', $item->variant_id() );
	}

	/**
	 * Tests that CartItem stores and returns the parent_id.
	 */
	public function test_parent_id_accessor(): void {
		$data = array( 'parent_id' => 'SHIRT-COLLECTION-001' );
		$item = CartItem::from_array( $data );

		$this->assertSame( 'SHIRT-COLLECTION-001', $item->parent_id() );
	}

	/**
	 * Tests that CartItem stores and returns the quantity.
	 */
	public function test_quantity_accessor(): void {
		$data = array( 'quantity' => 5 );
		$item = CartItem::from_array( $data );

		$this->assertSame( 5, $item->quantity() );
	}

	/**
	 * Tests that CartItem stores and returns the name.
	 */
	public function test_name_accessor(): void {
		$data = array( 'name' => 'Blue Cotton T-Shirt' );
		$item = CartItem::from_array( $data );

		$this->assertSame( 'Blue Cotton T-Shirt', $item->name() );
	}

	/**
	 * Tests that CartItem stores and returns the description.
	 */
	public function test_description_accessor(): void {
		$data = array( 'description' => 'Comfortable cotton t-shirt' );
		$item = CartItem::from_array( $data );

		$this->assertSame( 'Comfortable cotton t-shirt', $item->description() );
	}

	/**
	 * Tests that CartItem stores and returns the price as Money object.
	 */
	public function test_price_accessor(): void {
		$data = array(
			'price' => array(
				'currency_code' => 'USD',
				'value'         => '25.00',
			),
		);
		$item = CartItem::from_array( $data );

		$price = $item->price();

		$this->assertInstanceOf( Money::class, $price );
		$this->assertSame( 'USD', $price->currency() );
		$this->assertSame( 25.00, $price->value() );
	}

	/**
	 * Tests that CartItem stores and returns selected_attributes array.
	 */
	public function test_selected_attributes_accessor(): void {
		$data = array(
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
		);
		$item = CartItem::from_array( $data );

		$attributes = $item->selected_attributes();

		$this->assertIsArray( $attributes );
		$this->assertCount( 2, $attributes );
		$this->assertSame( 'Color', $attributes[0]['name'] );
		$this->assertSame( 'Blue', $attributes[0]['value'] );
		$this->assertSame( 'Size', $attributes[1]['name'] );
		$this->assertSame( 'Medium', $attributes[1]['value'] );
	}

	/**
	 * Tests that CartItem stores and returns gift_options as GiftOptions object.
	 */
	public function test_gift_options_accessor(): void {
		$data = array(
			'gift_options' => array(
				'is_gift'      => true,
				'sender_name'  => 'John Smith',
				'gift_message' => 'Happy Birthday!',
			),
		);
		$item = CartItem::from_array( $data );

		$gift_options = $item->gift_options();

		$this->assertInstanceOf( GiftOptions::class, $gift_options );
		$this->assertTrue( $gift_options->is_gift() );
		$this->assertSame( 'John Smith', $gift_options->sender_name() );
		$this->assertSame( 'Happy Birthday!', $gift_options->gift_message() );
	}

	/**
	 * @dataProvider optional_field_provider
	 */
	public function test_optional_fields_return_null_when_missing( string $field_name, string $getter_method ): void {
		$data = array( 'quantity' => 1 ); // quantity is required
		$item = CartItem::from_array( $data );

		$this->assertNull( $item->$getter_method() );
	}

	public function optional_field_provider(): array {
		return array(
			'item_id'             => array( 'item_id', 'item_id' ),
			'variant_id'          => array( 'variant_id', 'variant_id' ),
			'parent_id'           => array( 'parent_id', 'parent_id' ),
			'name'                => array( 'name', 'name' ),
			'description'         => array( 'description', 'description' ),
			'price'               => array( 'price', 'price' ),
			'selected_attributes' => array( 'selected_attributes', 'selected_attributes' ),
			'gift_options'        => array( 'gift_options', 'gift_options' ),
		);
	}

	/**
	 * Tests that quantity is required and validates as missing field when not provided.
	 */
	public function test_quantity_missing_produces_validation_issue(): void {
		$data = array();
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'quantity', $issue_data['field'] );
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
	 * Tests that item_id exceeding 127 characters produces validation issue.
	 */
	public function test_item_id_exceeds_max_length(): void {
		$data = array(
			'item_id'  => str_repeat( 'a', 128 ),
			'quantity' => 1,
		);
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'item_id', $issue_data['field'] );
	}

	/**
	 * Tests that item_id with exactly 127 characters is valid.
	 */
	public function test_item_id_at_max_length_is_valid(): void {
		$data = array(
			'item_id'  => str_repeat( 'a', 127 ),
			'quantity' => 1,
		);
		$item = CartItem::from_array( $data );

		$issues = $item->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that variant_id exceeding 127 characters produces validation issue.
	 */
	public function test_variant_id_exceeds_max_length(): void {
		$data = array(
			'variant_id' => str_repeat( 'a', 128 ),
			'quantity'   => 1,
		);
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'variant_id', $issue_data['field'] );
	}

	/**
	 * Tests that variant_id with exactly 127 characters is valid.
	 */
	public function test_variant_id_at_max_length_is_valid(): void {
		$data = array(
			'variant_id' => str_repeat( 'a', 127 ),
			'quantity'   => 1,
		);
		$item = CartItem::from_array( $data );

		$issues = $item->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that parent_id exceeding 127 characters produces validation issue.
	 */
	public function test_parent_id_exceeds_max_length(): void {
		$data = array(
			'parent_id' => str_repeat( 'a', 128 ),
			'quantity'  => 1,
		);
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'parent_id', $issue_data['field'] );
	}

	/**
	 * Tests that parent_id with exactly 127 characters is valid.
	 */
	public function test_parent_id_at_max_length_is_valid(): void {
		$data = array(
			'parent_id' => str_repeat( 'a', 127 ),
			'quantity'  => 1,
		);
		$item = CartItem::from_array( $data );

		$issues = $item->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that name exceeding 127 characters produces validation issue.
	 */
	public function test_name_exceeds_max_length(): void {
		$data = array(
			'name'     => str_repeat( 'a', 128 ),
			'quantity' => 1,
		);
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'name', $issue_data['field'] );
	}

	/**
	 * Tests that name with exactly 127 characters is valid.
	 */
	public function test_name_at_max_length_is_valid(): void {
		$data = array(
			'name'     => str_repeat( 'a', 127 ),
			'quantity' => 1,
		);
		$item = CartItem::from_array( $data );

		$issues = $item->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that description exceeding 255 characters produces validation issue.
	 */
	public function test_description_exceeds_max_length(): void {
		$data = array(
			'description' => str_repeat( 'a', 256 ),
			'quantity'    => 1,
		);
		$item = CartItem::from_array( $data );

		$issues     = $item->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'description', $issue_data['field'] );
	}

	/**
	 * Tests that description with exactly 255 characters is valid.
	 */
	public function test_description_at_max_length_is_valid(): void {
		$data = array(
			'description' => str_repeat( 'a', 255 ),
			'quantity'    => 1,
		);
		$item = CartItem::from_array( $data );

		$issues = $item->validate();

		$this->assertEmpty( $issues );
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
	 * Tests that leading/trailing whitespace is trimmed from string values.
	 *
	 * @dataProvider whitespace_trimming_provider
	 */
	public function test_string_values_are_trimmed( string $field_name, string $input_value, string $expected_value ): void {
		$data = array(
			$field_name => $input_value,
			'quantity'  => 1,
		);
		$item = CartItem::from_array( $data );

		$this->assertSame( $expected_value, $item->$field_name() );
	}

	public function whitespace_trimming_provider(): array {
		return array(
			'item_id with leading space'  => array( 'item_id', ' SHIRT-001', 'SHIRT-001' ),
			'item_id with trailing space' => array( 'item_id', 'SHIRT-001 ', 'SHIRT-001' ),
			'item_id with both'           => array( 'item_id', '  SHIRT-001  ', 'SHIRT-001' ),
			'variant_id with spaces'      => array(
				'variant_id',
				' SHIRT-BLUE-M ',
				'SHIRT-BLUE-M',
			),
			'parent_id with spaces'       => array(
				'parent_id',
				' COLLECTION-001 ',
				'COLLECTION-001',
			),
			'name with spaces'            => array(
				'name',
				' Blue T-Shirt ',
				'Blue T-Shirt',
			),
			'description with spaces'     => array(
				'description',
				' Comfortable cotton t-shirt ',
				'Comfortable cotton t-shirt',
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
	 * Tests backwards compatible cart item with only item_id (no variant_id).
	 */
	public function test_backwards_compatible_item_id_only(): void {
		$data = array(
			'item_id'  => 'SHIRT-BLUE-M',
			'quantity' => 2,
			'name'     => 'Blue T-Shirt',
			'price'    => array(
				'currency_code' => 'USD',
				'value'         => '25.00',
			),
		);

		$item   = CartItem::from_array( $data );
		$issues = $item->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'SHIRT-BLUE-M', $item->item_id() );
		$this->assertNull( $item->variant_id() );
		$this->assertNull( $item->parent_id() );
	}

	/**
	 * Tests new structure with variant_id and parent_id.
	 */
	public function test_new_structure_with_variant_and_parent(): void {
		$data = array(
			'variant_id' => 'SHIRT-BLUE-M-COTTON',
			'parent_id'  => 'SHIRT-COLLECTION-001',
			'quantity'   => 2,
			'name'       => 'Blue Cotton T-Shirt (Medium)',
			'price'      => array(
				'currency_code' => 'USD',
				'value'         => '25.00',
			),
		);

		$item   = CartItem::from_array( $data );
		$issues = $item->validate();

		$this->assertEmpty( $issues );
		$this->assertNull( $item->item_id() );
		$this->assertSame( 'SHIRT-BLUE-M-COTTON', $item->variant_id() );
		$this->assertSame( 'SHIRT-COLLECTION-001', $item->parent_id() );
	}

	/**
	 * Tests cart item with all three ID fields populated.
	 */
	public function test_item_with_all_id_fields(): void {
		$data = array(
			'item_id'    => 'SHIRT-BLUE-M',
			'variant_id' => 'SHIRT-BLUE-M-COTTON',
			'parent_id'  => 'SHIRT-COLLECTION-001',
			'quantity'   => 2,
		);

		$item   = CartItem::from_array( $data );
		$issues = $item->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'SHIRT-BLUE-M', $item->item_id() );
		$this->assertSame( 'SHIRT-BLUE-M-COTTON', $item->variant_id() );
		$this->assertSame( 'SHIRT-COLLECTION-001', $item->parent_id() );
	}

	/**
	 * Tests minimal cart item with only required quantity.
	 */
	public function test_minimal_cart_item_with_only_quantity(): void {
		$data = array( 'quantity' => 1 );
		$item = CartItem::from_array( $data );

		$issues = $item->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 1, $item->quantity() );
		$this->assertNull( $item->item_id() );
		$this->assertNull( $item->variant_id() );
		$this->assertNull( $item->parent_id() );
		$this->assertNull( $item->name() );
		$this->assertNull( $item->description() );
		$this->assertNull( $item->price() );
		$this->assertNull( $item->selected_attributes() );
		$this->assertNull( $item->gift_options() );
	}

	/**
	 * Tests cart item with single selected attribute.
	 */
	public function test_single_selected_attribute(): void {
		$data = array(
			'selected_attributes' => array(
				array(
					'name'  => 'Size',
					'value' => 'Large',
				),
			),
			'quantity'            => 1,
		);

		$item       = CartItem::from_array( $data );
		$issues     = $item->validate();
		$attributes = $item->selected_attributes();

		$this->assertEmpty( $issues );
		$this->assertIsArray( $attributes );
		$this->assertCount( 1, $attributes );
		$this->assertSame( 'Size', $attributes[0]['name'] );
		$this->assertSame( 'Large', $attributes[0]['value'] );
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

	/**
	 * Tests cart item with valid alphanumeric and hyphen characters in IDs.
	 */
	public function test_valid_id_characters(): void {
		$data = array(
			'item_id'    => 'SHIRT-001-BLUE',
			'variant_id' => 'VAR-123-ABC-xyz',
			'parent_id'  => 'PARENT-456',
			'quantity'   => 1,
		);

		$item   = CartItem::from_array( $data );
		$issues = $item->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'SHIRT-001-BLUE', $item->item_id() );
		$this->assertSame( 'VAR-123-ABC-xyz', $item->variant_id() );
		$this->assertSame( 'PARENT-456', $item->parent_id() );
	}

	/**
	 * Tests complete cart item matching schema example.
	 */
	public function test_complete_cart_item_from_schema_example(): void {
		$data = array(
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
		);

		$item   = CartItem::from_array( $data );
		$issues = $item->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 'SHIRT-BLUE-M-COTTON', $item->variant_id() );
		$this->assertSame( 'SHIRT-COLLECTION-001', $item->parent_id() );
		$this->assertSame( 2, $item->quantity() );
		$this->assertSame( 'Blue Cotton T-Shirt (Medium)', $item->name() );
		$this->assertInstanceOf( Money::class, $item->price() );
		$this->assertCount( 2, $item->selected_attributes() );
	}
}
