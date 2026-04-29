<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Response;

use Brain\Monkey;
use Mockery;
use WC_Order;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse
 */
class CartResponseTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		Monkey\Functions\when( 'get_woocommerce_currency' )->justReturn( 'USD' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function create_mock_wc_cart( float $item_total = 100.0, float $discount = 0.0, float $shipping = 0.0, float $tax = 0.0 ): \WC_Cart {
		$wc_cart = Mockery::mock( \WC_Cart::class );
		$wc_cart->allows( 'get_cart_contents_total' )->andReturn( (string) $item_total );
		$wc_cart->allows( 'get_discount_total' )->andReturn( (string) $discount );
		$wc_cart->allows( 'get_shipping_total' )->andReturn( (string) $shipping );
		$wc_cart->allows( 'get_total_tax' )->andReturn( (string) $tax );

		$cart_total = $item_total - $discount + $shipping + $tax;
		$wc_cart->allows( 'get_total' )->andReturn( (string) $cart_total );

		return $wc_cart;
	}

	/**
	 * Returns a minimal cart data array with one item at the given unit price.
	 *
	 * @param string $variant_id SKU / variant identifier.
	 * @param int    $quantity   Number of units.
	 * @param string $unit_price Price per unit as a decimal string.
	 * @return array<string, mixed>
	 */
	private function make_cart_data( string $variant_id = 'TEST-001', int $quantity = 1, string $unit_price = '100.00' ): array {
		return array(
			'items'          => array(
				array(
					'variant_id' => $variant_id,
					'quantity'   => $quantity,
					'name'       => 'Test Product',
					'price'      => array( 'currency_code' => 'USD', 'value' => $unit_price ),
				),
			),
			'payment_method' => array( 'type' => 'paypal' ),
		);
	}

	// -------------------------------------------------------------------------
	// applied_coupons — presence / absence
	// -------------------------------------------------------------------------

	/**
	 * @scenario Coupon list is populated
	 *
	 * Given a cart with a 10 % coupon applied
	 * When the response is built via create()->applied_coupons()
	 * Then to_array() includes an applied_coupons key
	 * And that key contains exactly one coupon with the correct code, description, and discount
	 * amount
	 */
	public function test_to_array_includes_applied_coupons_when_provided(): void {
		$applied_coupons = array(
			array(
				'code'            => 'TESTCOUPON',
				'description'     => '10% off',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '10.00' ),
			),
		);

		$wc_cart  = $this->create_mock_wc_cart( 100.0, 10.0 );
		$cart     = PayPalCart::from_array( $this->make_cart_data( 'TEST-001', 2, '50.00' ) );
		$response = CartResponse::create( $cart, 'test-cart-id' )->wc_cart( $wc_cart )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'applied_coupons', $result );
		$this->assertIsArray( $result['applied_coupons'] );
		$this->assertCount( 1, $result['applied_coupons'] );

		$coupon = $result['applied_coupons'][0];
		$this->assertEquals( 'TESTCOUPON', $coupon['code'] );
		$this->assertEquals( '10% off', $coupon['description'] );
		$this->assertEquals( 'USD', $coupon['discount_amount']['currency_code'] );
		$this->assertEquals( '10.00', $coupon['discount_amount']['value'] );
	}

	/**
	 * @scenario Coupon list is explicitly empty
	 *
	 * Given a cart with no coupons applied
	 * When the response is built via create()->applied_coupons([])
	 * Then to_array() does not include an applied_coupons key
	 */
	public function test_to_array_excludes_applied_coupons_when_empty(): void {
		$cart     = PayPalCart::from_array( $this->make_cart_data( 'TEST-001', 2, '50.00' ) );
		$response = CartResponse::create( $cart )
			->applied_coupons( array() );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'applied_coupons', $result );
	}

	/**
	 * @scenario applied_coupons() setter is never called
	 *
	 * Given a cart with no coupon information provided at all
	 * When the response is built via create() without chaining applied_coupons()
	 * Then to_array() does not include an applied_coupons key
	 */
	public function test_to_array_excludes_applied_coupons_when_not_provided(): void {
		$cart     = PayPalCart::from_array( $this->make_cart_data( 'TEST-001', 2, '50.00' ) );
		$response = CartResponse::create( $cart );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'applied_coupons', $result );
	}

	// -------------------------------------------------------------------------
	// totals — discount field
	// -------------------------------------------------------------------------

	/**
	 * @scenario Discount field appears when at least one coupon is applied
	 *
	 * Given a cart with a 10 % coupon
	 * When the response is built via create()->applied_coupons()
	 * Then to_array()['totals'] includes a discount key with the correct currency and value
	 */
	public function test_calculate_totals_includes_discount_field_when_coupons_applied(): void {
		$applied_coupons = array(
			array(
				'code'            => 'TESTCOUPON',
				'description'     => '10% off',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '10.00' ),
			),
		);

		$wc_cart  = $this->create_mock_wc_cart( 100.0, 10.0 );
		$cart     = PayPalCart::from_array( $this->make_cart_data( 'TEST-001', 2, '50.00' ) );
		$response = CartResponse::create( $cart, 'test-cart-id' )->wc_cart( $wc_cart )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'discount', $result['totals'] );
		$this->assertEquals( 'USD', $result['totals']['discount']['currency_code'] );
		$this->assertEquals( '10.00', $result['totals']['discount']['value'] );
	}

	/**
	 * @scenario Discount field is absent when no coupons are applied
	 *
	 * Given a cart with no coupons
	 * When the response is built via create() without chaining applied_coupons()
	 * Then to_array()['totals'] does not include a discount key
	 */
	public function test_calculate_totals_excludes_discount_field_when_no_coupons(): void {
		$wc_cart  = $this->create_mock_wc_cart( 100.0 );
		$cart     = PayPalCart::from_array( $this->make_cart_data( 'TEST-001', 2, '50.00' ) );
		$response = CartResponse::create( $cart, 'test-cart-id' )->wc_cart( $wc_cart );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'discount', $result['totals'] );
	}

	/**
	 * @scenario Discount is subtracted from the cart amount
	 *
	 * Given a cart with items totalling $100 and a $20 coupon discount
	 * When the response is built via create()->applied_coupons()
	 * Then item_total is $100.00, discount is $20.00, and amount is $80.00
	 */
	public function test_calculate_totals_subtracts_discount_from_amount(): void {
		$applied_coupons = array(
			array(
				'code'            => 'TESTCOUPON',
				'description'     => '$20 off',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '20.00' ),
			),
		);

		$wc_cart  = $this->create_mock_wc_cart( 100.0, 20.0 );
		$cart     = PayPalCart::from_array( $this->make_cart_data( 'TEST-001', 2, '50.00' ) );
		$response = CartResponse::create( $cart, 'test-cart-id' )->wc_cart( $wc_cart )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		$this->assertEquals( '100.00', $result['totals']['item_total']['value'] );
		$this->assertEquals( '20.00', $result['totals']['discount']['value'] );
		$this->assertEquals( 80.0, $result['totals']['amount']['value'] );
	}

	// -------------------------------------------------------------------------
	// Response structure
	// -------------------------------------------------------------------------

	/**
	 * @scenario Full response shape matches the PayPal cart spec
	 *
	 * Given a cart with one item and no coupons
	 * When the response is built via create()
	 * Then to_array() contains id, status, validation_status, validation_issues, and totals
	 * And totals contains item_total, shipping, tax_total, and amount — each with currency_code
	 * and value
	 */
	public function test_response_structure_matches_paypal_spec(): void {
		$wc_cart  = $this->create_mock_wc_cart( 100.0 );
		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create( $cart, 'test-cart-id' )->wc_cart( $wc_cart );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'validation_status', $result );
		$this->assertArrayHasKey( 'validation_issues', $result );
		$this->assertArrayHasKey( 'totals', $result );

		$this->assertArrayHasKey( 'item_total', $result['totals'] );
		$this->assertArrayHasKey( 'shipping', $result['totals'] );
		$this->assertArrayHasKey( 'tax_total', $result['totals'] );
		$this->assertArrayHasKey( 'amount', $result['totals'] );

		foreach ( array( 'item_total', 'shipping', 'tax_total', 'amount' ) as $field ) {
			$this->assertArrayHasKey( 'currency_code', $result['totals'][ $field ] );
			$this->assertArrayHasKey( 'value', $result['totals'][ $field ] );
		}
	}

	// -------------------------------------------------------------------------
	// Multiple coupons / discount capping
	// -------------------------------------------------------------------------

	/**
	 * @scenario Multiple coupon discount amounts are summed
	 *
	 * Given a cart with two coupons worth $10 and $5 respectively
	 * When the response is built via create()->applied_coupons()
	 * Then the discount total is $15.00 and the cart amount is $85.00
	 */
	public function test_multiple_coupons_discount_amounts_are_summed(): void {
		$applied_coupons = array(
			array(
				'code'            => 'COUPON1',
				'description'     => '10% off',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '10.00' ),
			),
			array(
				'code'            => 'COUPON2',
				'description'     => '$5 off',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '5.00' ),
			),
		);

		$wc_cart  = $this->create_mock_wc_cart( 100.0, 15.0 );
		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create( $cart, 'test-cart-id' )->wc_cart( $wc_cart )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		$this->assertEquals( '15.00', $result['totals']['discount']['value'] );
		$this->assertEquals( 85.0, $result['totals']['amount']['value'] );
	}

	/**
	 * @scenario Combined coupon value exceeds item total
	 *
	 * Given a cart with a $25 T-shirt and two coupons totalling $30
	 * When the response is built via create()->applied_coupons()
	 * Then the discount is capped at $24.99 so that the PayPal minimum amount of $0.01 is preserved
	 * And the item total remains at $25.00
	 */
	public function test_discount_is_capped_when_exceeds_item_total(): void {
		$applied_coupons = array(
			array(
				'code'            => 'SOLO20',
				'description'     => 'fixed_cart discount',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '20.00' ),
			),
			array(
				'code'            => 'GAMING20',
				'description'     => 'fixed_cart discount',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '10.00' ),
			),
		);

		$wc_cart  = $this->create_mock_wc_cart( 25.0, 24.99 );
		$cart     = PayPalCart::from_array( $this->make_cart_data( 'SHIRT-001', 1, '25.00' ) );
		$response = CartResponse::create( $cart, 'test-cart-id' )->wc_cart( $wc_cart )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		$this->assertEquals( '24.99', $result['totals']['discount']['value'] );
		$this->assertEquals( '0.01', $result['totals']['amount']['value'] );
		$this->assertEquals( '25.00', $result['totals']['item_total']['value'] );
	}

	/**
	 * @scenario Coupon value equals item total exactly
	 *
	 * Given a cart with a $50 product and a 100 % off coupon for $50
	 * When the response is built via create()->applied_coupons()
	 * Then the discount is capped at $49.99 to maintain a minimum PayPal order amount of $0.01
	 */
	public function test_discount_equal_to_item_total_is_capped(): void {
		$applied_coupons = array(
			array(
				'code'            => 'FREE100',
				'description'     => '100% off',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '50.00' ),
			),
		);

		$wc_cart  = $this->create_mock_wc_cart( 50.0, 49.99 );
		$cart     = PayPalCart::from_array( $this->make_cart_data( 'TEST-001', 1, '50.00' ) );
		$response = CartResponse::create( $cart, 'test-cart-id' )->wc_cart( $wc_cart )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		$this->assertEquals( '49.99', $result['totals']['discount']['value'] );
		$this->assertEquals( '0.01', $result['totals']['amount']['value'] );
	}

	// -------------------------------------------------------------------------
	// Money formatting
	// -------------------------------------------------------------------------

	/**
	 * @scenario Floating-point arithmetic does not produce precision artefacts
	 *
	 * Given a cart where capped discounts could cause floating-point rounding errors
	 * When the response is built via create()->applied_coupons()
	 * Then the amount value is the string "0.01" — not a float like 0.010000000000001
	 * And it matches the two-decimal-place format pattern
	 */
	public function test_amount_formatting_avoids_floating_point_precision_issues(): void {
		$applied_coupons = array(
			array(
				'code'            => 'SOLO20',
				'description'     => 'fixed_cart discount',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '20.00' ),
			),
			array(
				'code'            => 'GAMING20',
				'description'     => 'fixed_cart discount',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '10.00' ),
			),
		);

		$wc_cart  = $this->create_mock_wc_cart( 25.0, 24.99 );
		$cart     = PayPalCart::from_array( $this->make_cart_data( 'SHIRT-001', 1, '25.00' ) );
		$response = CartResponse::create( $cart, 'test-cart-id' )->wc_cart( $wc_cart )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		$this->assertIsString( $result['totals']['amount']['value'] );
		$this->assertEquals( '0.01', $result['totals']['amount']['value'] );
		$this->assertMatchesRegularExpression( '/^\d+\.\d{2}$/', $result['totals']['amount']['value'] );
	}

	/**
	 * @scenario All money values use the same two-decimal string format
	 *
	 * Given a cart with items, a coupon, shipping, and tax
	 * When the response is built via create()->applied_coupons()
	 * Then every money field in totals is a string matching the pattern ^\d+\.\d{2}$
	 */
	public function test_all_money_values_are_formatted_consistently(): void {
		$applied_coupons = array(
			array(
				'code'            => 'SAVE10',
				'description'     => '$10 off',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '10.00' ),
			),
		);

		$wc_cart  = $this->create_mock_wc_cart( 100.0, 10.0, 5.0, 8.5 );
		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create( $cart, 'test-cart-id' )->wc_cart( $wc_cart )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		$money_fields = array( 'item_total', 'discount', 'shipping', 'tax_total', 'amount' );
		foreach ( $money_fields as $field ) {
			if ( isset( $result['totals'][ $field ] ) ) {
				$this->assertIsString(
					$result['totals'][ $field ]['value'],
					"Field {$field} value should be a string"
				);
				$this->assertMatchesRegularExpression(
					'/^\d+\.\d{2}$/',
					$result['totals'][ $field ]['value'],
					"Field {$field} should match format 'XX.XX'"
				);
			}
		}
	}

	// -------------------------------------------------------------------------
	// shipping_options — presence / absence
	// -------------------------------------------------------------------------

	/**
	 * @scenario shipping_options() is never called
	 *
	 * Given a cart with no shipping options provided
	 * When the response is built via create() without chaining shipping_options()
	 * Then to_array() does not include an available_shipping_options key
	 */
	public function test_to_array_excludes_available_shipping_options_when_setter_not_called(): void {
		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create( $cart );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'available_shipping_options', $result );
	}

	/**
	 * @scenario shipping_options() is called with an empty array
	 *
	 * Given no shipping methods are available for the cart
	 * When the response is built via create()->shipping_options([])
	 * Then to_array() does not include an available_shipping_options key
	 */
	public function test_to_array_excludes_available_shipping_options_when_empty_array(): void {
		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create( $cart )
			->shipping_options( array() );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'available_shipping_options', $result );
	}

	/**
	 * @scenario shipping_options() is called with a non-empty array
	 *
	 * Given one shipping method is available for the cart
	 * When the response is built via create()->shipping_options() with one option
	 * Then to_array() includes an available_shipping_options key
	 * And that key contains exactly one entry
	 */
	public function test_to_array_includes_available_shipping_options_when_provided(): void {
		$shipping_options = array(
			array(
				'id'          => 'flat_rate:1',
				'label'       => 'Flat Rate',
				'amount'      => '5.00',
				'currency'    => 'USD',
				'is_selected' => true,
			),
		);

		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create( $cart )
			->shipping_options( $shipping_options );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'available_shipping_options', $result );
		$this->assertCount( 1, $result['available_shipping_options'] );
	}

	// -------------------------------------------------------------------------
	// shipping_options — option shape
	// -------------------------------------------------------------------------

	/**
	 * @scenario Each shipping option has the required PayPal fields
	 *
	 * Given two shipping options are available
	 * When the response is built via create()->shipping_options()
	 * Then each option in available_shipping_options contains id, label, amount, currency, and
	 * is_selected
	 */
	public function test_shipping_options_each_entry_has_required_keys(): void {
		$shipping_options = array(
			array(
				'id'          => 'flat_rate:1',
				'label'       => 'Flat Rate',
				'amount'      => '5.00',
				'currency'    => 'USD',
				'is_selected' => true,
			),
			array(
				'id'          => 'free_shipping:1',
				'label'       => 'Free Shipping',
				'amount'      => '0.00',
				'currency'    => 'USD',
				'is_selected' => false,
			),
		);

		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create( $cart )
			->shipping_options( $shipping_options );

		$result = $response->to_array();

		foreach ( $result['available_shipping_options'] as $option ) {
			$this->assertArrayHasKey( 'id', $option );
			$this->assertArrayHasKey( 'label', $option );
			$this->assertArrayHasKey( 'amount', $option );
			$this->assertArrayHasKey( 'currency', $option );
			$this->assertArrayHasKey( 'is_selected', $option );
		}
	}

	/**
	 * @scenario Exactly one shipping option is marked as selected
	 *
	 * Given three available shipping options where the second is chosen
	 * When the response is built via create()->shipping_options()
	 * Then exactly one entry in available_shipping_options has is_selected=true
	 * And that entry corresponds to the chosen shipping method
	 */
	public function test_shipping_options_exactly_one_is_selected(): void {
		$shipping_options = array(
			array(
				'id'          => 'flat_rate:1',
				'label'       => 'Flat Rate',
				'amount'      => '5.00',
				'currency'    => 'USD',
				'is_selected' => false,
			),
			array(
				'id'          => 'express_shipping:1',
				'label'       => 'Express Shipping',
				'amount'      => '15.00',
				'currency'    => 'USD',
				'is_selected' => true,
			),
			array(
				'id'          => 'free_shipping:1',
				'label'       => 'Free Shipping',
				'amount'      => '0.00',
				'currency'    => 'USD',
				'is_selected' => false,
			),
		);

		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create( $cart )
			->shipping_options( $shipping_options );

		$result = $response->to_array();

		$selected = array_filter(
			$result['available_shipping_options'],
			static function ( array $option ): bool {
				return $option['is_selected'] === true;
			}
		);

		$this->assertCount( 1, $selected );

		$selected_option = array_values( $selected )[0];
		$this->assertSame( 'express_shipping:1', $selected_option['id'] );
	}

	// -------------------------------------------------------------------------
	// create_new — status and payment_method
	// -------------------------------------------------------------------------

	/**
	 * @scenario New cart POST sets status to CREATED
	 *
	 * Given a PayPal cart and a freshly issued cart ID and token
	 * When the response is built via create_new()
	 * Then to_array()['status'] equals 'CREATED'
	 */
	public function test_create_new_sets_status_to_created(): void {
		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create_new( $cart, 'new-cart-id', 'tok_abc123' );

		$result = $response->to_array();

		$this->assertSame( 'CREATED', $result['status'] );
	}

	/**
	 * @scenario New cart response includes payment_method with type and token
	 *
	 * Given a PayPal cart and a token issued during cart creation
	 * When the response is built via create_new() with that token
	 * Then to_array()['payment_method'] has type='paypal' and token equal to the passed value
	 */
	public function test_create_new_includes_payment_method_with_token(): void {
		$token    = 'tok_xyz789';
		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create_new( $cart, 'new-cart-id', $token );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'payment_method', $result );
		$this->assertSame( 'paypal', $result['payment_method']['type'] );
		$this->assertSame( $token, $result['payment_method']['token'] );
	}

	/**
	 * @scenario New cart response does not expose payment_confirmation
	 *
	 * Given a cart that has just been created via POST
	 * When the response is built via create_new()
	 * Then to_array() does not include a payment_confirmation key
	 */
	public function test_create_new_excludes_payment_confirmation(): void {
		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create_new( $cart, 'new-cart-id', 'tok_abc123' );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'payment_confirmation', $result );
	}

	// -------------------------------------------------------------------------
	// create_completed — status and payment_confirmation
	// -------------------------------------------------------------------------

	/**
	 * @scenario Server-generated cart ID is not overwritten by a caller-supplied ID in the request body
	 *
	 * Given a PayPal cart built from request body data that already contains an "id" key
	 * When the response is built via create_new() with a distinct server-generated cart ID
	 * Then to_array()['id'] equals the server-generated ID
	 * And the caller-supplied ID from the request body is ignored
	 */
	public function test_create_new_id_is_not_overwritten_by_caller_supplied_id(): void {
		$cart_data         = $this->make_cart_data();
		$cart_data['id']   = 'client-supplied-id';
		$cart              = PayPalCart::from_array( $cart_data );
		$response          = CartResponse::create_new( $cart, 'server-generated-id', 'tok_test' );

		$result = $response->to_array();

		$this->assertSame( 'server-generated-id', $result['id'] );
	}

	/**
	 * @scenario Completed checkout sets status to COMPLETED
	 *
	 * Given a cart that has been paid and a corresponding WC_Order
	 * When the response is built via create_completed()
	 * Then to_array()['status'] equals 'COMPLETED'
	 */
	public function test_create_completed_sets_status_to_completed(): void {
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_id' )->andReturn( 42 );
		$wc_order->allows( 'get_checkout_order_received_url' )
			->andReturn( 'https://example.com/order/42' );

		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create_completed( $cart, 'cart-id-paid', $wc_order );

		$result = $response->to_array();

		$this->assertSame( 'COMPLETED', $result['status'] );
	}

	/**
	 * @scenario Completed checkout includes merchant order number and review URL
	 *
	 * Given a paid WC_Order with ID 42 and a known order received URL
	 * When the response is built via create_completed()
	 * Then to_array()['payment_confirmation'] contains merchant_order_number equal to the order ID
	 * And order_review_page equal to the checkout order received URL
	 */
	public function test_create_completed_includes_payment_confirmation(): void {
		$order_id   = 42;
		$review_url = 'https://example.com/order/42';

		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_id' )->andReturn( $order_id );
		$wc_order->allows( 'get_checkout_order_received_url' )->andReturn( $review_url );

		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create_completed( $cart, 'cart-id-paid', $wc_order );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'payment_confirmation', $result );
		$this->assertSame( $order_id, $result['payment_confirmation']['merchant_order_number'] );
		$this->assertSame( $review_url, $result['payment_confirmation']['order_review_page'] );
	}

	/**
	 * @scenario Completed checkout includes payment_method but without token
	 *
	 * Given a cart that has been fully paid
	 * When the response is built via create_completed()
	 * Then to_array() includes a payment_method key
	 * And that key does not contain a token
	 */
	public function test_create_completed_excludes_token_from_payment_method(): void {
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_id' )->andReturn( 42 );
		$wc_order->allows( 'get_checkout_order_received_url' )
			->andReturn( 'https://example.com/order/42' );

		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create_completed( $cart, 'cart-id-paid', $wc_order );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'payment_method', $result );
		$this->assertArrayNotHasKey( 'token', $result['payment_method'] );
	}

	// -------------------------------------------------------------------------
	// create — status
	// -------------------------------------------------------------------------

	/**
	 * @scenario Base/get/replace flow sets status to INCOMPLETE
	 *
	 * Given a cart being fetched or updated (not yet paid)
	 * When the response is built via create()
	 * Then to_array()['status'] equals 'INCOMPLETE'
	 */
	public function test_create_sets_status_to_incomplete(): void {
		$cart     = PayPalCart::from_array( $this->make_cart_data() );
		$response = CartResponse::create( $cart );

		$result = $response->to_array();

		$this->assertSame( 'INCOMPLETE', $result['status'] );
	}

}
