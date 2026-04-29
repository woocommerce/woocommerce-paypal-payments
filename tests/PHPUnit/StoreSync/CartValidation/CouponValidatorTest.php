<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use Mockery;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponValidator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponContextBuilder;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\DiscountCalculator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponResolutionBuilder;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponValidator
 */
class CouponValidatorTest extends TestCase
{

	private CouponValidator $validator;
	private $product_manager;
	private $context_builder;
	private $discount_calculator;
	private $resolution_builder;

	public function setUp(): void
	{
		parent::setUp();
		\Brain\Monkey\Functions\when('get_woocommerce_currency')->justReturn('USD');
		// Note: wc_coupons_enabled() is stubbed per-test as needed
		$this->product_manager = Mockery::mock(ProductManager::class);
		$this->discount_calculator = new DiscountCalculator($this->product_manager);
		$this->context_builder = new CouponContextBuilder($this->product_manager, $this->discount_calculator);
		$this->resolution_builder = new CouponResolutionBuilder();
		$this->validator = new CouponValidator(
			$this->context_builder,
			$this->discount_calculator,
			$this->resolution_builder
		);
	}

	public function test_validate_returns_null_for_cart_without_coupons(): void
	{
		$cart = $this->create_cart_with_coupons(array());

		$result = $this->validator->validate($cart);

		$this->assertNull($result);
	}

	public function test_validate_skips_remove_action_coupons(): void
	{
		$cart = $this->create_cart_with_coupons(
			array(
				array('code' => 'REMOVE_ME', 'action' => 'REMOVE'),
			)
		);

		$result = $this->validator->validate($cart);

		// REMOVE actions are skipped entirely - no WC_Coupon instantiation needed.
		$this->assertNull($result);
	}

	public function test_validate_returns_error_when_coupons_disabled(): void
	{
		// This test requires actual WooCommerce classes, so skip in unit test environment.
		// TODO: This test does not run in a unit-test environment. How is it executed?
		if ( ! class_exists( 'WC_Coupon' ) || ! class_exists( 'WC_Discounts' ) ) {
			$this->markTestSkipped( 'WooCommerce classes not available in unit test environment' );
		}

		// Stub wc_coupons_enabled to return false for this test.
		\Brain\Monkey\Functions\when('wc_coupons_enabled')->justReturn(false);

		$cart = $this->create_cart_with_coupons(
			array(
				array('code' => 'TESTCODE', 'action' => 'APPLY'),
			)
		);

		$result = $this->validator->validate($cart);

		$this->assertIsArray($result);
		$this->assertCount(1, $result);

		$issue = $result[0];
		$data = $issue->to_array();

		$this->assertSame('PRICING_ERROR', $data['code']);
		$this->assertSame('BUSINESS_RULE', $data['type']);
		$this->assertSame('Coupons are not enabled', $data['message']);
		$this->assertStringContainsString('does not accept coupon codes', $data['user_message']);
		$this->assertSame('coupons', $data['field']);
	}

	public function test_validate_filters_only_apply_actions(): void
	{
		$cart = $this->create_cart_with_coupons(
			array(
				array('code' => 'KEEP_THIS', 'action' => 'REMOVE'),
				array('code' => 'ALSO_REMOVE', 'action' => 'REMOVE'),
			)
		);

		$result = $this->validator->validate($cart);

		// All coupons are REMOVE actions, so nothing to validate.
		$this->assertNull($result);
	}

	public function test_coupon_invalid_issue_has_correct_error_code(): void
	{
		$issue = ValidationIssue::create_coupon_invalid( 'Test message' )
			->user_message( 'Test user message' )
			->for_field( 'coupons[0]' );

		$data = $issue->to_array();

		$this->assertSame( 'PRICING_ERROR', $data['code'] );
		$this->assertSame( 'BUSINESS_RULE', $data['type'] );
	}

	public function test_coupon_invalid_truncates_long_messages(): void
	{
		$long_message      = str_repeat( 'a', 300 );
		$long_user_message = str_repeat( 'b', 600 );

		$issue = ValidationIssue::create_coupon_invalid( $long_message )
			->user_message( $long_user_message );

		$data = $issue->to_array();

		$this->assertSame( 255, strlen( $data['message'] ) );
		$this->assertSame( 500, strlen( $data['user_message'] ) );
	}

	/**
	 * Helper to create a cart with coupons.
	 */
	private function create_cart_with_coupons(array $coupons, float $subtotal = 50.00, string $customer_email = ''): PayPalCart
	{
		$cart_data = array(
			'items' => array(
				array(
					'item_id' => '1',
					'quantity' => 1,
					'name' => 'Test Product',
					'price' => array(
						'currency_code' => 'USD',
						'value' => $subtotal,
					),
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		if (!empty($coupons)) {
			$cart_data['coupons'] = $coupons;
		}

		if ($customer_email) {
			$cart_data['customer'] = array(
				'email_address' => $customer_email,
			);
		}

		return PayPalCart::from_array($cart_data);
	}
}
