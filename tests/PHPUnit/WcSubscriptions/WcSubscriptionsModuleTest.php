<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcSubscriptions;

use Mockery;
use ReflectionClass;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

use function Brain\Monkey\Filters\expectApplied;

/**
 * @covers \WooCommerce\PayPalCommerce\WcSubscriptions\WcSubscriptionsModule
 */
class WcSubscriptionsModuleTest extends TestCase {

	private WcSubscriptionsModule $testee;

	public function setUp(): void {
		parent::setUp();

		$this->testee = new WcSubscriptionsModule();
	}

	/**
	 * @param bool   $accept_manual_renewals Value returned by SubscriptionHelper::accept_manual_renewals().
	 * @param bool   $save_paypal_and_venmo  Value returned by SettingsProvider::save_paypal_and_venmo().
	 * @return SettingsProvider|Mockery\MockInterface
	 */
	private function create_settings_provider( bool $save_paypal_and_venmo ) {
		$settings_provider = Mockery::mock( SettingsProvider::class );
		$settings_provider->allows( 'save_paypal_and_venmo' )->andReturn( $save_paypal_and_venmo );

		return $settings_provider;
	}

	/**
	 * @return SubscriptionHelper|Mockery\MockInterface
	 */
	private function create_subscription_helper( bool $accept_manual_renewals ) {
		$subscription_helper = Mockery::mock( SubscriptionHelper::class );
		$subscription_helper->allows( 'plugin_is_active' )->andReturn( true );
		$subscription_helper->allows( 'accept_manual_renewals' )->andReturn( $accept_manual_renewals );

		return $subscription_helper;
	}

	private function invoke_get_subscriptions_mode( SettingsProvider $settings_provider, SubscriptionHelper $subscription_helper ): string {
		$method = ( new ReflectionClass( $this->testee ) )->getMethod( 'get_subscriptions_mode' );
		$method->setAccessible( true );

		return $method->invoke( $this->testee, $settings_provider, $subscription_helper );
	}

	/**
	 * @scenario Manual-renewal-only subscription, vaulting disabled
	 *
	 * Given WooCommerce Subscriptions "Accept Manual Renewals" is enabled
	 * And vault/save-PayPal-and-Venmo is disabled
	 * When get_subscriptions_mode() is called
	 * Then it must return 'disable_paypal_subscriptions', not 'subscriptions_api',
	 * so the cart is routed through the plain Orders API instead of requiring
	 * a linked PayPal subscription plan.
	 */
	public function test_returns_disable_paypal_subscriptions_when_manual_renewal_accepted_and_vaulting_disabled(): void {
		expectApplied( 'woocommerce_paypal_payments_subscription_mode_disabled' )
			->once()
			->with( false )
			->andReturn( false );

		$settings_provider    = $this->create_settings_provider( false );
		$subscription_helper  = $this->create_subscription_helper( true );

		$result = $this->invoke_get_subscriptions_mode( $settings_provider, $subscription_helper );

		$this->assertSame( 'disable_paypal_subscriptions', $result );
	}

	/**
	 * @scenario Subscription requiring automatic renewal, vaulting disabled
	 *
	 * Given the subscription requires automatic renewals (manual renewals not accepted)
	 * And vault/save-PayPal-and-Venmo is disabled
	 * When get_subscriptions_mode() is called
	 * Then it must still return 'subscriptions_api' — unchanged from current behaviour,
	 * proving the manual-renewal fix does not regress the auto-renewal-required path.
	 */
	public function test_returns_subscriptions_api_when_auto_renewal_required_and_vaulting_disabled(): void {
		expectApplied( 'woocommerce_paypal_payments_subscription_mode_disabled' )
			->once()
			->with( false )
			->andReturn( false );

		$settings_provider   = $this->create_settings_provider( false );
		$subscription_helper = $this->create_subscription_helper( false );

		$result = $this->invoke_get_subscriptions_mode( $settings_provider, $subscription_helper );

		$this->assertSame( 'subscriptions_api', $result );
	}
}