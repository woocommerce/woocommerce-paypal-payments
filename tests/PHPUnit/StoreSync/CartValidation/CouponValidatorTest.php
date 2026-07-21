<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use Mockery;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponValidator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponContextBuilder;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\DiscountCalculator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponResolutionBuilder;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponValidator
 */
class CouponValidatorTest extends ValidationTest {

	private CouponValidator $validator;
	private $product_manager;
	private $context_builder;
	private $discount_calculator;
	private $resolution_builder;

	public function setUp(): void {
		parent::setUp();
		\Brain\Monkey\Functions\when( 'get_woocommerce_currency' )->justReturn( 'USD' );
		// Note: wc_coupons_enabled() is stubbed per-test as needed
		$this->product_manager     = Mockery::mock( ProductManager::class );
		$this->discount_calculator = new DiscountCalculator( $this->product_manager );
		$store_currency            = Mockery::mock( StoreCurrencyValue::class );
		$store_currency->allows( 'value' )->andReturn( 'USD' );
		$this->context_builder    =
			new CouponContextBuilder( $this->product_manager, $this->discount_calculator, $store_currency );
		$this->resolution_builder = new CouponResolutionBuilder();
		$this->validator          = new CouponValidator(
			$this->context_builder,
			$this->discount_calculator,
			$this->resolution_builder
		);
	}

	public function test_validate_returns_null_for_cart_without_coupons(): void {
		$cart = $this->create_cart_with_coupons( array() );

		$result = $this->validator->validate( $this->wrap_in_store_cart( $cart ) );

		$this->assertNull( $result );
	}

	public function test_validate_skips_remove_action_coupons(): void {
		$cart = $this->create_cart_with_coupons(
			array(
				array( 'code' => 'REMOVE_ME', 'action' => 'REMOVE' ),
			)
		);

		$result = $this->validator->validate( $this->wrap_in_store_cart( $cart ) );

		// REMOVE actions are skipped entirely - no WC_Coupon instantiation needed.
		$this->assertNull( $result );
	}

	public function test_validate_returns_error_when_coupons_disabled(): void {
		/**
		 * GIVEN a cart with one APPLY coupon and the store has coupons disabled
		 * WHEN validate() is called
		 * THEN a single COUPON_NOT_SUPPORTED issue is returned
		 * AND the issue carries the internal message 'Coupons are not enabled'
		 * AND the issue is attributed to the 'coupons' field
		 */
		\Brain\Monkey\Functions\when( 'wc_coupons_enabled' )->justReturn( false );
		\Brain\Monkey\Functions\when( 'wp_validate_redirect' )->returnArg( 1 );

		$cart = $this->create_cart_with_coupons(
			array(
				array( 'code' => 'TESTCODE', 'action' => 'APPLY' ),
			)
		);

		$result = $this->validator->validate( $this->wrap_in_store_cart( $cart ) );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		$data = $result[0]->to_array();
		$this->assertValidationIssue( $data, 'PRICING_ERROR', 'BUSINESS_RULE', 'coupons', 'Coupons are not enabled' );
	}

	public function test_validate_filters_only_apply_actions(): void {
		/**
		 * GIVEN a cart containing one REMOVE coupon followed by one APPLY coupon
		 * WHEN validate() is called with coupons disabled (so we reach the gate without WC_Coupon lookups)
		 * THEN exactly one issue is returned
		 * AND that issue is COUPON_NOT_SUPPORTED — proving that only the APPLY coupon survived
		 *     the filter and drove the result (the REMOVE coupon was silently discarded).
		 *
		 * Note: COUPON_NOT_SUPPORTED issues do not embed the coupon code in to_array() output,
		 * so we cannot assert "which" APPLY coupon was used — but count === 1 already proves
		 * the REMOVE coupon did not produce an issue of its own.
		 */
		\Brain\Monkey\Functions\when( 'wc_coupons_enabled' )->justReturn( false );
		\Brain\Monkey\Functions\when( 'wp_validate_redirect' )->returnArg( 1 );

		$cart = $this->create_cart_with_coupons(
			array(
				array( 'code' => 'REMOVE_ME', 'action' => 'REMOVE' ),
				array( 'code' => 'SAVE10', 'action' => 'APPLY' ),
			)
		);

		$result = $this->validator->validate( $this->wrap_in_store_cart( $cart ) );

		// Exactly one issue: the single APPLY coupon produced it; the REMOVE coupon was filtered out.
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertValidationIssue( $result[0]->to_array(), 'PRICING_ERROR', 'BUSINESS_RULE', 'coupons' );
	}


}
