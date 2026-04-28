<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\StoreSync\Response;

use Brain\Monkey;
use Mockery;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\TestCase;

class CartResponseTest extends TestCase
{

	public function setUp(): void
	{
		parent::setUp();
		Monkey\Functions\when('get_woocommerce_currency')->justReturn('USD');
	}

	private function create_mock_wc_cart(float $item_total = 100.0, float $discount = 0.0, float $shipping = 0.0, float $tax = 0.0): \WC_Cart
	{
		$wc_cart = Mockery::mock(\WC_Cart::class);
		$wc_cart->allows('get_cart_contents_total')->andReturn((string) $item_total);
		$wc_cart->allows('get_discount_total')->andReturn((string) $discount);
		$wc_cart->allows('get_shipping_total')->andReturn((string) $shipping);
		$wc_cart->allows('get_total_tax')->andReturn((string) $tax);

		// Calculate cart total: subtotal - discount + shipping + tax
		$cart_total = $item_total - $discount + $shipping + $tax;
		$wc_cart->allows('get_total')->andReturn((string) $cart_total);

		return $wc_cart;
	}

	public function test_to_array_includes_applied_coupons_when_provided(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'TEST-001',
					'quantity' => 2,
					'name' => 'Test Product',
					'price' => array('currency_code' => 'USD', 'value' => '50.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		$applied_coupons = array(
			array(
				'code' => 'TESTCOUPON',
				'description' => '10% off',
				'discount_amount' => array('currency_code' => 'USD', 'value' => '10.00'),
			),
		);

		$wc_cart = $this->create_mock_wc_cart(100.0, 10.0);
		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart, $applied_coupons, 'test-cart-id', $wc_cart);

		$result = $response->to_array();

		$this->assertArrayHasKey('applied_coupons', $result);
		$this->assertIsArray($result['applied_coupons']);
		$this->assertCount(1, $result['applied_coupons']);

