<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use Mockery;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\ProductManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\CouponInvalid;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator\CouponValidator;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator\CouponContextBuilder;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator\DiscountCalculator;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator\CouponResolutionBuilder;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator\CouponValidator
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
		if (!class_exists('WC_Coupon') || !class_exists('WC_Discounts')) {
			$this->markTestSkipped('WooCommerce classes not available in unit test environment');
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
		$issue = new CouponInvalid(
			'Test message',
			'Test user message',
			'coupons[0]',
			''
		);

		$data = $issue->to_array();

		$this->assertSame('PRICING_ERROR', $data['code']);
		$this->assertSame('BUSINESS_RULE', $data['type']);
	}

	public function test_coupon_invalid_issue_includes_context_and_resolution(): void
	{
		$context = array(
			'specific_issue' => 'COUPON_NOT_EXIST',
			'coupon_code' => 'INVALID',
		);

		$resolution_options = array(
			array(
				'action' => 'RETRY_REQUEST',
				'label' => 'Try again',
				'metadata' => array('priority' => 'high'),
			),
		);

		$issue = new CouponInvalid(
			'Coupon does not exist',
			'The coupon is not valid.',
			'coupons[0]',
			'',
			$context,
			$resolution_options
		);

		$data = $issue->to_array();

		$this->assertArrayHasKey('context', $data);
		$this->assertArrayHasKey('resolution_options', $data);
		$this->assertSame('COUPON_NOT_EXIST', $data['context']['specific_issue']);
		$this->assertSame('RETRY_REQUEST', $data['resolution_options'][0]['action']);
	}

	public function test_coupon_invalid_truncates_long_messages(): void
	{
		$long_message = str_repeat('a', 300);
		$long_user_message = str_repeat('b', 600);

		$issue = new CouponInvalid(
			$long_message,
			$long_user_message,
			'coupons[0]',
			''
		);

		$data = $issue->to_array();

		$this->assertSame(255, strlen($data['message']));
		$this->assertSame(500, strlen($data['user_message']));
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
