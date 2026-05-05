<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

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
						'id'         => 'flat_rate:4',
						'name'       => 'Flat Rate',
						'price'      => array( 'currency_code' => 'USD', 'value' => '5.00' ),
						'isSelected' => true,
					),
				),
			)
		);

		$cart = PayPalCart::from_array( $input );

		$options = $cart->available_shipping_options();
		$this->assertNotNull( $options, 'available_shipping_options() must not return null when options are provided' );
		$this->assertNotEmpty( $options, 'available_shipping_options() must return a non-empty array' );
		$this->assertInstanceOf( ShippingOption::class, $options[0] );
		$this->assertTrue( $options[0]->isSelected(), 'The first option must report isSelected() = true' );
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

		$testee = PayPalCart::from_array( $multiple_problems );

		$issues = $testee->issues();
		$this->assertCount( 3, $issues );
	}
}
