<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\UserIdToken;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Actions\expectAdded as expectActionAdded;
use function Brain\Monkey\Filters\expectAdded as expectFilterAdded;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\SavePaymentMethods\SavePaymentMethodsModule
 */
class SavePaymentMethodsModuleTest extends TestCase
{
	private $container;
	private $settings;
	private $subscription_helper;

	public function setUp(): void
	{
		parent::setUp();

		when('get_current_user_id')->justReturn(0);
		when('get_user_meta')->justReturn('');

		$this->settings            = Mockery::mock(SettingsProvider::class);
		$this->subscription_helper = Mockery::mock(SubscriptionHelper::class);

		$this->container = Mockery::mock(ContainerInterface::class);
		$this->container->shouldReceive('get')->with('save-payment-methods.eligible')->andReturn(true);
		$this->container->shouldReceive('get')->with('settings.settings-provider')->andReturn($this->settings);
		$this->container->shouldReceive('get')->with('wc-subscriptions.helper')->andReturn($this->subscription_helper);
	}

	/**
	 * Runs the module, fires the deferred `after_setup_theme` registration (gated by the save
	 * settings), and returns the captured `ppcp_create_order_request_body_data` filter so it can
	 * be invoked directly in assertions.
	 */
	private function captured_request_body_filter(): callable
	{
		$captured_setup  = null;
		$captured_filter = null;

		expectActionAdded('after_setup_theme')
			->once()
			->whenHappen(
				static function ( $callback ) use ( &$captured_setup ) {
					$captured_setup = $callback;
				}
			);

		expectFilterAdded('ppcp_create_order_request_body_data')
			->once()
			->whenHappen(
				static function ( $callback ) use ( &$captured_filter ) {
					$captured_filter = $callback;
				}
			);

		( new SavePaymentMethodsModule() )->run( $this->container );

		$this->assertIsCallable($captured_setup);

		// Fires the deferred registration that adds the request-body filter.
		$captured_setup();

		$this->assertIsCallable($captured_filter);

		return $captured_filter;
	}

	private function apple_pay_order_data(): array
	{
		return array(
			'payment_source' => array(
				'apple_pay' => array(
					'experience_context' => array( 'return_url' => 'https://example.test' ),
				),
			),
		);
	}

	/**
	 * Runs the module, fires the deferred setup, and returns the captured localized script-data
	 * filter so its behavior can be tested without rendering frontend assets.
	 */
	private function captured_localized_script_data_filter(): callable
	{
		$captured_setup  = null;
		$captured_filter = null;

		expectActionAdded('after_setup_theme')
			->once()
			->whenHappen(
				static function ( $callback ) use ( &$captured_setup ) {
					$captured_setup = $callback;
				}
			);

		expectFilterAdded('woocommerce_paypal_payments_localized_script_data')
			->once()
			->whenHappen(
				static function ( $callback ) use ( &$captured_filter ) {
					$captured_filter = $callback;
				}
			);

		( new SavePaymentMethodsModule() )->run( $this->container );

		$this->assertIsCallable($captured_setup);
		$captured_setup();
		$this->assertIsCallable($captured_filter);

		return $captured_filter;
	}

	/**
	 * @scenario Mini Cart hydration on a non-cart page must not request a PayPal user ID token.
	 */
	public function testDoesNotAddIdTokenDuringMiniCartDataRequest(): void
	{
		when('doing_action')->justReturn(true);
		when('is_cart')->justReturn(false);
		when('has_block')->justReturn(false);

		$this->settings->shouldReceive('save_paypal_and_venmo')->andReturn(true);
		$this->settings->shouldReceive('save_card_details')->andReturn(false);
		$this->container->shouldNotReceive('get')->with('api.user-id-token');

		$filter = $this->captured_localized_script_data_filter();
		$data   = array( 'marker' => true );

		$this->assertSame($data, $filter($data));
	}