		$coupon = $result['applied_coupons'][0];
		$this->assertEquals('TESTCOUPON', $coupon['code']);
		$this->assertEquals('10% off', $coupon['description']);
		$this->assertEquals('USD', $coupon['discount_amount']['currency_code']);
		$this->assertEquals('10.00', $coupon['discount_amount']['value']);
	}

	public function test_to_array_excludes_applied_coupons_when_empty(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'TEST-001',
					'quantity' => 2,
					'name' => 'Test Product',
					'price' => array('currency_code' => 'USD', 'value' => '50.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart, array());

		$result = $response->to_array();

		$this->assertArrayNotHasKey('applied_coupons', $result);
	}

	public function test_to_array_excludes_applied_coupons_when_not_provided(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'TEST-001',
					'quantity' => 2,
					'name' => 'Test Product',
					'price' => array('currency_code' => 'USD', 'value' => '50.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart);

		$result = $response->to_array();

		$this->assertArrayNotHasKey('applied_coupons', $result);
	}

	public function test_calculate_totals_includes_discount_field_when_coupons_applied(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'TEST-001',
					'quantity' => 2,
					'name' => 'Test Product',
					'price' => array('currency_code' => 'USD', 'value' => '50.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		$applied_coupons = array(
			array(
				'code' => 'TESTCOUPON',
				'description' => '10% off',
				'discount_amount' => array('currency_code' => 'USD', 'value' => '10.00'),
			),
		);

		$wc_cart = $this->create_mock_wc_cart(100.0, 10.0);
		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart, $applied_coupons, 'test-cart-id', $wc_cart);

		$result = $response->to_array();

		$this->assertArrayHasKey('discount', $result['totals']);
		$this->assertEquals('USD', $result['totals']['discount']['currency_code']);
		$this->assertEquals('10.00', $result['totals']['discount']['value']);
	}

	public function test_calculate_totals_excludes_discount_field_when_no_coupons(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'TEST-001',
					'quantity' => 2,
					'name' => 'Test Product',
					'price' => array('currency_code' => 'USD', 'value' => '50.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		$wc_cart = $this->create_mock_wc_cart(100.0);
		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart, array(), 'test-cart-id', $wc_cart);

		$result = $response->to_array();

		$this->assertArrayNotHasKey('discount', $result['totals']);
	}

	public function test_calculate_totals_subtracts_discount_from_amount(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'TEST-001',
					'quantity' => 2,
					'name' => 'Test Product',
					'price' => array('currency_code' => 'USD', 'value' => '50.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		$applied_coupons = array(
			array(
				'code' => 'TESTCOUPON',
				'description' => '$20 off',
				'discount_amount' => array('currency_code' => 'USD', 'value' => '20.00'),
			),
		);

		$wc_cart = $this->create_mock_wc_cart(100.0, 20.0);
		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart, $applied_coupons, 'test-cart-id', $wc_cart);

		$result = $response->to_array();

		// Item total: 2 * 50.00 = 100.00
		// Discount: 20.00
		// Amount: 100.00 - 20.00 = 80.00
		$this->assertEquals('100.00', $result['totals']['subtotal']['value']);
		$this->assertEquals('20.00', $result['totals']['discount']['value']);
		$this->assertEquals(80.0, $result['totals']['total']['value']);
	}

	public function test_response_structure_matches_paypal_spec(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'TEST-001',
					'quantity' => 1,
					'name' => 'Test Product',
					'price' => array('currency_code' => 'USD', 'value' => '100.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		$wc_cart = $this->create_mock_wc_cart(100.0);
		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart, array(), 'test-cart-id', $wc_cart);

		$result = $response->to_array();

		// Verify required fields exist
		$this->assertArrayHasKey('id', $result);
		$this->assertArrayHasKey('status', $result);
		$this->assertArrayHasKey('validation_status', $result);
		$this->assertArrayHasKey('validation_issues', $result);
		$this->assertArrayHasKey('totals', $result);

		// Verify totals structure
		$this->assertArrayHasKey('subtotal', $result['totals']);
		$this->assertArrayHasKey('shipping', $result['totals']);
		$this->assertArrayHasKey('tax', $result['totals']);
		$this->assertArrayHasKey('total', $result['totals']);

		// Verify each total has currency_code and value
		foreach (array('subtotal', 'shipping', 'tax', 'total') as $field) {
			$this->assertArrayHasKey('currency_code', $result['totals'][$field]);
			$this->assertArrayHasKey('value', $result['totals'][$field]);
		}
	}

	public function test_multiple_coupons_discount_amounts_are_summed(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'TEST-001',
					'quantity' => 1,
					'name' => 'Test Product',
					'price' => array('currency_code' => 'USD', 'value' => '100.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		$applied_coupons = array(
			array(
				'code' => 'COUPON1',
				'description' => '10% off',
				'discount_amount' => array('currency_code' => 'USD', 'value' => '10.00'),
			),
			array(
				'code' => 'COUPON2',
				'description' => '$5 off',
				'discount_amount' => array('currency_code' => 'USD', 'value' => '5.00'),
			),
		);

		$wc_cart = $this->create_mock_wc_cart(100.0, 15.0);
		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart, $applied_coupons, 'test-cart-id', $wc_cart);

		$result = $response->to_array();

		// Total discount should be 10.00 + 5.00 = 15.00
		$this->assertEquals('15.00', $result['totals']['discount']['value']);
		// Amount should be 100.00 - 15.00 = 85.00
		$this->assertEquals(85.0, $result['totals']['total']['value']);
	}

	public function test_discount_is_capped_when_exceeds_item_total(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'SHIRT-001',
					'quantity' => 1,
					'name' => 'T-Shirt',
					'price' => array('currency_code' => 'USD', 'value' => '25.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		// Coupons total to $30 on a $25 cart
		$applied_coupons = array(
			array(
				'code' => 'SOLO20',
				'description' => 'fixed_cart discount',
				'discount_amount' => array('currency_code' => 'USD', 'value' => '20.00'),
			),
			array(
				'code' => 'GAMING20',
				'description' => 'fixed_cart discount',
				'discount_amount' => array('currency_code' => 'USD', 'value' => '10.00'),
			),
		);

		$wc_cart = $this->create_mock_wc_cart(25.0, 24.99);
		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart, $applied_coupons, 'test-cart-id', $wc_cart);

		$result = $response->to_array();

		// Discount should be capped at subtotal - 0.01 = 24.99
		$this->assertEquals('24.99', $result['totals']['discount']['value']);
		// Amount should be minimum 0.01 to satisfy PayPal
		$this->assertEquals('0.01', $result['totals']['total']['value']);
		// Item total should remain unchanged
		$this->assertEquals('25.00', $result['totals']['subtotal']['value']);
	}

	public function test_discount_equal_to_item_total_is_capped(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'TEST-001',
					'quantity' => 1,
					'name' => 'Test Product',
					'price' => array('currency_code' => 'USD', 'value' => '50.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		// Discount exactly equals cart total
		$applied_coupons = array(
			array(
				'code' => 'FREE100',
				'description' => '100% off',
				'discount_amount' => array('currency_code' => 'USD', 'value' => '50.00'),
			),
		);

		$wc_cart = $this->create_mock_wc_cart(50.0, 49.99);
		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart, $applied_coupons, 'test-cart-id', $wc_cart);

		$result = $response->to_array();

		// Discount should be capped at subtotal - 0.01 = 49.99
		$this->assertEquals('49.99', $result['totals']['discount']['value']);
		// Amount should be minimum 0.01 to satisfy PayPal
		$this->assertEquals('0.01', $result['totals']['total']['value']);
	}

	public function test_amount_formatting_avoids_floating_point_precision_issues(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'SHIRT-001',
					'quantity' => 1,
					'name' => 'T-Shirt',
					'price' => array('currency_code' => 'USD', 'value' => '25.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		$applied_coupons = array(
			array(
				'code' => 'SOLO20',
				'description' => 'fixed_cart discount',
				'discount_amount' => array('currency_code' => 'USD', 'value' => '20.00'),
			),
			array(
				'code' => 'GAMING20',
				'description' => 'fixed_cart discount',
				'discount_amount' => array('currency_code' => 'USD', 'value' => '10.00'),
			),
		);

		$wc_cart = $this->create_mock_wc_cart(25.0, 24.99);
		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart, $applied_coupons, 'test-cart-id', $wc_cart);

		$result = $response->to_array();

		// Verify amount is properly formatted as string with 2 decimals
		// Should be "0.01" not 0.010000000000001563
		$this->assertIsString($result['totals']['total']['value']);
		$this->assertEquals('0.01', $result['totals']['total']['value']);
		$this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $result['totals']['total']['value']);
	}

	public function test_all_money_values_are_formatted_consistently(): void
	{
		$cart_data = array(
			'items' => array(
				array(
					'variant_id' => 'TEST-001',
					'quantity' => 1,
					'name' => 'Test Product',
					'price' => array('currency_code' => 'USD', 'value' => '100.00'),
				),
			),
			'payment_method' => array('type' => 'paypal'),
		);

		$applied_coupons = array(
			array(
				'code' => 'SAVE10',
				'description' => '$10 off',
				'discount_amount' => array('currency_code' => 'USD', 'value' => '10.00'),
			),
		);

		$wc_cart = $this->create_mock_wc_cart(100.0, 10.0, 5.0, 8.5);
		$cart = PayPalCart::from_array($cart_data);
		$response = new CartResponse($cart, $applied_coupons, 'test-cart-id', $wc_cart);

		$result = $response->to_array();

		// All money values should be strings with 2 decimal places
		$money_fields = array('subtotal', 'discount', 'shipping', 'tax', 'total');
		foreach ($money_fields as $field) {
			if (isset($result['totals'][$field])) {
				$this->assertIsString(
					$result['totals'][$field]['value'],
					"Field {$field} value should be a string"
				);
				$this->assertMatchesRegularExpression(
					'/^\d+\.\d{2}$/',
					$result['totals'][$field]['value'],
					"Field {$field} should match format 'XX.XX'"
				);
			}
		}
	}
}
