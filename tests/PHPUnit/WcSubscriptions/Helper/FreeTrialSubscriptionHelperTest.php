<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class FreeTrialSubscriptionHelperTest extends TestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Builds a cart mock holding the given items ('data' => product) and emptiness.
	 *
	 * @param array $items
	 * @param bool  $is_empty
	 * @return \Mockery\MockInterface
	 */
	private function cart_with_items( array $items, bool $is_empty = false ) {
		$cart = Mockery::mock( 'WC_Cart' );
		$cart->shouldReceive( 'is_empty' )->andReturn( $is_empty );

		if ( ! $is_empty ) {
			$cart->shouldReceive( 'get_cart' )->andReturn( $items );
		}

		return $cart;
	}

	/**
	 * Builds a product mock and stubs WC_Subscriptions_Product::is_subscription() for it.
	 *
	 * @param bool      $is_subscription
	 * @param bool|null $has_plan_meta Only relevant when $is_subscription is true.
	 * @return \Mockery\MockInterface
	 */
	private function product( bool $is_subscription, ?bool $has_plan_meta = null ) {
		$product = Mockery::mock();

		Mockery::mock( 'alias:WC_Subscriptions_Product' )
			->shouldReceive( 'is_subscription' )
			->andReturn( $is_subscription );

		if ( $is_subscription ) {
			$product->shouldReceive( 'get_meta' )
				->with( 'ppcp_subscription_plan' )
				->andReturn( $has_plan_meta ? 'PLAN-1' : '' );
		}

		return $product;
	}

	// -------------------------------------------------------------------------
	// cart_requires_vaulting()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN WooCommerce Subscriptions is not active
	 * WHEN cart_requires_vaulting() is called
	 * THEN it returns false without looking at the cart at all
	 */
	public function test_cart_requires_vaulting_false_when_wcs_plugin_not_active(): void {
		$helper = new TestableFreeTrialSubscriptionHelper( false );

		$this->assertFalse( $helper->cart_requires_vaulting() );
	}

	/**
	 * GIVEN WooCommerce Subscriptions is active but there is no cart on WC()
	 * WHEN cart_requires_vaulting() is called
	 * THEN it returns false
	 */
	public function test_cart_requires_vaulting_false_when_no_cart_present(): void {
		when( 'WC' )->justReturn( (object) array( 'cart' => null ) );

		$helper = new TestableFreeTrialSubscriptionHelper( true );

		$this->assertFalse( $helper->cart_requires_vaulting() );
	}

	/**
	 * GIVEN WooCommerce Subscriptions is active and the cart is empty
	 * WHEN cart_requires_vaulting() is called
	 * THEN it returns false
	 */
	public function test_cart_requires_vaulting_false_when_cart_is_empty(): void {
		when( 'WC' )->justReturn( (object) array( 'cart' => $this->cart_with_items( array(), true ) ) );

		$helper = new TestableFreeTrialSubscriptionHelper( true );

		$this->assertFalse( $helper->cart_requires_vaulting() );
	}

	/**
	 * GIVEN a cart holding only a non-subscription product
	 * WHEN cart_requires_vaulting() is called
	 * THEN it returns false, since there is no subscription renewal to vault against
	 */
	public function test_cart_requires_vaulting_false_when_cart_has_no_subscription_item(): void {
		$product = $this->product( false );
		$cart    = $this->cart_with_items( array( array( 'data' => $product ) ) );

		when( 'WC' )->justReturn( (object) array( 'cart' => $cart ) );

		$helper = new TestableFreeTrialSubscriptionHelper( true );

		$this->assertFalse( $helper->cart_requires_vaulting() );
	}

	/**
	 * GIVEN a cart holding a subscription product that carries a ppcp_subscription_plan meta
	 * WHEN cart_requires_vaulting() is called
	 * THEN it returns false, because renewals for that item are billed by PayPal against the
	 *      connected plan, not from a vaulted payment method
	 */
	public function test_cart_requires_vaulting_false_when_subscription_item_has_connected_plan(): void {
		$product = $this->product( true, true );
		$cart    = $this->cart_with_items( array( array( 'data' => $product ) ) );

		when( 'WC' )->justReturn( (object) array( 'cart' => $cart ) );

		$helper = new TestableFreeTrialSubscriptionHelper( true );

		$this->assertFalse( $helper->cart_requires_vaulting() );
	}

	/**
	 * GIVEN a cart holding a subscription product with no ppcp_subscription_plan meta
	 * WHEN cart_requires_vaulting() is called
	 * THEN it returns true, since renewals must be paid from a vaulted payment method
	 */
	public function test_cart_requires_vaulting_true_when_subscription_item_has_no_connected_plan(): void {
		$product = $this->product( true, false );
		$cart    = $this->cart_with_items( array( array( 'data' => $product ) ) );

		when( 'WC' )->justReturn( (object) array( 'cart' => $cart ) );

		$helper = new TestableFreeTrialSubscriptionHelper( true );

		$this->assertTrue( $helper->cart_requires_vaulting() );
	}

	/**
	 * GIVEN a cart that qualifies for vaulting (a subscription item with no connected plan)
	 * WHEN cart_requires_vaulting() is called
	 * THEN it never reads the cart's total to reach its answer - a Mockery mock without a
	 *      get_total() expectation fails the test the moment that method is called
	 * AND this is the property the whole split rests on: the frontend re-answers the free-trial
	 *     question against a live total by combining this cart-shape answer with a total of its
	 *     own, without another request
	 */
	public function test_cart_requires_vaulting_never_reads_the_cart_total(): void {
		$product = $this->product( true, false );
		$cart    = $this->cart_with_items( array( array( 'data' => $product ) ) );

		when( 'WC' )->justReturn( (object) array( 'cart' => $cart ) );

		$helper = new TestableFreeTrialSubscriptionHelper( true );

		$this->assertTrue( $helper->cart_requires_vaulting() );
	}

	// -------------------------------------------------------------------------
	// is_free_trial_cart()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a cart whose shape may or may not require vaulting, at a given total
	 * WHEN is_free_trial_cart() is called
	 * THEN it is true only when the cart requires vaulting AND the total is zero or below
	 * AND a qualifying cart with a positive total is not a free trial
	 * AND a non-qualifying cart is never a free trial, regardless of total
	 *
	 * @dataProvider free_trial_cart_provider
	 */
	public function test_is_free_trial_cart_requires_both_cart_shape_and_non_positive_total(
		bool $cart_requires_vaulting,
		string $total,
		bool $expected
	): void {
		$helper = Mockery::mock( FreeTrialSubscriptionHelper::class )->makePartial();
		$helper->shouldReceive( 'cart_requires_vaulting' )->andReturn( $cart_requires_vaulting );

		$cart = Mockery::mock( 'WC_Cart' );
		$cart->shouldReceive( 'get_total' )->with( 'numeric' )->andReturn( $total );
		when( 'WC' )->justReturn( (object) array( 'cart' => $cart ) );

		$this->assertSame( $expected, $helper->is_free_trial_cart() );
	}

	public function free_trial_cart_provider(): array {
		return array(
			'qualifying cart with a zero total is a free trial'      => array( true, '0.00', true ),
			'qualifying cart with a negative total is a free trial'  => array( true, '-5.00', true ),
			'qualifying cart with a positive total is not free'      => array( true, '19.99', false ),
			'non-qualifying cart with a zero total is not free'      => array( false, '0.00', false ),
			'non-qualifying cart with a positive total is not free'  => array( false, '19.99', false ),
		);
	}

	/**
	 * GIVEN a cart that requires vaulting but WC() reports no cart at all
	 * WHEN is_free_trial_cart() is called
	 * THEN it returns false rather than erroring on a null cart
	 */
	public function test_is_free_trial_cart_false_when_cart_requires_vaulting_but_no_cart_present(): void {
		$helper = Mockery::mock( FreeTrialSubscriptionHelper::class )->makePartial();
		$helper->shouldReceive( 'cart_requires_vaulting' )->andReturn( true );

		when( 'WC' )->justReturn( (object) array( 'cart' => null ) );

		$this->assertFalse( $helper->is_free_trial_cart() );
	}
}

/**
 * Testable subclass overriding the protected WCS-active check so tests can control it
 * directly, without depending on whether the real WC_Subscriptions class is loaded.
 */
class TestableFreeTrialSubscriptionHelper extends FreeTrialSubscriptionHelper {
	/**
	 * @var bool
	 */
	private $wcs_active;

	public function __construct( bool $wcs_active ) {
		$this->wcs_active = $wcs_active;
	}

	protected function is_wcs_plugin_active(): bool {
		return $this->wcs_active;
	}
}
