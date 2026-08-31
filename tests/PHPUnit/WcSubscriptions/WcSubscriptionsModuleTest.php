<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcSubscriptions;

use Mockery;
use ReflectionClass;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

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
	 * @return SettingsProvider|Mockery\MockInterface
	 */
	private function create_settings_provider() {
		return Mockery::mock( SettingsProvider::class );
	}

	/**
	 * @param SettingsProvider  $settings_provider  The settings provider stub.
	 * @param SubscriptionHelper $subscription_helper The subscription helper stub.
	 * @return string
	 */
	private function invoke_get_subscriptions_mode( SettingsProvider $settings_provider, SubscriptionHelper $subscription_helper ): string {
		$method = ( new ReflectionClass( $this->testee ) )->getMethod( 'get_subscriptions_mode' );
		$method->setAccessible( true );

		return $method->invoke( $this->testee, $settings_provider, $subscription_helper );
	}

	/**
	 * GIVEN a SubscriptionHelper that resolves the subscriptions mode for a given settings provider
	 * WHEN get_subscriptions_mode() is called with that settings provider and helper
	 * THEN it returns exactly the mode resolved by the helper
	 * AND the helper's resolve_subscription_mode() received that same settings-provider instance,
	 *     exactly once - proving the module delegates rather than re-implementing the decision
	 *
	 * @dataProvider subscription_mode_provider
	 */
	public function test_get_subscriptions_mode_delegates_to_subscription_helper( string $resolved_mode ): void {
		$settings_provider = $this->create_settings_provider();

		$subscription_helper = Mockery::mock( SubscriptionHelper::class );
		$subscription_helper->expects( 'resolve_subscription_mode' )
			->once()
			->with( $settings_provider )
			->andReturn( $resolved_mode );

		$result = $this->invoke_get_subscriptions_mode( $settings_provider, $subscription_helper );

		$this->assertSame( $resolved_mode, $result );
	}

	public function subscription_mode_provider(): array {
		return array(
			'helper resolves the disabled mode'       => array( SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_DISABLED ),
			'helper resolves the subscriptions API mode' => array( SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_SUBSCRIPTIONS ),
		);
	}
}