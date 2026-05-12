<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Response;

use Mockery;
use WC_Order;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\StoreSyncTestCase;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;

use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse
 */
class CartResponseTest extends StoreSyncTestCase {

	public function setUp(): void {
		parent::setUp();
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Creates a StorePayPalCart stub whose to_array() returns the given data and
	 * whose validation() returns a fresh (empty) StoreValidation.
	 *
	 * @param array $cart_data Array returned by to_array().
	 * @return StorePayPalCart
	 */
	private function make_store_cart( array $cart_data = array(), string $paypal_order = '' ): StorePayPalCart {
		$validation = new StoreValidation();
		$store_cart = Mockery::mock( StorePayPalCart::class );
		$store_cart->allows( 'validation' )->andReturn( $validation );
		$store_cart->allows( 'paypal_order' )->andReturn( $paypal_order );
		$store_cart->allows( 'get_validation_issues' )->andReturn( array() );
		$store_cart->allows( 'get_items' )->andReturn( $cart_data['items'] ?? array() );
		$store_cart->allows( 'get_customer' )->andReturn( $cart_data['customer'] ?? array() );
		$store_cart->allows( 'get_shipping_address' )->andReturn( $cart_data['shipping_address'] ?? array() );
		$store_cart->allows( 'get_billing_address' )->andReturn( $cart_data['billing_address'] ?? null );
		$store_cart->allows( 'get_totals' )->andReturn( $cart_data['totals'] ?? null );

		$payment_method_data = $cart_data['payment_method'] ?? array( 'type' => 'paypal' );
		if ( $paypal_order ) {
			$payment_method_data['token'] = $paypal_order;
		}
		$store_cart->allows( 'get_payment_method' )->andReturn( $payment_method_data );

		return $store_cart;
	}

	/**
	 * Returns a totals array that mirrors what StorePayPalCart::to_array() would
	 * produce for a cart with the given money values.
	 *
	 * @param float  $subtotal Item total (before discount).
	 * @param float  $discount Coupon discount, 0 to omit the key.
	 * @param float  $shipping Shipping amount.
	 * @param float  $tax      Tax amount.
	 * @param string $currency Currency code.
	 * @return array
	 */
	private function make_totals(
		float $subtotal,
		float $discount = 0.0,
		float $shipping = 0.0,
		float $tax = 0.0,
		string $currency = 'USD'
	): array {
		$total = $subtotal - $discount + $shipping + $tax;
		$money = static function ( float $v ) use ( $currency ): array {
			return array(
				'currency_code' => $currency,
				'value'         => number_format( $v, 2, '.', '' ),
			);
		};

		$totals = array(
			'subtotal' => $money( $subtotal ),
			'shipping' => $money( $shipping ),
			'tax'      => $money( $tax ),
			'total'    => $money( $total ),
		);

		if ( $discount > 0.0 ) {
			$totals['discount'] = $money( $discount );
		}

		return $totals;
	}

	// -------------------------------------------------------------------------
	// applied_coupons — presence / absence
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a cart with a 10 % coupon applied
	 * WHEN the response is built via create()->applied_coupons()
	 * THEN to_array() includes an applied_coupons key
	 * AND that key contains exactly one coupon with the correct code, description, and discount
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

		$store_cart = $this->make_store_cart(
			array( 'totals' => $this->make_totals( 100.0, 10.0 ) )
		);
		$response   = CartResponse::create( $store_cart, 'test-cart-id' )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'applied_coupons', $result );
		$this->assertIsArray( $result['applied_coupons'] );
		$this->assertCount( 1, $result['applied_coupons'] );

