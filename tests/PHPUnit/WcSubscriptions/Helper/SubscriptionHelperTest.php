<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions\Helper;

use Mockery;
use WC_Order;
use WC_Subscription;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use function Brain\Monkey\Filters\expectApplied;
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
	 * GIVEN WooCommerce Subscriptions is not active
	 * WHEN resolve_subscription_mode() is called
	 * THEN it returns an empty string, since there is no subscriptions mode to resolve
	 */
	public function test_resolve_subscription_mode_returns_empty_string_when_plugin_not_active(): void {
		$settings_provider = Mockery::mock( SettingsProvider::class );

		$helper = Mockery::mock( SubscriptionHelper::class )->makePartial();
		$helper->shouldReceive( 'plugin_is_active' )->andReturn( false );

		$this->assertSame( '', $helper->resolve_subscription_mode( $settings_provider ) );
	}

	/**
	 * GIVEN WooCommerce Subscriptions is active
	 * AND the woocommerce_paypal_payments_subscription_mode_disabled filter forces the disabled mode
	 * WHEN resolve_subscription_mode() is called
	 * THEN it returns 'disable_paypal_subscriptions' regardless of manual renewals or vaulting settings
	 */
	public function test_resolve_subscription_mode_returns_disabled_when_forced_by_filter(): void {
		expectApplied( 'woocommerce_paypal_payments_subscription_mode_disabled' )
			->once()
			->with( false )
			->andReturn( true );

		$settings_provider = Mockery::mock( SettingsProvider::class );

		$helper = Mockery::mock( SubscriptionHelper::class )->makePartial();
		$helper->shouldReceive( 'plugin_is_active' )->andReturn( true );

		$this->assertSame(
			SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_DISABLED,
			$helper->resolve_subscription_mode( $settings_provider )
		);
	}

	/**
	 * GIVEN WooCommerce Subscriptions is active and the disabled-mode filter is not applied
	 * WHEN resolve_subscription_mode() is called
	 * THEN a manual-renewal-only subscription with vaulting disabled resolves to
	 *      'disable_paypal_subscriptions'
	 * AND vaulting enabled resolves to 'vaulting_api'
	 * AND vaulting disabled with automatic renewal required resolves to 'subscriptions_api'
	 *
	 * @dataProvider subscription_mode_provider
	 */
	public function test_resolve_subscription_mode_decides_between_disabled_vaulting_and_subscriptions_api(
		bool $accept_manual_renewals,
		bool $save_paypal_and_venmo,
		string $expected_mode
	): void {
		expectApplied( 'woocommerce_paypal_payments_subscription_mode_disabled' )
			->once()
			->with( false )
			->andReturn( false );

		$settings_provider = Mockery::mock( SettingsProvider::class );
		$settings_provider->allows( 'save_paypal_and_venmo' )->andReturn( $save_paypal_and_venmo );

		$helper = Mockery::mock( SubscriptionHelper::class )->makePartial();
		$helper->shouldReceive( 'plugin_is_active' )->andReturn( true );
		$helper->shouldReceive( 'accept_manual_renewals' )->andReturn( $accept_manual_renewals );

		$this->assertSame( $expected_mode, $helper->resolve_subscription_mode( $settings_provider ) );
	}

	public function subscription_mode_provider(): array {
		return array(
			'manual renewal accepted and vaulting disabled disables PayPal subscriptions' => array( true, false, SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_DISABLED ),
			'vaulting enabled resolves to vaulting API'                                    => array( false, true, SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_VAULTING ),
			'automatic renewal required and vaulting disabled keeps subscriptions API'     => array( false, false, SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_SUBSCRIPTIONS ),
		);
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

	/**
	 * GIVEN the cart does not contain a subscription
	 * WHEN subscription_cart_processable() is called
	 * THEN it returns true without resolving the subscription mode or checking button eligibility,
	 *      since a non-subscription cart is always processable
	 */
	public function test_subscription_cart_processable_returns_true_when_cart_has_no_subscription(): void {
		$settings_provider = Mockery::mock( SettingsProvider::class );

		$helper = Mockery::mock( SubscriptionHelper::class )->makePartial();
		$helper->shouldReceive( 'cart_contains_subscription' )->andReturn( false );
		$helper->shouldNotReceive( 'resolve_subscription_mode' );
		$helper->shouldNotReceive( 'paypal_subscription_button_allowed' );

		$this->assertTrue( $helper->subscription_cart_processable( $settings_provider ) );
	}

	/**
	 * GIVEN a subscription is in the cart
	 * WHEN subscription_cart_processable() is called
	 * THEN it resolves whether PayPal Subscriptions mode applies and whether a vault token can be
	 *      saved, then defers the final decision to paypal_subscription_button_allowed()
	 * AND a vault token can only be saved when both a connected merchant (client_id) and
	 *     "Save PayPal and Venmo" are present - one without the other keeps it un-savable
	 *
	 * @dataProvider subscription_cart_processable_wiring_provider
	 */
	public function test_subscription_cart_processable_wires_mode_and_vault_token_signals(
		string $resolved_mode,
		bool $save_paypal_and_venmo,
		string $client_id,
		bool $expected_is_paypal_subscription,
		bool $expected_can_save_vault_token,
		bool $button_allowed_result
	): void {
		$settings_provider = Mockery::mock( SettingsProvider::class );
		$settings_provider->allows( 'save_paypal_and_venmo' )->andReturn( $save_paypal_and_venmo );
		$settings_provider->allows( 'merchant_data' )->andReturn(
			new MerchantConnectionDTO( false, $client_id, '', '' )
		);

		$helper = Mockery::mock( SubscriptionHelper::class )->makePartial();
		$helper->shouldReceive( 'cart_contains_subscription' )->andReturn( true );
		$helper->shouldReceive( 'resolve_subscription_mode' )
			->with( $settings_provider )
			->andReturn( $resolved_mode );
		$helper->shouldReceive( 'paypal_subscription_button_allowed' )
			->with( $expected_is_paypal_subscription, $expected_can_save_vault_token )
			->andReturn( $button_allowed_result );

		$this->assertSame(
			$button_allowed_result,
			$helper->subscription_cart_processable( $settings_provider )
		);
	}

	public function subscription_cart_processable_wiring_provider(): array {
		return array(
			'vaulting mode with vaulting enabled and connected merchant allows the button' => array(
				SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_VAULTING,
				true,
				'client-id-123',
				false,
				true,
				true,
			),
			'subscriptions API mode with a PayPal plan allows the button'                  => array(
				SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_SUBSCRIPTIONS,
				false,
				'',
				true,
				false,
				true,
			),
			'subscriptions API mode without a plan and vaulting disabled hides the button' => array(
				SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_SUBSCRIPTIONS,
				false,
				'',
				true,
				false,
				false,
			),
			'manual-renewal-only subscription allows the button'                           => array(
				SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_DISABLED,
				false,
				'',
				false,
				false,
				true,
			),
			'vaulting enabled but no connected merchant keeps the vault token un-savable'   => array(
				SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_VAULTING,
				true,
				'',
				false,
				false,
				false,
			),
		);
	}

	/**
	 * GIVEN a subscription requiring a PayPal plan is in the cart
	 * AND no PayPal plan is linked to the product
	 * AND vaulting ("Save PayPal and Venmo") is disabled
	 * WHEN subscription_cart_processable() runs end-to-end (mode resolution and button
	 *      eligibility are not stubbed)
	 * THEN it returns false, so the PayPal gateway is hidden instead of shown disabled
	 */
	public function test_subscription_cart_processable_end_to_end_hides_gateway_when_vaulting_disabled_and_no_plan(): void {
		expectApplied( 'woocommerce_paypal_payments_subscription_mode_disabled' )
			->once()
			->with( false )
			->andReturn( false );

		$settings_provider = Mockery::mock( SettingsProvider::class );
		$settings_provider->allows( 'save_paypal_and_venmo' )->andReturn( false );
		$settings_provider->allows( 'merchant_data' )->andReturn(
			new MerchantConnectionDTO( false, '', '', '' )
		);

		$helper = Mockery::mock( SubscriptionHelper::class )->makePartial();
		$helper->shouldReceive( 'cart_contains_subscription' )->andReturn( true );
		$helper->shouldReceive( 'plugin_is_active' )->andReturn( true );
		$helper->shouldReceive( 'accept_manual_renewals' )->andReturn( false );
		$helper->shouldReceive( 'checkout_subscription_product_allowed' )->andReturn( false );

		$this->assertFalse( $helper->subscription_cart_processable( $settings_provider ) );
	}
}
