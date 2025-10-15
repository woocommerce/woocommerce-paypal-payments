<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers PayPalCart
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
						'currency_code' => 'USD',
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
				'country_code'   => 'US',
			),
			'billing_address'  => array(
				'address_line_1' => '456 Payment Blvd',
				'admin_area_2'   => 'New York',
				'admin_area_1'   => 'NY',
				'postal_code'    => '10001',
				'country_code'   => 'US',
			),
			'payment_method'   => array(
				'type' => 'paypal',
			),
			'checkout_fields'  => array(
				array(
					'type'   => 'AGE_VERIFICATION_21_PLUS',
					'status' => 'PENDING',
				),
			),
			'coupons'          => array(
				array(
					'code'   => 'SAVE10',
					'action' => 'APPLY',
				),
			),
			'geo_coordinates'  => array(
				'latitude'     => '37.7749',
				'longitude'    => '-122.4194',
				'subdivision'  => 'CA',
				'country_code' => 'US',
			),
		);
	}

	protected function get_expected_data(): array {
		return array(
			'items.0.item_id'                 => 'SHIRT-001',
			'items.0.quantity'                => 1,
			'items.0.name'                    => 'Blue T-Shirt',
			'items.0.price.currency'          => 'USD',
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
	}

	public function test_optional_fields(): void {
		$this->assertOptionalField( 'customer' );
		$this->assertOptionalField( 'shipping_address' );
		$this->assertOptionalField( 'billing_address' );
		$this->assertOptionalField( 'checkout_fields' );
		$this->assertOptionalField( 'coupons' );
		$this->assertOptionalField( 'geo_coordinates' );
	}

	/**
	 * Tests that PayPalCart stores and returns the items array.
	 */
	public function test_items_accessor(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'payment_method' => array( 'type' => 'paypal' ),
		);

		$cart  = PayPalCart::from_array( $data );
		$items = $cart->items();

		$this->assertIsArray( $items );
		$this->assertCount( 1, $items );
		$this->assertInstanceOf( CartItem::class, $items[0] );
	}

	/**
	 * Tests that PayPalCart stores and returns the customer object.
	 */
	public function test_customer_accessor(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'customer'       => array(
				'email_address' => 'customer@example.com',
			),
			'payment_method' => array( 'type' => 'paypal' ),
		);

		$cart     = PayPalCart::from_array( $data );
		$customer = $cart->customer();

		$this->assertInstanceOf( Customer::class, $customer );
	}

	/**
	 * Tests that PayPalCart stores and returns the shipping_address object.
	 */
	public function test_shipping_address_accessor(): void {
		$data = array(
			'items'            => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'shipping_address' => array(
				'address_line_1' => '123 Main Street',
				'country_code'   => 'US',
			),
			'payment_method'   => array( 'type' => 'paypal' ),
		);

		$cart     = PayPalCart::from_array( $data );
		$shipping = $cart->shipping_address();

		$this->assertInstanceOf( Address::class, $shipping );
	}

	/**
	 * Tests that PayPalCart stores and returns the billing_address object.
	 */
	public function test_billing_address_accessor(): void {
		$data = array(
			'items'           => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'billing_address' => array(
				'address_line_1' => '456 Payment Blvd',
				'country_code'   => 'US',
			),
			'payment_method'  => array( 'type' => 'paypal' ),
		);

		$cart    = PayPalCart::from_array( $data );
		$billing = $cart->billing_address();

		$this->assertInstanceOf( Address::class, $billing );
	}

	/**
	 * Tests that PayPalCart stores and returns the payment_method object.
	 */
	public function test_payment_method_accessor(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'payment_method' => array(
				'type'  => 'paypal',
				'token' => 'EC-TOKEN123',
			),
		);

		$cart    = PayPalCart::from_array( $data );
		$payment = $cart->payment_method();

		$this->assertInstanceOf( PaymentMethod::class, $payment );
	}

	/**
	 * Tests that PayPalCart stores and returns the checkout_fields array.
	 */
	public function test_checkout_fields_accessor(): void {
		$data = array(
			'items'           => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'checkout_fields' => array(
				array(
					'type'   => 'AGE_VERIFICATION_21_PLUS',
					'status' => 'PENDING',
				),
				array(
					'type'   => 'GIFT_MESSAGE',
					'status' => 'PENDING',
				),
			),
			'payment_method'  => array( 'type' => 'paypal' ),
		);

		$cart   = PayPalCart::from_array( $data );
		$fields = $cart->checkout_fields();

		$this->assertIsArray( $fields );
		$this->assertCount( 2, $fields );
		$this->assertInstanceOf( CheckoutField::class, $fields[0] );
		$this->assertInstanceOf( CheckoutField::class, $fields[1] );
	}

	/**
	 * Tests that PayPalCart stores and returns the coupons array.
	 */
	public function test_coupons_accessor(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'coupons'        => array(
				array(
					'code'   => 'SAVE10',
					'action' => 'APPLY',
				),
				array(
					'code'   => 'FREESHIP',
					'action' => 'APPLY',
				),
			),
			'payment_method' => array( 'type' => 'paypal' ),
		);

		$cart    = PayPalCart::from_array( $data );
		$coupons = $cart->coupons();

		$this->assertIsArray( $coupons );
		$this->assertCount( 2, $coupons );
		$this->assertInstanceOf( Coupon::class, $coupons[0] );
		$this->assertInstanceOf( Coupon::class, $coupons[1] );
	}

	/**
	 * Tests that PayPalCart stores and returns the geo_coordinates object.
	 */
	public function test_geo_coordinates_accessor(): void {
		$data = array(
			'items'           => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'geo_coordinates' => array(
				'latitude'     => '37.7749',
				'longitude'    => '-122.4194',
				'subdivision'  => 'CA',
				'country_code' => 'US',
			),
			'payment_method'  => array( 'type' => 'paypal' ),
		);

		$cart = PayPalCart::from_array( $data );
		$geo  = $cart->geo_coordinates();

		$this->assertInstanceOf( GeoCoordinates::class, $geo );
	}

	/**
	 * @dataProvider optional_field_provider
	 */
	public function test_optional_fields_return_null_when_missing( string $field_name, string $getter_method ): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'payment_method' => array( 'type' => 'paypal' ),
		);

		$cart = PayPalCart::from_array( $data );

		$this->assertNull( $cart->$getter_method() );
	}

	public function optional_field_provider(): array {
		return array(
			'customer'         => array( 'customer', 'customer' ),
			'shipping_address' => array( 'shipping_address', 'shipping_address' ),
			'billing_address'  => array( 'billing_address', 'billing_address' ),
			'checkout_fields'  => array( 'checkout_fields', 'checkout_fields' ),
			'coupons'          => array( 'coupons', 'coupons' ),
			'geo_coordinates'  => array( 'geo_coordinates', 'geo_coordinates' ),
		);
	}

	/**
	 * Tests that items field is required and produces validation issue when missing.
	 */
	public function test_items_missing_produces_validation_issue(): void {
		$data = array(
			'payment_method' => array( 'type' => 'paypal' ),
		);

		$cart       = PayPalCart::from_array( $data );
		$issues     = $cart->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'items', $issue_data['field'] );
	}

	/**
	 * Tests that payment_method field is required and produces validation issue when missing.
	 */
	public function test_payment_method_missing_produces_validation_issue(): void {
		$data = array(
			'items' => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
		);

		$cart       = PayPalCart::from_array( $data );
		$issues     = $cart->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'payment_method', $issue_data['field'] );
	}

	/**
	 * Tests that items array with zero items produces validation issue.
	 */
	public function test_items_empty_array_produces_validation_issue(): void {
		$data = array(
			'items'          => array(),
			'payment_method' => array( 'type' => 'paypal' ),
		);

		$cart       = PayPalCart::from_array( $data );
		$issues     = $cart->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'items', $issue_data['field'] );
	}

	/**
	 * Tests that items array with exactly 1 item is valid.
	 */
	public function test_items_at_minimum_is_valid(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'payment_method' => array( 'type' => 'paypal' ),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that items array exceeding 100 items produces validation issue.
	 */
	public function test_items_exceeds_maximum(): void {
		$items = array();
		for ( $i = 1; $i <= 101; $i ++ ) {
			$items[] = array(
				'item_id'  => "ITEM-$i",
				'quantity' => 1,
			);
		}

		$data = array(
			'items'          => $items,
			'payment_method' => array( 'type' => 'paypal' ),
		);

		$cart       = PayPalCart::from_array( $data );
		$issues     = $cart->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'items', $issue_data['field'] );
	}

	/**
	 * Tests that items array with exactly 100 items is valid.
	 */
	public function test_items_at_maximum_is_valid(): void {
		$items = array();
		for ( $i = 1; $i <= 100; $i ++ ) {
			$items[] = array(
				'item_id'  => "ITEM-$i",
				'quantity' => 1,
			);
		}

		$data = array(
			'items'          => $items,
			'payment_method' => array( 'type' => 'paypal' ),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that checkout_fields array exceeding 20 fields produces validation issue.
	 */
	public function test_checkout_fields_exceeds_maximum(): void {
		$fields = array();
		for ( $i = 1; $i <= 21; $i ++ ) {
			$fields[] = array(
				'type'   => 'GIFT_MESSAGE',
				'status' => 'PENDING',
			);
		}

		$data = array(
			'items'           => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'checkout_fields' => $fields,
			'payment_method'  => array( 'type' => 'paypal' ),
		);

		$cart       = PayPalCart::from_array( $data );
		$issues     = $cart->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'checkout_fields', $issue_data['field'] );
	}

	/**
	 * Tests that checkout_fields array with exactly 20 fields is valid.
	 */
	public function test_checkout_fields_at_maximum_is_valid(): void {
		$fields = array();
		for ( $i = 1; $i <= 20; $i ++ ) {
			$fields[] = array(
				'type'   => 'GIFT_MESSAGE',
				'status' => 'PENDING',
			);
		}

		$data = array(
			'items'           => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'checkout_fields' => $fields,
			'payment_method'  => array( 'type' => 'paypal' ),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * @dataProvider invalid_type_provider
	 */
	public function test_fields_reject_invalid_types( string $field_name, $invalid_value, string $getter_method, $expected_default ): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'payment_method' => array( 'type' => 'paypal' ),
			$field_name      => $invalid_value,
		);

		$cart = PayPalCart::from_array( $data );

		$this->assertSame( $expected_default, $cart->$getter_method() );
	}

	public function invalid_type_provider(): array {
		return array(
			'items with string'            => array( 'items', 'items', 'items', array() ),
			'items with object'            => array(
				'items',
				array( 'item_id' => 'SHIRT-001' ),
				'items',
				array(),
			),
			'customer with string'         => array(
				'customer',
				'customer@example.com',
				'customer',
				null,
			),
			'shipping_address with string' => array(
				'shipping_address',
				'123 Main St',
				'shipping_address',
				null,
			),
			'billing_address with string'  => array(
				'billing_address',
				'456 Payment Blvd',
				'billing_address',
				null,
			),
			'payment_method with string'   => array(
				'payment_method',
				'paypal',
				'payment_method',
				null,
			),
			'checkout_fields with string'  => array(
				'checkout_fields',
				'age_verification',
				'checkout_fields',
				null,
			),
			'coupons with string'          => array( 'coupons', 'SAVE10', 'coupons', null ),
			'geo_coordinates with string'  => array(
				'geo_coordinates',
				'37.7749,-122.4194',
				'geo_coordinates',
				null,
			),
		);
	}

	/**
	 * Tests that multiple validation errors are all returned together.
	 */
	public function test_multiple_validation_errors_returned_together(): void {
		$data = array(
			'items'           => array(),
			'checkout_fields' => array_fill( 0, 21, array(
				'type'   => 'GIFT_MESSAGE',
				'status' => 'PENDING',
			) ),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertCount( 3, $issues, 'Should return all validation errors at once' );

		$fields = array_map(
			function ( $issue ) {
				return $issue->to_array()['field'];
			},
			$issues
		);

		$this->assertContains( 'items', $fields );
		$this->assertContains( 'payment_method', $fields );
		$this->assertContains( 'checkout_fields', $fields );
	}

	/**
	 * Tests minimal cart creation with only required fields.
	 */
	public function test_minimal_cart_creation(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
		$this->assertCount( 1, $cart->items() );
		$this->assertInstanceOf( PaymentMethod::class, $cart->payment_method() );
		$this->assertNull( $cart->customer() );
		$this->assertNull( $cart->shipping_address() );
	}

	/**
	 * Tests cart creation matching schema's minimal example.
	 */
	public function test_cart_from_schema_minimal_example(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
		$this->assertInstanceOf( PaymentMethod::class, $cart->payment_method() );
	}

	/**
	 * Tests complete cart with all optional fields populated.
	 */
	public function test_complete_cart_with_all_fields(): void {
		$data = array(
			'items'            => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'customer'         => array(
				'email_address' => 'customer@example.com',
			),
			'shipping_address' => array(
				'address_line_1' => '123 Main Street',
				'country_code'   => 'US',
			),
			'billing_address'  => array(
				'address_line_1' => '456 Payment Blvd',
				'country_code'   => 'US',
			),
			'payment_method'   => array(
				'type' => 'paypal',
			),
			'checkout_fields'  => array(
				array(
					'type'   => 'AGE_VERIFICATION_21_PLUS',
					'status' => 'PENDING',
				),
			),
			'coupons'          => array(
				array(
					'code'   => 'SAVE10',
					'action' => 'APPLY',
				),
			),
			'geo_coordinates'  => array(
				'latitude'     => '37.7749',
				'longitude'    => '-122.4194',
				'subdivision'  => 'CA',
				'country_code' => 'US',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
		$this->assertInstanceOf( Customer::class, $cart->customer() );
		$this->assertInstanceOf( Address::class, $cart->shipping_address() );
		$this->assertInstanceOf( Address::class, $cart->billing_address() );
		$this->assertInstanceOf( PaymentMethod::class, $cart->payment_method() );
		$this->assertIsArray( $cart->checkout_fields() );
		$this->assertIsArray( $cart->coupons() );
		$this->assertInstanceOf( GeoCoordinates::class, $cart->geo_coordinates() );
	}

	/**
	 * Tests AI agent use case: creating initial cart.
	 */
	public function test_ai_agent_creates_initial_cart(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-BLUE-M',
					'quantity' => 1,
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
		$this->assertCount( 1, $cart->items() );
	}

	/**
	 * Tests AI agent use case: adding customer information.
	 */
	public function test_ai_agent_adds_customer_information(): void {
		$data = array(
			'items'            => array(
				array(
					'item_id'  => 'SHIRT-BLUE-M',
					'quantity' => 1,
				),
			),
			'customer'         => array(
				'email_address' => 'john.smith@example.com',
			),
			'shipping_address' => array(
				'address_line_1' => '123 Main Street',
				'country_code'   => 'US',
			),
			'payment_method'   => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
		$this->assertInstanceOf( Customer::class, $cart->customer() );
		$this->assertInstanceOf( Address::class, $cart->shipping_address() );
	}

	/**
	 * Tests AI agent use case: age-restricted product cart.
	 */
	public function test_age_restricted_product_cart(): void {
		$data = array(
			'items'           => array(
				array(
					'item_id'  => 'WINE-CABERNET-2019',
					'quantity' => 2,
				),
			),
			'checkout_fields' => array(
				array(
					'type'   => 'AGE_VERIFICATION_21_PLUS',
					'status' => 'PENDING',
				),
			),
			'payment_method'  => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
		$this->assertCount( 1, $cart->checkout_fields() );
	}

	/**
	 * Tests cart with multiple items.
	 */
	public function test_cart_with_multiple_items(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 2,
				),
				array(
					'item_id'  => 'JEANS-001',
					'quantity' => 1,
				),
				array(
					'item_id'  => 'SHOES-001',
					'quantity' => 1,
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
		$this->assertCount( 3, $cart->items() );
	}

	/**
	 * Tests cart with multiple coupons.
	 */
	public function test_cart_with_multiple_coupons(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'coupons'        => array(
				array(
					'code'   => 'SAVE10',
					'action' => 'APPLY',
				),
				array(
					'code'   => 'FREESHIP',
					'action' => 'APPLY',
				),
				array(
					'code'   => 'WELCOME',
					'action' => 'APPLY',
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
		$this->assertCount( 3, $cart->coupons() );
	}

	/**
	 * Tests cart with multiple checkout fields.
	 */
	public function test_cart_with_multiple_checkout_fields(): void {
		$data = array(
			'items'           => array(
				array(
					'item_id'  => 'WINE-001',
					'quantity' => 1,
				),
			),
			'checkout_fields' => array(
				array(
					'type'   => 'AGE_VERIFICATION_21_PLUS',
					'status' => 'PENDING',
				),
				array(
					'type'   => 'GIFT_MESSAGE',
					'status' => 'PENDING',
				),
				array(
					'type'   => 'DELIVERY_INSTRUCTIONS',
					'status' => 'PENDING',
				),
			),
			'payment_method'  => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
		$this->assertCount( 3, $cart->checkout_fields() );
	}

	/**
	 * Tests that empty checkout_fields array is stored.
	 */
	public function test_empty_checkout_fields_array(): void {
		$data = array(
			'items'           => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'checkout_fields' => array(),
			'payment_method'  => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$fields = $cart->checkout_fields();

		$this->assertIsArray( $fields );
		$this->assertEmpty( $fields );
	}

	/**
	 * Tests that empty coupons array is stored.
	 */
	public function test_empty_coupons_array(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'coupons'        => array(),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		$cart    = PayPalCart::from_array( $data );
		$coupons = $cart->coupons();

		$this->assertIsArray( $coupons );
		$this->assertEmpty( $coupons );
	}

	/**
	 * Tests cart with both shipping and billing addresses.
	 */
	public function test_cart_with_separate_shipping_and_billing(): void {
		$data = array(
			'items'            => array(
				array(
					'item_id'  => 'SHIRT-001',
					'quantity' => 1,
				),
			),
			'shipping_address' => array(
				'address_line_1' => '123 Delivery Street',
				'country_code'   => 'US',
			),
			'billing_address'  => array(
				'address_line_1' => '456 Billing Avenue',
				'country_code'   => 'US',
			),
			'payment_method'   => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
		$this->assertInstanceOf( Address::class, $cart->shipping_address() );
		$this->assertInstanceOf( Address::class, $cart->billing_address() );
	}

	/**
	 * Tests backwards compatible cart with item_id only.
	 */
	public function test_backwards_compatible_cart_structure(): void {
		$data = array(
			'items'          => array(
				array(
					'item_id'  => 'SHIRT-BLUE-M',
					'quantity' => 2,
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests new structure cart with variant_id and parent_id.
	 */
	public function test_new_structure_cart_with_variant_ids(): void {
		$data = array(
			'items'          => array(
				array(
					'variant_id' => 'SHIRT-BLUE-M-COTTON',
					'parent_id'  => 'SHIRT-COLLECTION-001',
					'quantity'   => 2,
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		$cart   = PayPalCart::from_array( $data );
		$issues = $cart->validate();

		$this->assertEmpty( $issues );
	}
}
