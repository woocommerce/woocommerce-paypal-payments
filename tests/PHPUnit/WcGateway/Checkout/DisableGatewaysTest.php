<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Checkout;

use Mockery;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

/**
 * GIVEN scenarios only exercise the subscription-cart-processable branch of the handler; all
 * other collaborators are stubbed to their "do nothing" outcome so that branch is isolated.
 */
class DisableGatewaysTest extends TestCase {

	private SettingsProvider $settings_provider;
	private SettingsStatus $settings_status;
	private SubscriptionHelper $subscription_helper;
	private Context $context;
	private CardPaymentsConfiguration $card_configuration;

	public function setUp(): void {
		parent::setUp();

		when( 'WC' )->justReturn( (object) array( 'payment_gateways' => null ) );

		$this->settings_provider = Mockery::mock( SettingsProvider::class );
		$this->settings_provider->allows( 'merchant_data' )->andReturn(
			new MerchantConnectionDTO( false, 'client-id-123', '', '' )
		);

		$this->settings_status = Mockery::mock( SettingsStatus::class );
		$this->settings_status->allows( 'is_smart_button_enabled_for_location' )->andReturn( true );

		$this->subscription_helper = Mockery::mock( SubscriptionHelper::class );
		$this->subscription_helper->allows( 'cart_contains_paypal_subscription_product' )->andReturn( false );

		$this->context = Mockery::mock( Context::class );
		$this->context->allows( 'is_paypal_continuation' )->andReturn( false );

		$this->card_configuration = Mockery::mock( CardPaymentsConfiguration::class );
		$this->card_configuration->allows( 'use_acdc' )->andReturn( false );
	}

	/**
	 * GIVEN the classic checkout is showing the PayPal gateway
	 * AND the cart contains a subscription that cannot be processed (e.g. vaulting is disabled
	 *     and the product has no linked PayPal plan)
	 * WHEN DisableGateways::handler() filters the available payment methods
	 * THEN the PayPal gateway is removed instead of being shown with a disabled button
	 */
	public function test_handler_hides_paypal_gateway_when_subscription_cart_not_processable(): void {
		$this->subscription_helper->allows( 'subscription_cart_processable' )
			->with( $this->settings_provider )
			->andReturn( false );

		$handler = $this->create_handler();

		$methods = $handler->handler( array( PayPalGateway::ID => 'paypal-gateway' ) );

		$this->assertArrayNotHasKey( PayPalGateway::ID, $methods );
	}

	/**
	 * GIVEN the classic checkout is showing the PayPal gateway
	 * AND the cart's subscription can be processed by PayPal (e.g. a linked plan exists or
	 *     vaulting is available)
	 * WHEN DisableGateways::handler() filters the available payment methods
	 * THEN the PayPal gateway remains available
	 */
	public function test_handler_keeps_paypal_gateway_when_subscription_cart_is_processable(): void {
		$this->subscription_helper->allows( 'subscription_cart_processable' )
			->with( $this->settings_provider )
			->andReturn( true );

		$handler = $this->create_handler();

		$methods = $handler->handler( array( PayPalGateway::ID => 'paypal-gateway' ) );

		$this->assertArrayHasKey( PayPalGateway::ID, $methods );
	}

	private function create_handler(): DisableGateways {
		return new DisableGateways(
			$this->settings_provider,
			$this->settings_status,
			$this->subscription_helper,
			$this->context,
			$this->card_configuration,
			'US'
		);
	}
}
