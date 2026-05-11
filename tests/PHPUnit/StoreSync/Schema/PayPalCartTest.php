<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\ShippingOption
 */
class PayPalCartTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return PayPalCart::class;
	}

	protected function get_valid_data(): array {
		return array(
			'items'            => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
					'name'     => 'Blue T-Shirt',
					'price'    => array(
						'currency_code' => 'usd',
						'value'         => '25.00',
					),
				),
			),
			'customer'         => array(
				'email_address' => 'customer@example.com',
				'name'          => array(
					'given_name' => 'John',
					'surname'    => 'Smith',
				),
			),
			'shipping_address' => array(
				'address_line_1' => '123 Main Street',
				'admin_area_2'   => 'San Jose',
				'admin_area_1'   => 'CA',
				'postal_code'    => '95131',
				'country_code'   => 'us',
			),
			'billing_address'  => array(
				'address_line_1' => '456 Payment Blvd',
				'admin_area_2'   => 'New York',
				'admin_area_1'   => 'NY',
				'postal_code'    => '10001',
				'country_code'   => 'us',
			),
			'payment_method'   => array(
				'type' => 'paypal',
			),
			'checkout_fields'  => array(
				array(
					'type'   => 'AGE_VERIFICATION_21_PLUS',
					'status' => 'pending',
				),
			),
			'coupons'          => array(
				array(
					'code'   => 'SAVE10',
					'action' => 'apply',
				),
			),
			'geo_coordinates'  => array(
				'latitude'     => '37.7749',
				'longitude'    => '-122.4194',
				'subdivision'  => 'CA',
				'country_code' => 'us',
			),
		);
	}

	protected function get_expected_data(): array {
		return array(
			'items.0.item_id'                 => 'SHIRT-001',
			'items.0.quantity'                => 1,
			'items.0.name'                    => 'Blue T-Shirt',
			'items.0.price.currency_code'     => 'USD',
			'items.0.price.value'             => 25.00,
			'customer.email_address'          => 'customer@example.com',
			'customer.name.given_name'        => 'John',
			'customer.name.surname'           => 'Smith',
			'shipping_address.address_line_1' => '123 Main Street',
			'shipping_address.admin_area_2'   => 'San Jose',
			'shipping_address.admin_area_1'   => 'CA',
			'shipping_address.postal_code'    => '95131',
			'shipping_address.country_code'   => 'US',
			'billing_address.address_line_1'  => '456 Payment Blvd',
			'billing_address.admin_area_2'    => 'New York',
			'billing_address.admin_area_1'    => 'NY',
			'billing_address.postal_code'     => '10001',
			'billing_address.country_code'    => 'US',
			'payment_method.type'             => 'paypal',
			'checkout_fields.0.type'          => 'AGE_VERIFICATION_21_PLUS',
			'checkout_fields.0.status'        => 'PENDING',
			'coupons.0.code'                  => 'SAVE10',
			'coupons.0.action'                => 'APPLY',
			'geo_coordinates.latitude'        => 37.7749,
			'geo_coordinates.longitude'       => - 122.4194,
			'geo_coordinates.subdivision'     => 'CA',
			'geo_coordinates.country_code'    => 'US',
		);
	}

	protected function get_data_types(): array {
		return array(
			'customer'         => array( 'type' => 'array', 'valid' => array() ),
			'shipping_address' => array( 'type' => 'array', 'valid' => array() ),
			'billing_address'  => array( 'type' => 'array', 'valid' => array() ),
			'payment_method'   => array( 'type' => 'array', 'valid' => array() ),
			'checkout_fields'  => array( 'type' => 'array', 'valid' => array() ),
			'coupons'          => array( 'type' => 'array', 'valid' => array() ),
			'geo_coordinates'  => array( 'type' => 'array', 'valid' => array() ),
		);
	}

	protected function mandatory_data(): array {
		return array(
			'payment_method' => array( 'type' => 'paypal' ),
			'items'          => array(
				array( 'quantity' => 1 ),
			),
		);
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'items' );
		$this->assertRequiredField( 'payment_method' );

		$this->assertOptionalField( 'customer' );
		$this->assertOptionalField( 'shipping_address' );
		$this->assertOptionalField( 'billing_address' );
		$this->assertOptionalField( 'checkout_fields' );
		$this->assertOptionalField( 'coupons' );
		$this->assertOptionalField( 'geo_coordinates' );
	}

	public function test_array_fields(): void {
		$car_item       = [ 'quantity' => 1 ];
		$checkout_field = [ 'type' => 'GIFT_MESSAGE', 'status' => 'PENDING' ];

		$this->assertArrayFieldMinCount( 'items', 1, $car_item );
		$this->assertArrayFieldMaxCount( 'items', 100, $car_item );
		$this->assertArrayFieldMaxCount( 'checkout_fields', 20, $checkout_field );
	}

	/**
	 * @scenario Cart payload includes a selected shipping option
	 *
	 * Given a PayPal cart payload containing an available_shipping_options entry
	 * with isSelected set to true
	 * When PayPalCart::from_array() parses that payload
	 * Then available_shipping_options() returns a non-empty array
	 * And the first element is a ShippingOption instance
	 * And that ShippingOption reports isSelected() as true with id() 'flat_rate:4'
	 */
	public function test_available_shipping_options_are_parsed_from_input(): void {
		$input = array_merge(
			$this->mandatory_data(),
			array(
				'available_shipping_options' => array(
					array(
						'id'          => 'flat_rate:4',
						'name'        => 'Flat Rate',
						'price'       => array( 'currency_code' => 'USD', 'value' => '5.00' ),
						'is_selected' => true,
					),
				),
			)
		);

		$cart = PayPalCart::from_array( $input, new StoreValidation() );

		$options = $cart->available_shipping_options();
		$this->assertNotNull( $options, 'available_shipping_options() must not return null when options are provided' );
		$this->assertNotEmpty( $options, 'available_shipping_options() must return a non-empty array' );
		$this->assertInstanceOf( ShippingOption::class, $options[0] );
		$this->assertTrue( $options[0]->is_selected(), 'The first option must report is_selected() = true' );
		$this->assertSame( 'flat_rate:4', $options[0]->id() );
	}

	/**
	 * @scenario Cart payload has no available_shipping_options key
	 *
	 * Given a minimal PayPal cart payload without an available_shipping_options key
	 * When PayPalCart::from_array() parses that payload
	 * Then available_shipping_options() returns null
	 * And no validation issues are raised for the missing field
	 */
	public function test_available_shipping_options_is_optional_and_returns_null_when_absent(): void {
		$this->assertOptionalField( 'available_shipping_options' );
	}

	public function test_validation_issue_propagation(): void {
		/**
		 * The PayPalCart instance is expected to collect ALL issues from child classes.
		 *
		 * This input should generate 3 validation issues:
		 * 1. 'items' - at least one item must be provided (MissingField)
		 * 2. 'payment_method' - this field is expected (MissingField)
		 * 3. 'customer.email_address' - when provided, must be a valid email (InvalidData))
		 */
		$multiple_problems = array(
			'items'    => array(),
			'customer' => array( 'email_address' => 'not-provided' ),
		);

		$validation = new StoreValidation();
		PayPalCart::from_array( $multiple_problems, $validation );

		$issues = $validation->all();
		$this->assertCount( 3, $issues );
	}

	// -------------------------------------------------------------------------
	// Group — to_array()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a minimal PayPalCart with items and payment_method only
	 * WHEN to_array() is called
	 * THEN items and payment_method are present; optional fields are absent
	 */
	public function test_to_array_contains_items_and_payment_method_for_minimal_cart(): void {
		$cart = PayPalCart::from_array(
			array(
				'items'          => array( array( 'item_id' => 'SKU-1', 'quantity' => 2 ) ),
				'payment_method' => array( 'type' => 'paypal' ),
			),
			new StoreValidation()
		);

		$result = $cart->to_array();

		$this->assertArrayHasKey( 'items', $result );
		$this->assertCount( 1, $result['items'] );
		$this->assertSame( 'SKU-1', $result['items'][0]['item_id'] );
		$this->assertSame( 2, $result['items'][0]['quantity'] );
		$this->assertArrayHasKey( 'payment_method', $result );
		$this->assertSame( 'paypal', $result['payment_method']['type'] );
		$this->assertArrayNotHasKey( 'customer', $result );
		$this->assertArrayNotHasKey( 'shipping_address', $result );
		$this->assertArrayNotHasKey( 'billing_address', $result );
	}

	/**
	 * GIVEN a PayPalCart with a country_code provided as lowercase 'us'
	 * WHEN to_array() is called
	 * THEN the shipping_address country_code is normalized to uppercase 'US'
	 */
	public function test_to_array_normalizes_country_code_to_uppercase(): void {
		$cart = PayPalCart::from_array(
			array(
				'items'            => array( array( 'quantity' => 1 ) ),
				'payment_method'   => array( 'type' => 'paypal' ),
				'shipping_address' => array(
					'country_code'   => 'us',
					'address_line_1' => '123 Main St',
				),
			),
			new StoreValidation()
		);

		$result = $cart->to_array();

		$this->assertArrayHasKey( 'shipping_address', $result );
		$this->assertSame( 'US', $result['shipping_address']['country_code'] );
	}

	/**
	 * GIVEN a PayPalCart with customer, shipping_address, and billing_address
	 * WHEN to_array() is called
	 * THEN all optional nested objects are delegated to their own to_array() and appear in the result
	 */
	public function test_to_array_delegates_optional_fields_to_nested_to_array(): void {
		$cart = PayPalCart::from_array(
			array(
				'items'            => array( array( 'quantity' => 1 ) ),
				'payment_method'   => array( 'type' => 'paypal' ),
				'customer'         => array( 'email_address' => 'test@example.com' ),
				'shipping_address' => array( 'country_code' => 'DE' ),
				'billing_address'  => array( 'country_code' => 'DE' ),
			),
			new StoreValidation()
		);

		$result = $cart->to_array();

		$this->assertArrayHasKey( 'customer', $result );
		$this->assertSame( 'test@example.com', $result['customer']['email_address'] );
		$this->assertArrayHasKey( 'shipping_address', $result );
		$this->assertSame( 'DE', $result['shipping_address']['country_code'] );
		$this->assertArrayHasKey( 'billing_address', $result );
		$this->assertSame( 'DE', $result['billing_address']['country_code'] );
	}

	/**
	 * GIVEN a PayPalCart with customer whose to_array() returns an empty array
	 *       (Customer has no fields set, but is present in input)
	 * WHEN to_array() is called
	 * THEN customer is absent from the result because an empty array is falsy for array_filter
	 */
	public function test_to_array_omits_customer_when_customer_to_array_is_empty(): void {
		// An empty customer object (no valid fields) produces an empty to_array().
		$cart = PayPalCart::from_array(
			array(
				'items'          => array( array( 'quantity' => 1 ) ),
				'payment_method' => array( 'type' => 'paypal' ),
				'customer'       => array(),
			),
			new StoreValidation()
		);

		$result = $cart->to_array();

		$this->assertArrayNotHasKey( 'customer', $result );
	}
}
