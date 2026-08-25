<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

class FastlaneConfigTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * @var CardPaymentsConfiguration&Mockery\MockInterface
	 */
	private $card_payments_configuration;

	public function setUp(): void {
		parent::setUp();

		// Satisfy should_render()'s wp_loaded guard; the guard test overrides this.
		when( 'did_action' )->justReturn( 1 );

		when( 'is_user_logged_in' )->justReturn( false );

		$this->card_payments_configuration = Mockery::mock( CardPaymentsConfiguration::class );
	}

	private function configFor( ?SubscriptionHelper $subscription_helper = null, ?callable $is_eligible = null ): FastlaneConfig {
		return new FastlaneConfig(
			$this->card_payments_configuration,
			$subscription_helper ?? $this->noSubscriptions(),
			$is_eligible ?? $this->eligible()
		);
	}

	private function noSubscriptions(): SubscriptionHelper {
		$helper = Mockery::mock( SubscriptionHelper::class );
		$helper->shouldReceive( 'cart_contains_subscription' )->andReturn( false );

		return $helper;
	}

	private function subscriptionInCart(): SubscriptionHelper {
		$helper = Mockery::mock( SubscriptionHelper::class );
		$helper->shouldReceive( 'cart_contains_subscription' )->andReturn( true );

		return $helper;
	}

	private function eligible(): callable {
		return static fn(): bool => true;
	}

	private function notEligible(): callable {
		return static fn(): bool => false;
	}

	private function failIfCalled(): callable {
		return function (): bool {
			$this->fail( 'The eligibility callable must not be invoked.' );

			return false;
		};
	}

	private function useFastlane( bool $enabled ): void {
		$this->card_payments_configuration->shouldReceive( 'use_fastlane' )->andReturn( $enabled );
	}

	/**
	 * GIVEN wp_loaded has not run yet
	 * AND every other gate would otherwise allow Fastlane to render
	 * WHEN checking whether Fastlane should render at checkout
	 * THEN it is reported as not rendering
	 * AND the merchant reads a doing-it-wrong notice rather than a silently wrong answer
	 */
	public function testNotRenderedBeforeWpLoadedHasRun(): void {
		when( 'did_action' )->justReturn( 0 );
		when( '_doing_it_wrong' )->justReturn( null );

		$this->useFastlane( true );

		$config = $this->configFor( $this->noSubscriptions(), $this->failIfCalled() );

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN the page context is one Fastlane supports or not
	 * WHEN checking whether Fastlane should render there
	 * THEN only the checkout and checkout-block contexts are accepted
	 *
	 * @dataProvider context_provider
	 */
	public function testRenderedOnlyOnSupportedContexts( string $context, bool $expected ): void {
		$this->useFastlane( true );

		$config = $this->configFor();

		$this->assertSame( $expected, $config->should_render( $context ) );
	}

	public function context_provider(): array {
		return array(
			'classic checkout is supported'    => array( 'checkout', true ),
			'checkout block is supported'      => array( 'checkout-block', true ),
			'product page is not supported'    => array( 'product', false ),
			'cart page is not supported'       => array( 'cart', false ),
			'pay-now page is not supported'    => array( 'pay-now', false ),
			'mini-cart is not supported'       => array( 'mini-cart', false ),
			'an unknown context is refused'    => array( 'some-unknown-context', false ),
		);
	}

	/**
	 * GIVEN the buyer is already logged in
	 * WHEN checking whether Fastlane should render at checkout
	 * THEN it is reported as not rendering, since a known customer already has
	 * their details on file
	 */
	public function testNotRenderedWhenBuyerIsLoggedIn(): void {
		when( 'is_user_logged_in' )->justReturn( true );

		$this->useFastlane( true );

		$config = $this->configFor();

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN the merchant's card payments configuration does not enable Fastlane
	 * WHEN checking whether Fastlane should render at checkout
	 * THEN it is reported as not rendering
	 */
	public function testNotRenderedWhenCardPaymentsConfigurationDisablesFastlane(): void {
		$this->useFastlane( false );

		$config = $this->configFor();

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN the cart contains a subscription product
	 * WHEN checking whether Fastlane should render at checkout
	 * THEN it is reported as not rendering, because this flow does not produce a
	 * vaulted payment method a subscription would need
	 */
	public function testNotRenderedWhenCartContainsSubscription(): void {
		$this->useFastlane( true );

		$config = $this->configFor( $this->subscriptionInCart(), $this->failIfCalled() );

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN every other gate is satisfied but the injected eligibility callable
	 * reports the merchant is not eligible (country/currency, merchant filter or
	 * the gateway being disabled)
	 * WHEN checking whether Fastlane should render at checkout
	 * THEN it is reported as not rendering
	 */
	public function testNotRenderedWhenNotEligible(): void {
		$this->useFastlane( true );

		$config = $this->configFor( $this->noSubscriptions(), $this->notEligible() );

		$this->assertFalse( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN every gate that controls rendering is satisfied
	 * WHEN checking whether Fastlane should render at checkout
	 * THEN it is reported as rendering
	 */
	public function testRenderedWhenNothingBlocksIt(): void {
		$this->useFastlane( true );

		$config = $this->configFor( $this->noSubscriptions(), $this->eligible() );

		$this->assertTrue( $config->should_render( 'checkout' ) );
	}

	/**
	 * GIVEN an eligibility callable
	 * WHEN the config object is constructed and should_render() is not called
	 * THEN the callable is never invoked
	 */
	public function testConstructionDoesNotInvokeEligibilityCallable(): void {
		$this->configFor( $this->noSubscriptions(), $this->failIfCalled() );

		$this->addToAssertionCount( 1 );
	}
}
