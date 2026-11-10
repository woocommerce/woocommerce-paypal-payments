<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcSubscriptions;

use Mockery;
use ReflectionClass;
use WC_Order;
use WC_Subscription;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use function Brain\Monkey\Functions\when;
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

	/**
	 * Registers the gateway support filters and returns the callback attached to
	 * WooCommerce Subscriptions' per-subscription gateway support filter.
	 */
	private function capture_subscription_supports_filter( bool $save_card_details, bool $helper_result ): callable {
		$settings_provider = $this->create_settings_provider();
		$settings_provider->allows( 'save_card_details' )->andReturn( $save_card_details );

		$subscription_helper = Mockery::mock( SubscriptionHelper::class );
		$subscription_helper->allows( 'is_mode_disabled_for_manual_renewals' )->andReturn( $helper_result );

		$callbacks = $this->register_gateway_support_filters(
			$this->mock_container( $settings_provider, $subscription_helper )
		);

		return $callbacks['woocommerce_subscription_payment_gateway_supports'];
	}

	/**
	 * @return ContainerInterface|Mockery\MockInterface
	 */
	private function mock_container( SettingsProvider $settings_provider, SubscriptionHelper $subscription_helper ) {
		$container = Mockery::mock( ContainerInterface::class );
		$container->allows( 'get' )->andReturnUsing(
			static function ( string $id ) use ( $settings_provider, $subscription_helper ) {
				return 'settings.settings-provider' === $id ? $settings_provider : $subscription_helper;
			}
		);

		return $container;
	}

	/**
	 * @return array<string, callable> The registered filter callbacks by hook name.
	 */
	private function register_gateway_support_filters( ContainerInterface $container ): array {
		$callbacks = array();
		when( 'add_filter' )->alias(
			static function ( $hook, $callback ) use ( &$callbacks ) {
				$callbacks[ $hook ] = $callback;
				return true;
			}
		);

		$method = ( new ReflectionClass( $this->testee ) )->getMethod( 'add_gateways_support' );
		$method->setAccessible( true );
		$method->invoke( $this->testee, $container );

		return $callbacks;
	}

	/**
	 * GIVEN a subscription paid with a given gateway, and WooCommerce Subscriptions asking whether
	 *       that gateway supports a feature
	 * WHEN the gateway-support filter runs
	 * THEN a PayPal subscription (or a card subscription with saved cards enabled) is supported
	 *      for vault features exactly when the helper says manual renewals disabled the mode
	 * AND an existing positive answer, a non-vault feature or any other gateway is left unchanged
	 *
	 * @dataProvider subscription_support_provider
	 */
	public function test_subscription_gateway_support_filter_keeps_saved_token_renewals_working(
		bool $supported,
		string $feature,
		string $payment_method,
		bool $save_card_details,
		bool $helper_result,
		bool $expected
	): void {
		$callback = $this->capture_subscription_supports_filter( $save_card_details, $helper_result );

		$subscription = Mockery::mock( WC_Subscription::class );
		$subscription->allows( 'get_payment_method' )->andReturn( $payment_method );

		$this->assertSame( $expected, $callback( $supported, $feature, $subscription ) );
	}

	public function subscription_support_provider(): array {
		return array(
			'paypal subscription while manual renewals disabled the mode' => array( false, 'subscription_suspension', 'ppcp-gateway', false, true, true ),
			'paypal subscription while the mode is not disabled'          => array( false, 'subscription_suspension', 'ppcp-gateway', false, false, false ),
			'already supported feature stays supported'                   => array( true, 'subscription_suspension', 'ppcp-gateway', false, false, true ),
			'feature outside the vault list stays unsupported'            => array( false, 'gateway_scheduled_payments', 'ppcp-gateway', false, true, false ),
			'another gateway stays unsupported'                           => array( false, 'subscription_suspension', 'bacs', true, true, false ),
			'card subscription with saved cards enabled'                  => array( false, 'subscription_suspension', 'ppcp-credit-card-gateway', true, true, true ),
			'card subscription without saved cards stays unsupported'     => array( false, 'subscription_suspension', 'ppcp-credit-card-gateway', false, true, false ),
		);
	}

	/**
	 * GIVEN the support check is made for something that is not a subscription
	 * WHEN the gateway-support filter runs
	 * THEN the incoming answer is returned unchanged
	 *
	 * @dataProvider non_subscription_argument_provider
	 */
	public function test_subscription_gateway_support_filter_ignores_non_subscriptions( $subscription ): void {
		$callback = $this->capture_subscription_supports_filter( true, true );

		$this->assertFalse( $callback( false, 'subscription_suspension', $subscription ) );
	}

	public function non_subscription_argument_provider(): array {
		return array(
			'no subscription given' => array( null ),
			'a plain order'         => array( Mockery::mock( WC_Order::class ) ),
		);
	}
}