<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions\Helper;

use Mockery;
use WC_Order;
use WC_Subscription;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use function Brain\Monkey\Functions\when;

class SubscriptionHelperTest extends TestCase
{
	public function testPreviousTransaction()
	{
		$subscription = Mockery::mock(WC_Subscription::class);
		$subscription->shouldReceive('get_related_orders')
			->andReturn(
				[
					1 => 1,
					3 => 3,
					2 => 2,
				]
			);



		$token = Mockery::mock( \WC_Payment_Token::class);
		$token->shouldReceive('get_token')->andReturn('token12345');

		$tokens = Mockery::mock( 'overload:' . \WC_Payment_Tokens::class );
		$tokens->shouldReceive('get')->andReturn( $token );

		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->shouldReceive('get_status')->andReturn('processing');
		$wc_order->shouldReceive('get_transaction_id')->andReturn('ABC123');
		$wc_order->shouldReceive('get_payment_method')->andReturn(CreditCardGateway::ID);
		$wc_order->shouldReceive('get_payment_tokens')->andReturn(['token12345']);

		when('wc_get_order')->justReturn($wc_order);

		$this->assertSame(
			'ABC123',
			(new SubscriptionHelper())->previous_transaction($subscription, 'token12345')
		);
	}

	public function testCartContainsRenewalReturnsFalseWhenSubscriptionsPluginNotActive()
	{
		// Neither WC_Subscriptions nor WC_Subscriptions_Product exist in the test environment,
		// so plugin_is_active() is reliably false here without needing to mock it.
		$this->assertFalse((new SubscriptionHelper())->cart_contains_renewal());
	}

	public function testLocationsWithSubscriptionProductWhenNothingIsPresent()
	{
		when('is_product')->justReturn(false);
		when('is_wc_endpoint_url')->justReturn(false);

		$helper = $this->partialSubscriptionHelper(false, false, false, false);

		$this->assertSame(
			[
				'product'  => false,
				'payorder' => false,
				'cart'     => false,
			],
			$helper->locations_with_subscription_product()
		);
	}

	public function testLocationsWithSubscriptionProductOnProductPage()
	{
		when('is_product')->justReturn(true);
		when('is_wc_endpoint_url')->justReturn(false);

		$helper = $this->partialSubscriptionHelper(true, false, false, false);

		$this->assertSame(
			[
				'product'  => true,
				'payorder' => false,
				'cart'     => false,
			],
			$helper->locations_with_subscription_product()
		);
	}

	public function testLocationsWithSubscriptionProductOnClassicOrderPayEndpoint()
	{
		when('is_product')->justReturn(false);
		when('is_wc_endpoint_url')->justReturn(true);

		$helper = $this->partialSubscriptionHelper(false, true, false, false);

		$this->assertSame(
			[
				'product'  => false,
				'payorder' => true,
				'cart'     => false,
			],
			$helper->locations_with_subscription_product()
		);
	}

	/**
	 * @scenario Regression test for PCP-2649. WooCommerce Subscriptions can route a manual
	 *           renewal through the cart/Checkout block instead of the classic order-pay
	 *           endpoint, so a cart-based renewal must be classified as "payorder", not "cart" -
	 *           otherwise Google Pay incorrectly treats it as a brand-new subscription and hides
	 *           itself even when manual renewals are accepted.
	 */
	public function testLocationsWithSubscriptionProductWhenCartContainsRenewal()
	{
		when('is_product')->justReturn(false);
		when('is_wc_endpoint_url')->justReturn(false);

		$helper = $this->partialSubscriptionHelper(false, false, true, true);

		$this->assertSame(
			[
				'product'  => false,
				'payorder' => true,
				'cart'     => false,
			],
			$helper->locations_with_subscription_product()
		);
	}

	public function testLocationsWithSubscriptionProductWhenCartContainsNewSubscription()
	{
		when('is_product')->justReturn(false);
		when('is_wc_endpoint_url')->justReturn(false);

		$helper = $this->partialSubscriptionHelper(false, false, true, false);

		$this->assertSame(
			[
				'product'  => false,
				'payorder' => false,
				'cart'     => true,
			],
			$helper->locations_with_subscription_product()
		);
	}

