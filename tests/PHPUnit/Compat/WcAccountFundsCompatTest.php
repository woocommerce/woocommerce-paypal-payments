<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

use Kestrel\Account_Funds\Cart as KestrelAccountFundsCart;
use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Filters\expectAdded;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Compat\WcAccountFundsCompat
 */
class WcAccountFundsCompatTest extends TestCase {

	private $testee;

	public function setUp(): void {
		parent::setUp();

		$this->testee = new WcAccountFundsCompat();
	}

	/**
	 * Loads the Kestrel\Account_Funds\Cart stub on demand and resets its static state,
	 * so tests exercising the "plugin present" path stay isolated from one another.
	 */
	private function stub_kestrel_cart( bool $is_using_partially, float $applied_amount ): void {
		require_once __DIR__ . '/Fixtures/KestrelAccountFundsCartStub.php';

		KestrelAccountFundsCart::reset();
		KestrelAccountFundsCart::$is_using_partially = $is_using_partially;
		KestrelAccountFundsCart::$applied_amount     = $applied_amount;
	}

	/**
	 * GIVEN an order that Account Funds partially paid via a direct set_total() call,
	 *   recorded as order meta
	 * WHEN order_extra_discount() runs
	 * THEN the credit is added to the extra discount so the breakdown accounts for it
	 */
	public function test_order_extra_discount_reports_credit_used_on_order(): void {
		$order = Mockery::mock( \WC_Order::class );
		$order->shouldReceive( 'get_meta' )->with( '_funds_used' )->andReturn( '12.50' );

		$result = $this->testee->order_extra_discount( 10.0, $order );

		$this->assertSame( 22.5, $result );
	}

	/**
	 * GIVEN an order without a usable store-credit amount recorded against it
	 * WHEN order_extra_discount() runs
	 * THEN the extra discount accumulated by other hooks is returned unchanged
	 *
	 * @dataProvider data_no_reportable_order_credit
	 */
	public function test_order_extra_discount_unchanged_when_no_reportable_credit( $meta_value ): void {
		$order = Mockery::mock( \WC_Order::class );
		$order->shouldReceive( 'get_meta' )->with( '_funds_used' )->andReturn( $meta_value );

		$result = $this->testee->order_extra_discount( 10.0, $order );

		$this->assertSame( 10.0, $result );
	}

	public function data_no_reportable_order_credit(): array {
		return array(
			'meta absent, WordPress returns empty string' => array( '' ),
			'negative credit is clamped to zero'           => array( '-5.00' ),
		);
	}

	/**
	 * GIVEN a cart partially paid with store credit
	 * WHEN store_api_cart_extra_discount() runs
	 * THEN the credit is converted from a float to minor units and added to the extra
	 *   discount reported to the Store API
	 */
	public function test_store_api_cart_extra_discount_converts_credit_to_minor_units(): void {
		$this->stub_kestrel_cart( true, 12.50 );
		when( 'wc_get_price_decimals' )->justReturn( 2 );

		$result = $this->testee->store_api_cart_extra_discount( 100 );

		$this->assertSame( 1350, $result );
	}

	/**
	 * GIVEN the plugin reports a balance while not partially covering the cart, so the
	 *   cart total was never reduced
	 * WHEN store_api_cart_extra_discount() runs
	 * THEN the extra discount in minor units is returned untouched, the block checkout
	 *   counterpart of the classic guard below
	 */
	public function test_store_api_cart_extra_discount_unchanged_when_cart_total_was_not_reduced(): void {
		$this->stub_kestrel_cart( false, 25.0 );
		when( 'wc_get_price_decimals' )->justReturn( 2 );

		$result = $this->testee->store_api_cart_extra_discount( 100 );

		$this->assertSame( 100, $result );
	}

	/**
	 * GIVEN the plugin reports an applied balance while not partially covering the cart,
	 *   which is what its getter returns once its own gateway is selected and the cart
	 *   total was therefore never reduced
	 * WHEN cart_extra_discount() runs
	 * THEN nothing is reported, since a discount the cart total does not carry would
	 *   undercharge the buyer
	 */
	public function test_cart_extra_discount_ignores_credit_when_cart_total_was_not_reduced(): void {
		$this->stub_kestrel_cart( false, 25.0 );

		$cart = Mockery::mock( \WC_Cart::class );

		$result = $this->testee->cart_extra_discount( 2.0, $cart );

		$this->assertSame( 2.0, $result );
	}

	/**
	 * GIVEN the plugin reports a negative applied amount
	 * WHEN cart_extra_discount() runs
	 * THEN it is clamped to zero rather than inflating what other hooks reported, which
	 *   the factory's own clamp on the summed value would not catch
	 */
	public function test_cart_extra_discount_clamps_a_negative_applied_amount(): void {
		$this->stub_kestrel_cart( true, -5.0 );

		$cart = Mockery::mock( \WC_Cart::class );

		$result = $this->testee->cart_extra_discount( 2.0, $cart );

		$this->assertSame( 2.0, $result );
	}

	/**
	 * GIVEN the Account Funds plugin is not installed, so its cart helper class
	 *   never exists
	 * WHEN cart_extra_discount() runs
	 * THEN no credit is reported and the extra discount is returned unchanged
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_cart_extra_discount_reports_no_credit_when_kestrel_class_absent(): void {
		$this->assertFalse(
			class_exists( '\Kestrel\Account_Funds\Cart', false ),
			'Precondition: the Kestrel stub must not have been loaded in this process.'
		);

		$cart = Mockery::mock( \WC_Cart::class );

		$result = $this->testee->cart_extra_discount( 5.0, $cart );

		$this->assertSame( 5.0, $result );
	}

	/**
	 * GIVEN a cart partially paid with store credit
	 * WHEN cart_extra_discount() runs
	 * THEN the applied credit is added to the extra discount accumulated by other hooks
	 */
	public function test_cart_extra_discount_adds_applied_credit_when_plugin_present(): void {
		$this->stub_kestrel_cart( true, 7.5 );

		$cart = Mockery::mock( \WC_Cart::class );

		$result = $this->testee->cart_extra_discount( 2.0, $cart );

		$this->assertSame( 9.5, $result );
	}

	/**
	 * GIVEN the compat class is being wired up
	 * WHEN register() runs
	 * THEN all three extra-discount filters used to balance the breakdown are attached
	 */
	public function test_register_attaches_all_three_extra_discount_filters(): void {
		expectAdded( 'woocommerce_paypal_payments_cart_extra_discount' )->once();
		expectAdded( 'woocommerce_paypal_payments_order_extra_discount' )->once();
		expectAdded( 'woocommerce_paypal_payments_store_api_cart_extra_discount' )->once();

		$this->testee->register();

		$this->addToAssertionCount( 1 );
	}
}