	/**
	 * @scenario Full Cart data collection must retain the ID token required for vaulting.
	 */
	public function testAddsIdTokenDuringCartDataRequest(): void
	{
		when('doing_action')->justReturn(true);
		when('is_cart')->justReturn(true);
		when('is_user_logged_in')->justReturn(true);

		$api = Mockery::mock(UserIdToken::class);
		$api->shouldReceive('id_token')->once()->with('')->andReturn('user-id-token');

		$this->settings->shouldReceive('save_paypal_and_venmo')->andReturn(true);
		$this->settings->shouldReceive('save_card_details')->andReturn(false);
		$this->container->shouldReceive('get')->with('api.user-id-token')->andReturn($api);
		$this->container->shouldReceive('get')->with('woocommerce.logger.woocommerce')->andReturn(
			Mockery::mock(LoggerInterface::class)
		);

		$filter = $this->captured_localized_script_data_filter();
		$result = $filter(array());

		$this->assertSame('user-id-token', $result['save_payment_methods']['id_token']);
	}

	/**
	 * @scenario Apple Pay purchase of a subscription must request vaulting so a payment token is
	 *           stored for later merchant-initiated renewals.
	 */
	public function testAddsVaultAttributesForApplePaySubscription(): void
	{
		$this->settings->shouldReceive('save_paypal_and_venmo')->andReturn(true);
		$this->settings->shouldReceive('save_card_details')->andReturn(false);
		$this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(true);
		$this->subscription_helper->shouldReceive('current_product_is_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('order_pay_contains_subscription')->andReturn(false);

		$filter = $this->captured_request_body_filter();

		$result = $filter(
			$this->apple_pay_order_data(),
			PayPalGateway::ID,
			array( 'funding_source' => 'apple_pay' )
		);

		$this->assertSame(
			'ON_SUCCESS',
			$result['payment_source']['apple_pay']['attributes']['vault']['store_in_vault']
		);
		// experience_context must be preserved.
		$this->assertArrayHasKey('experience_context', $result['payment_source']['apple_pay']);
		// PayPal-wallet-specific attributes must NOT be added for Apple Pay.
		$this->assertArrayNotHasKey('usage_type', $result['payment_source']['apple_pay']['attributes']['vault']);
	}

	/**
	 * @scenario The saved PayPal customer id must be attached so the token is vaulted against the
	 *           right customer for returning subscribers.
	 */
	public function testAddsCustomerIdWhenAvailable(): void
	{
		when('get_current_user_id')->justReturn(1);
		when('get_user_meta')->alias(
			static function ( $user_id, $key ) {
				return '_ppcp_target_customer_id' === $key ? 'CUST-123' : '';
			}
		);

		$this->settings->shouldReceive('save_paypal_and_venmo')->andReturn(true);
		$this->settings->shouldReceive('save_card_details')->andReturn(false);
		$this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(true);
		$this->subscription_helper->shouldReceive('current_product_is_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('order_pay_contains_subscription')->andReturn(false);

		$filter = $this->captured_request_body_filter();

		$result = $filter(
			$this->apple_pay_order_data(),
			PayPalGateway::ID,
			array( 'funding_source' => 'apple_pay' )
		);

		$this->assertSame(
			'CUST-123',
			$result['payment_source']['apple_pay']['attributes']['customer']['id']
		);
	}

	/**
	 * @scenario A one-off (non-subscription) Apple Pay purchase must NOT be vaulted, per Apple
	 *           guidelines against reusing Apple Pay for returning-buyer checkout.
	 */
	public function testDoesNotVaultApplePayWithoutSubscription(): void
	{
		$this->settings->shouldReceive('save_paypal_and_venmo')->andReturn(true);
		$this->settings->shouldReceive('save_card_details')->andReturn(false);
		$this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('current_product_is_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('order_pay_contains_subscription')->andReturn(false);

		$filter = $this->captured_request_body_filter();

		$result = $filter(
			$this->apple_pay_order_data(),
			PayPalGateway::ID,
			array( 'funding_source' => 'apple_pay' )
		);

		$this->assertArrayNotHasKey('attributes', $result['payment_source']['apple_pay']);
	}

	/**
	 * @scenario When wallet vaulting is disabled, Apple Pay must not be vaulted even for a
	 *           subscription.
	 */
	public function testDoesNotVaultApplePayWhenSaveDisabled(): void
	{
		// Gate passes via card saving, but wallet saving is off.
		$this->settings->shouldReceive('save_paypal_and_venmo')->andReturn(false);
		$this->settings->shouldReceive('save_card_details')->andReturn(true);

		$filter = $this->captured_request_body_filter();

		$result = $filter(
			$this->apple_pay_order_data(),
			PayPalGateway::ID,
			array( 'funding_source' => 'apple_pay' )
		);

		$this->assertArrayNotHasKey('attributes', $result['payment_source']['apple_pay']);
	}
}