		$coupon = $result['applied_coupons'][0];
		$this->assertEquals( 'TESTCOUPON', $coupon['code'] );
		$this->assertEquals( '10% off', $coupon['description'] );
		$this->assertMoneyValue( $coupon['discount_amount'], 10.0, 'USD' );
	}

	/**
	 * GIVEN a cart with no coupons applied
	 * WHEN the response is built via create()->applied_coupons([])
	 * THEN to_array() does not include an applied_coupons key
	 */
	public function test_to_array_excludes_applied_coupons_when_empty(): void {
		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create( $store_cart, '' )
			->applied_coupons( array() );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'applied_coupons', $result );
	}

	/**
	 * GIVEN a cart with no coupon information provided at all
	 * WHEN the response is built via create() without chaining applied_coupons()
	 * THEN to_array() does not include an applied_coupons key
	 */
	public function test_to_array_excludes_applied_coupons_when_not_provided(): void {
		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create( $store_cart, '' );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'applied_coupons', $result );
	}

	// -------------------------------------------------------------------------
	// totals — discount field
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a cart with a 10 % coupon
	 * WHEN the response is built via create()->applied_coupons()
	 * THEN to_array()['totals'] includes a discount key with the correct currency and value
	 */
	public function test_calculate_totals_includes_discount_field_when_coupons_applied(): void {
		$applied_coupons = array(
			array(
				'code'            => 'TESTCOUPON',
				'description'     => '10% off',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '10.00' ),
			),
		);

		$store_cart = $this->make_store_cart(
			array( 'totals' => $this->make_totals( 100.0, 10.0 ) )
		);
		$response   = CartResponse::create( $store_cart, 'test-cart-id' )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'discount', $result['totals'] );
		$this->assertMoneyValue( $result['totals']['discount'], 10.0, 'USD' );
	}

	/**
	 * GIVEN a cart with no coupons
	 * WHEN the response is built via create() without chaining applied_coupons()
	 * THEN to_array()['totals'] does not include a discount key
	 */
	public function test_calculate_totals_excludes_discount_field_when_no_coupons(): void {
		$store_cart = $this->make_store_cart( array( 'totals' => $this->make_totals( 100.0 ) ) );
		$response   = CartResponse::create( $store_cart, 'test-cart-id' );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'discount', $result['totals'] );
	}

	/**
	 * GIVEN a cart with items totalling $100 and a $20 coupon discount
	 * WHEN the response is built via create()->applied_coupons()
	 * THEN item_total is $100.00, discount is $20.00, and amount is $80.00
	 */
	public function test_calculate_totals_subtracts_discount_from_amount(): void {
		$applied_coupons = array(
			array(
				'code'            => 'TESTCOUPON',
				'description'     => '$20 off',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '20.00' ),
			),
		);

		$store_cart = $this->make_store_cart(
			array( 'totals' => $this->make_totals( 100.0, 20.0 ) )
		);
		$response   = CartResponse::create( $store_cart, 'test-cart-id' )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		// Item total: 2 * 50.00 = 100.00
		// Discount: 20.00
		// Amount: 100.00 - 20.00 = 80.00
		$this->assertMoneyValue( $result['totals']['subtotal'], 100.0, 'USD' );
		$this->assertMoneyValue( $result['totals']['discount'], 20.0, 'USD' );
		$this->assertMoneyValue( $result['totals']['total'], 80.0, 'USD' );
	}

	// -------------------------------------------------------------------------
	// Response structure
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a cart with one item and no coupons
	 * WHEN the response is built via create()
	 * THEN to_array() contains id, status, validation_status, validation_issues, and totals
	 * AND totals contains subtotal, shipping, tax, and total — each with currency_code and value
	 */
	public function test_response_structure_matches_paypal_spec(): void {
		$store_cart = $this->make_store_cart( array( 'totals' => $this->make_totals( 100.0 ) ) );
		$response   = CartResponse::create( $store_cart, 'test-cart-id' );

		$result = $response->to_array();

		// Verify required fields exist
		$this->assertArrayHasKey( 'id', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'validation_status', $result );
		$this->assertArrayHasKey( 'validation_issues', $result );
		$this->assertArrayHasKey( 'totals', $result );

		// Verify totals structure
		$this->assertArrayHasKey( 'subtotal', $result['totals'] );
		$this->assertArrayHasKey( 'shipping', $result['totals'] );
		$this->assertArrayHasKey( 'tax', $result['totals'] );
		$this->assertArrayHasKey( 'total', $result['totals'] );

		// Verify each total has currency_code and value
		foreach ( array( 'subtotal', 'shipping', 'tax', 'total' ) as $field ) {
			$this->assertMoneyValue( $result['totals'][ $field ] );
		}
	}

	// -------------------------------------------------------------------------
	// Multiple coupons / discount capping
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a cart with two coupons worth $10 and $5 respectively
	 * WHEN the response is built via create()->applied_coupons()
	 * THEN the discount total is $15.00 and the cart amount is $85.00
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

		$store_cart = $this->make_store_cart(
			array( 'totals' => $this->make_totals( 100.0, 15.0 ) )
		);
		$response   = CartResponse::create( $store_cart, 'test-cart-id' )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		// Total discount should be 10.00 + 5.00 = 15.00
		$this->assertMoneyValue( $result['totals']['discount'], 15.0, 'USD' );

		// Amount should be 100.00 - 15.00 = 85.00
		$this->assertMoneyValue( $result['totals']['total'], 85.0, 'USD' );
	}

	/**
	 * GIVEN a cart with a $25 T-shirt and two coupons totalling $30
	 * WHEN the response is built via create()->applied_coupons()
	 * THEN the discount is capped at $24.99 so that the PayPal minimum amount of $0.01 is preserved
	 * AND the item total remains at $25.00
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

		$store_cart = $this->make_store_cart(
			array( 'totals' => $this->make_totals( 25.0, 24.99 ) )
		);
		$response   = CartResponse::create( $store_cart, 'test-cart-id' )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		// Discount should be capped at subtotal - 0.01 = 24.99
		$this->assertMoneyValue( $result['totals']['discount'], 24.99, 'USD' );

		// Amount should be minimum 0.01 to satisfy PayPal
		$this->assertMoneyValue( $result['totals']['total'], 0.01, 'USD' );

		// Item total should remain unchanged
		$this->assertMoneyValue( $result['totals']['subtotal'], 25.0, 'USD' );
	}

	/**
	 * GIVEN a cart with a $50 product and a 100 % off coupon for $50
	 * WHEN the response is built via create()->applied_coupons()
	 * THEN the discount is capped at $49.99 to maintain a minimum PayPal order amount of $0.01
	 */
	public function test_discount_equal_to_item_total_is_capped(): void {
		$applied_coupons = array(
			array(
				'code'            => 'FREE100',
				'description'     => '100% off',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '50.00' ),
			),
		);

		$store_cart = $this->make_store_cart(
			array( 'totals' => $this->make_totals( 50.0, 49.99 ) )
		);
		$response   = CartResponse::create( $store_cart, 'test-cart-id' )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		// Discount should be capped at subtotal - 0.01 = 49.99
		$this->assertMoneyValue( $result['totals']['discount'], 49.99, 'USD' );

		// Amount should be minimum 0.01 to satisfy PayPal
		$this->assertMoneyValue( $result['totals']['total'], 0.01, 'USD' );
	}

	// -------------------------------------------------------------------------
	// Money formatting
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a cart where capped discounts could cause floating-point rounding errors
	 * WHEN the response is built via create()->applied_coupons()
	 * THEN the amount value is the string "0.01" — not a float like 0.010000000000001
	 * AND it matches the two-decimal-place format pattern
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

		$store_cart = $this->make_store_cart(
			array( 'totals' => $this->make_totals( 25.0, 24.99 ) )
		);
		$response   = CartResponse::create( $store_cart, 'test-cart-id' )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		$this->assertMoneyValue( $result['totals']['total'], 0.01, 'USD' );
	}

	/**
	 * GIVEN a cart with items, a coupon, shipping, and tax
	 * WHEN the response is built via create()->applied_coupons()
	 * THEN every money field in totals is a string matching the pattern ^\d+\.\d{2}$
	 */
	public function test_all_money_values_are_formatted_consistently(): void {
		$applied_coupons = array(
			array(
				'code'            => 'SAVE10',
				'description'     => '$10 off',
				'discount_amount' => array( 'currency_code' => 'USD', 'value' => '10.00' ),
			),
		);

		$store_cart = $this->make_store_cart(
			array( 'totals' => $this->make_totals( 100.0, 10.0, 5.0, 8.5 ) )
		);
		$response   = CartResponse::create( $store_cart, 'test-cart-id' )
			->applied_coupons( $applied_coupons );

		$result = $response->to_array();

		// All money values should be strings with 2 decimal places
		$money_fields = array( 'subtotal', 'discount', 'shipping', 'tax', 'total' );
		foreach ( $money_fields as $field ) {
			if ( ! isset( $result['totals'][ $field ] ) ) {
				continue;
			}

			$this->assertMoneyValue( $result['totals'][ $field ] );
		}
	}

	// -------------------------------------------------------------------------
	// shipping_options — presence / absence
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a cart with no shipping options provided
	 * WHEN the response is built via create() without chaining shipping_options()
	 * THEN to_array() does not include an available_shipping_options key
	 */
	public function test_to_array_excludes_available_shipping_options_when_setter_not_called(): void {
		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create( $store_cart, '' );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'available_shipping_options', $result );
	}

	/**
	 * GIVEN no shipping methods are available for the cart
	 * WHEN the response is built via create()->shipping_options([])
	 * THEN to_array() does not include an available_shipping_options key
	 */
	public function test_to_array_excludes_available_shipping_options_when_empty_array(): void {
		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create( $store_cart, '' )
			->shipping_options( array() );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'available_shipping_options', $result );
	}

	/**
	 * GIVEN one shipping method is available for the cart
	 * WHEN the response is built via create()->shipping_options() with one option
	 * THEN to_array() includes an available_shipping_options key
	 * AND that key contains exactly one entry
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

		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create( $store_cart, '' )
			->shipping_options( $shipping_options );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'available_shipping_options', $result );
		$this->assertCount( 1, $result['available_shipping_options'] );
	}

	// -------------------------------------------------------------------------
	// shipping_options — option shape
	// -------------------------------------------------------------------------

	/**
	 * GIVEN two shipping options are available
	 * WHEN the response is built via create()->shipping_options()
	 * THEN each option in available_shipping_options contains id, label, amount, currency, and
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

		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create( $store_cart, '' )
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
	 * GIVEN three available shipping options where the second is chosen
	 * WHEN the response is built via create()->shipping_options()
	 * THEN exactly one entry in available_shipping_options has is_selected=true
	 * AND that entry corresponds to the chosen shipping method
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

		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create( $store_cart, '' )
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
	 * GIVEN a PayPal cart and a freshly issued cart ID and token
	 * WHEN the response is built via create_new()
	 * THEN to_array()['status'] equals 'CREATED'
	 */
	public function test_create_new_sets_status_to_created(): void {
		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create_new( $store_cart, 'new-cart-id' );

		$result = $response->to_array();

		$this->assertSame( 'CREATED', $result['status'] );
	}

	/**
	 * GIVEN a PayPal cart and a token issued during cart creation
	 * WHEN the response is built via create_new() with that token
	 * THEN to_array()['payment_method'] has type='paypal' and token equal to the passed value
	 */
	public function test_create_new_includes_payment_method_with_token(): void {
		$token      = 'tok_xyz789';
		$store_cart = $this->make_store_cart( array(), $token );
		$response   = CartResponse::create_new( $store_cart, 'new-cart-id' );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'payment_method', $result );
		$this->assertSame( 'paypal', $result['payment_method']['type'] );
		$this->assertSame( $token, $result['payment_method']['token'] );
	}

	/**
	 * GIVEN a cart that has just been created via POST
	 * WHEN the response is built via create_new()
	 * THEN to_array() does not include a payment_confirmation key
	 */
	public function test_create_new_excludes_payment_confirmation(): void {
		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create_new( $store_cart, 'new-cart-id' );

		$result = $response->to_array();

		$this->assertArrayNotHasKey( 'payment_confirmation', $result );
	}

	// -------------------------------------------------------------------------
	// create_completed — status and payment_confirmation
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a PayPal cart built from request body data that already contains an "id" key
	 * WHEN the response is built via create_new() with a distinct server-generated cart ID
	 * THEN to_array()['id'] equals the server-generated ID
	 * AND the caller-supplied ID from the request body is ignored
	 */
	public function test_create_new_id_is_not_overwritten_by_caller_supplied_id(): void {
		// The store_cart to_array() may return an 'id' key from incoming request data.
		$store_cart = $this->make_store_cart( array( 'id' => 'client-supplied-id' ) );
		$response   = CartResponse::create_new( $store_cart, 'server-generated-id' );

		$result = $response->to_array();

		$this->assertSame( 'server-generated-id', $result['id'] );
	}

	/**
	 * GIVEN a cart that has been paid and a corresponding WC_Order
	 * WHEN the response is built via create_completed()
	 * THEN to_array()['status'] equals 'COMPLETED'
	 */
	public function test_create_completed_sets_status_to_completed(): void {
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_id' )->andReturn( 42 );
		$wc_order->allows( 'get_checkout_order_received_url' )
			->andReturn( 'https://example.com/order/42' );

		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create_completed( $store_cart, 'cart-id-paid', $wc_order );

		$result = $response->to_array();

		$this->assertSame( 'COMPLETED', $result['status'] );
	}

	/**
	 * GIVEN a paid WC_Order with ID 42 and a known order received URL
	 * WHEN the response is built via create_completed()
	 * THEN to_array()['payment_confirmation'] contains merchant_order_number equal to the order ID
	 * AND order_review_page equal to the checkout order received URL
	 */
	public function test_create_completed_includes_payment_confirmation(): void {
		$order_id   = 42;
		$review_url = 'https://example.com/order/42';

		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_id' )->andReturn( $order_id );
		$wc_order->allows( 'get_checkout_order_received_url' )->andReturn( $review_url );

		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create_completed( $store_cart, 'cart-id-paid', $wc_order );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'payment_confirmation', $result );
		$this->assertSame( $order_id, $result['payment_confirmation']['merchant_order_number'] );
		$this->assertSame( $review_url, $result['payment_confirmation']['order_review_page'] );
	}

	/**
	 * GIVEN a cart that has been fully paid
	 * WHEN the response is built via create_completed()
	 * THEN to_array() includes a payment_method key
	 * AND that key does not contain a token
	 */
	public function test_create_completed_excludes_token_from_payment_method(): void {
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_id' )->andReturn( 42 );
		$wc_order->allows( 'get_checkout_order_received_url' )
			->andReturn( 'https://example.com/order/42' );

		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create_completed( $store_cart, 'cart-id-paid', $wc_order );

		$result = $response->to_array();

		$this->assertArrayHasKey( 'payment_method', $result );
		$this->assertArrayNotHasKey( 'token', $result['payment_method'] );
	}

	// -------------------------------------------------------------------------
	// create — status
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a cart being fetched or updated (not yet paid)
	 * WHEN the response is built via create()
	 * THEN to_array()['status'] equals 'INCOMPLETE'
	 */
	public function test_create_sets_status_to_incomplete(): void {
		$store_cart = $this->make_store_cart();
		$response   = CartResponse::create( $store_cart, '' );

		$result = $response->to_array();

		$this->assertSame( 'INCOMPLETE', $result['status'] );
	}

}
