<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

use Mockery;
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

	private function venmo_order_data(): array
	{
		return array(
			'payment_source' => array(
				'venmo' => array(
					'experience_context' => array( 'return_url' => 'https://example.test' ),
				),
			),
		);
	}

	private function paypal_order_data(): array
	{
		return array(
			'payment_source' => array(
				'paypal' => array(
					'experience_context' => array( 'return_url' => 'https://example.test' ),
				),
			),
		);
	}

	private function stub_pay_now_subscription_order(): void
	{
		$this->settings->shouldReceive('save_paypal_and_venmo')->andReturn(true);
		$this->settings->shouldReceive('save_card_details')->andReturn(false);
		$this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('current_product_is_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('order_pay_contains_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('plugin_is_active')->andReturn(true);
		$this->subscription_helper->shouldReceive('has_subscription')->with(123)->andReturn(true);
	}

	/**
	 * GIVEN Venmo vaulting is enabled and the cart holds a one-off product
	 * WHEN the order is created with Venmo
	 * THEN no vault attributes are requested, as PayPal rejects vaulting on one-time Venmo purchases
	 */
	public function testDoesNotVaultVenmoWithoutSubscription(): void
	{
		$this->settings->shouldReceive('save_paypal_and_venmo')->andReturn(true);
		$this->settings->shouldReceive('save_card_details')->andReturn(false);
		$this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('current_product_is_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('order_pay_contains_subscription')->andReturn(false);

		$filter = $this->captured_request_body_filter();

		$result = $filter(
			$this->venmo_order_data(),
			PayPalGateway::ID,
			array( 'funding_source' => 'venmo' )
		);

		$this->assertArrayNotHasKey('attributes', $result['payment_source']['venmo']);
	}

	/**
	 * GIVEN the cart contains a subscription
	 * WHEN the order is created with Venmo
	 * THEN the Venmo payment source is vaulted for merchant-initiated renewals
	 */
	public function testVaultsVenmoWhenCartContainsSubscription(): void
	{
		$this->settings->shouldReceive('save_paypal_and_venmo')->andReturn(true);
		$this->settings->shouldReceive('save_card_details')->andReturn(false);
		$this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(true);
		$this->subscription_helper->shouldReceive('current_product_is_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('order_pay_contains_subscription')->andReturn(false);

		$filter = $this->captured_request_body_filter();

		$result = $filter(
			$this->venmo_order_data(),
			PayPalGateway::ID,
			array( 'funding_source' => 'venmo' )
		);

		$vault = $result['payment_source']['venmo']['attributes']['vault'];
		$this->assertSame('ON_SUCCESS', $vault['store_in_vault']);
		$this->assertSame('MERCHANT', $vault['usage_type']);
		$this->assertFalse($vault['permit_multiple_payment_tokens']);
		$this->assertArrayHasKey('experience_context', $result['payment_source']['venmo']);
	}

	/**
	 * GIVEN a pay-now request for an existing order that contains a subscription
	 * WHEN the order is created with Venmo
	 * THEN the Venmo payment source is vaulted
	 */
	public function testVaultsVenmoOnPayNowForSubscriptionOrder(): void
	{
		$this->stub_pay_now_subscription_order();

		$filter = $this->captured_request_body_filter();

		$result = $filter(
			$this->venmo_order_data(),
			PayPalGateway::ID,
			array(
				'funding_source' => 'venmo',
				'context'        => 'pay-now',
				'order_id'       => '123',
			)
		);

		$vault = $result['payment_source']['venmo']['attributes']['vault'];
		$this->assertSame('ON_SUCCESS', $vault['store_in_vault']);
		$this->assertSame('MERCHANT', $vault['usage_type']);
	}

	/**
	 * GIVEN the cart holds a one-off product
	 * WHEN the order is created with PayPal
	 * THEN the PayPal wallet is still vaulted, as PayPal vaulting does not depend on subscriptions
	 */
	public function testVaultsPayPalWithoutSubscription(): void
	{
		$this->settings->shouldReceive('save_paypal_and_venmo')->andReturn(true);
		$this->settings->shouldReceive('save_card_details')->andReturn(false);
		$this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('current_product_is_subscription')->andReturn(false);
		$this->subscription_helper->shouldReceive('order_pay_contains_subscription')->andReturn(false);

		$filter = $this->captured_request_body_filter();

		$result = $filter(
			$this->paypal_order_data(),
			PayPalGateway::ID,
			array( 'funding_source' => 'paypal' )
		);

		$vault = $result['payment_source']['paypal']['attributes']['vault'];
		$this->assertSame('ON_SUCCESS', $vault['store_in_vault']);
		$this->assertSame('MERCHANT', $vault['usage_type']);
		$this->assertFalse($vault['permit_multiple_payment_tokens']);
	}

	/**
	 * GIVEN a pay-now request for an existing order that contains a subscription
	 * WHEN the order is created with Apple Pay
	 * THEN the Apple Pay payment source is vaulted
	 */
	public function testVaultsApplePayOnPayNowForSubscriptionOrder(): void
	{
		$this->stub_pay_now_subscription_order();

		$filter = $this->captured_request_body_filter();

		$result = $filter(
			$this->apple_pay_order_data(),
			PayPalGateway::ID,
			array(
				'funding_source' => 'apple_pay',
				'context'        => 'pay-now',
				'order_id'       => '123',
			)
		);

		$this->assertSame(
			'ON_SUCCESS',
			$result['payment_source']['apple_pay']['attributes']['vault']['store_in_vault']
		);
	}
}