	public function testNeedSubscriptionIntentFalseWhenNotInSubscriptionsApiMode()
	{
		$helper = $this->partialSubscriptionHelperForIntent(true, false, '');

		$this->assertFalse($helper->need_subscription_intent('vaulting_api'));
	}

	public function testNeedSubscriptionIntentTrueForSubscriptionProductWithConnectedPlan()
	{
		$helper = $this->partialSubscriptionHelperForIntent(true, true, 'PLAN-1');

		$this->assertTrue($helper->need_subscription_intent('subscriptions_api'));
	}

	/**
	 * @scenario Regression test for PCP-6696. A subscription product without a connected
	 *           PayPal plan, with manual renewals enabled and vaulting disabled, must use
	 *           the standard checkout flow - forcing subscription intent here made the SDK
	 *           script incompatible with the standard button render that follows, since
	 *           PayPal's SDK requires createSubscription whenever intent=subscription.
	 */
	public function testNeedSubscriptionIntentFalseForSubscriptionProductWithManualRenewalsAndNoPlan()
	{
		$helper = $this->partialSubscriptionHelperForIntent(true, true, '');

		$this->assertFalse($helper->need_subscription_intent('subscriptions_api'));
	}

	public function testNeedSubscriptionIntentTrueForSubscriptionProductWithNoPlanAndNoManualRenewals()
	{
		$helper = $this->partialSubscriptionHelperForIntent(true, false, '');

		$this->assertTrue($helper->need_subscription_intent('subscriptions_api'));
	}

	public function testNeedSubscriptionIntentTrueOnCartContainingSubscription()
	{
		when('is_cart')->justReturn(true);
		when('is_checkout')->justReturn(false);
		$helper = $this->partialSubscriptionHelperForIntent(false, false, '');
		$helper->shouldReceive('cart_contains_subscription')->andReturn(true);

		$this->assertTrue($helper->need_subscription_intent('subscriptions_api'));
	}

	public function testNeedSubscriptionIntentFalseWhenNotASubscriptionContext()
	{
		when('is_cart')->justReturn(false);
		when('is_checkout')->justReturn(false);
		$helper = $this->partialSubscriptionHelperForIntent(false, false, '');

		$this->assertFalse($helper->need_subscription_intent('subscriptions_api'));
	}

	/**
	 * Builds a partial mock of SubscriptionHelper so `need_subscription_intent()` runs for
	 * real while the signals it reads from other methods on the same class are fixed.
	 */
	private function partialSubscriptionHelperForIntent(
		bool $current_product_is_subscription,
		bool $accept_manual_renewals,
		string $paypal_subscription_id
	): SubscriptionHelper {
		$helper = Mockery::mock(SubscriptionHelper::class)->makePartial();
		$helper->shouldReceive('current_product_is_subscription')->andReturn($current_product_is_subscription);
		$helper->shouldReceive('accept_manual_renewals')->andReturn($accept_manual_renewals);
		$helper->shouldReceive('paypal_subscription_id')->andReturn($paypal_subscription_id);

		return $helper;
	}

	/**
	 * Builds a partial mock of SubscriptionHelper so `locations_with_subscription_product()`
	 * runs for real while its individual signal methods return fixed, arranged values.
	 */
	private function partialSubscriptionHelper(
		bool $current_product_is_subscription,
		bool $order_pay_contains_subscription,
		bool $cart_contains_subscription,
		bool $cart_contains_renewal
	): SubscriptionHelper {
		$helper = Mockery::mock(SubscriptionHelper::class)->makePartial();
		$helper->shouldReceive('current_product_is_subscription')->andReturn($current_product_is_subscription);
		$helper->shouldReceive('order_pay_contains_subscription')->andReturn($order_pay_contains_subscription);
		$helper->shouldReceive('cart_contains_subscription')->andReturn($cart_contains_subscription);
		$helper->shouldReceive('cart_contains_renewal')->andReturn($cart_contains_renewal);

		return $helper;
	}
}
