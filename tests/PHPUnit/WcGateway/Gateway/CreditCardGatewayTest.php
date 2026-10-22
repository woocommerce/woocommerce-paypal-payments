<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Payment_Token;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcPaymentTokens\WooCommercePaymentTokens;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\CaptureCardPayment;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

class CreditCardGatewayTest extends TestCase
{
	private $orderProcessor;
	private $config;
	private $dcc_configuration;
	private $creditCardIcons;
	private $moduleUrl;
	private $sessionHandler;
	private $refundProcessor;
	private $transactionUrlProvider;
	private $subscriptionHelper;
	private $captureCardPayment;
	private $wcPaymentTokens;
	private $logger;
	private $paymentsEndpoint;
	private $environment;
	private $orderEndpoint;
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		$this->orderProcessor = Mockery::mock(OrderProcessor::class);
		$this->config = Mockery::mock(ContainerInterface::class);
		$this->dcc_configuration = Mockery::mock(CardPaymentsConfiguration::class);
		$this->creditCardIcons = [];
		$this->sessionHandler = Mockery::mock(SessionHandler::class);
		$this->refundProcessor = Mockery::mock(RefundProcessor::class);
		$this->transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
		$this->subscriptionHelper = Mockery::mock(SubscriptionHelper::class);
		$this->captureCardPayment = Mockery::mock(CaptureCardPayment::class);
		$this->wcPaymentTokens = Mockery::mock(WooCommercePaymentTokens::class);
		$this->logger = Mockery::mock(LoggerInterface::class);
		$this->paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
		$this->environment = Mockery::mock(Environment::class);
		$this->orderEndpoint = Mockery::mock(OrderEndpoint::class);

		$this->config->shouldReceive('has')->andReturn(true);
		$this->config->shouldReceive('get')->andReturn('');

		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('gateway_title')->andReturn('');
		$this->dcc_configuration->shouldReceive('gateway_description')->andReturn('');

		when('wc_clean')->returnArg();

		$this->testee = new CreditCardGateway(
			$this->orderProcessor,
			$this->config,
			$this->dcc_configuration,
			$this->creditCardIcons,
			$this->sessionHandler,
			$this->refundProcessor,
			$this->transactionUrlProvider,
			$this->subscriptionHelper,
			$this->paymentsEndpoint,
			$this->environment,
			$this->orderEndpoint,
			$this->captureCardPayment,
			$this->wcPaymentTokens,
			$this->logger
		);
	}

	public function testProcessPayment()
	{
		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->shouldReceive('get_meta')->andReturn('');
		when('wc_get_order')->justReturn($wc_order);

		$woocommerce = Mockery::mock(\WooCommerce::class);
		$session = Mockery::mock(\WC_Session::class);
		when('WC')->justReturn($woocommerce);
		$woocommerce->session = $session;
		$session->shouldReceive('set')->andReturn([]);
		$session->shouldReceive('get')->andReturn('');

		$this->orderProcessor->shouldReceive('process')
			->with($wc_order)
			->andReturn(true);
		$this->subscriptionHelper->shouldReceive('has_subscription')
			->andReturn(false);
		$this->sessionHandler->shouldReceive('destroy_session_data')->once();

		$result = $this->testee->process_payment(1);
		$this->assertEquals('success', $result['result']);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @scenario Current user changes their subscription payment to a token they own.
	 * The token is attached to the order and the change succeeds.
	 */
	public function testProcessPaymentAttachesTokenWhenOwnershipCheckPasses()
	{
		$this->enterSubscriptionChangePaymentContext('99');

		$token = Mockery::mock('alias:WC_Payment_Tokens');
		$payment_token = Mockery::mock(WC_Payment_Token::class);
		$payment_token->shouldReceive('get_user_id')->andReturn(1);
		$token->shouldReceive('get')->with(99)->andReturn($payment_token);

		when('get_current_user_id')->justReturn(1);

		$wc_order = $this->subscriptionChangeOrder();
		$wc_order->shouldReceive('add_payment_token')->once()->with($payment_token);
		$wc_order->shouldReceive('save')->once();
		$this->sessionHandler->shouldReceive('destroy_session_data')->once();

		$result = $this->subscriptionChangeGateway()->process_payment(1);

		$this->assertEquals('success', $result['result']);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @scenario Current user supplies a token id belonging to a different user while
	 * changing their subscription payment. The token must not be attached and the
	 * change must fail.
	 */
	public function testProcessPaymentRejectsTokenOwnedByDifferentUser()
	{
		$this->enterSubscriptionChangePaymentContext('99');

		$token = Mockery::mock('alias:WC_Payment_Tokens');
		$payment_token = Mockery::mock(WC_Payment_Token::class);
		$payment_token->shouldReceive('get_user_id')->andReturn(2);
		$token->shouldReceive('get')->with(99)->andReturn($payment_token);

		when('get_current_user_id')->justReturn(1);
		when('wc_add_notice')->justReturn(null);
		when('wc_get_checkout_url')->justReturn('http://example.test/checkout');

		$wc_order = $this->subscriptionChangeOrder();
		$wc_order->shouldNotReceive('add_payment_token');
		$wc_order->shouldNotReceive('save');
		$this->sessionHandler->shouldNotReceive('destroy_session_data');

		$result = $this->subscriptionChangeGateway()->process_payment(1);

		$this->assertEquals('failure', $result['result']);
	}

	/**
	 * GIVEN a guest free-trial marker staged in the session
	 * WHEN process_payment() runs for an order whose total is not zero
	 * THEN the order is not marked paid
	 * AND normal order processing runs instead of the free-trial shortcut
	 */
	public function testProcessPaymentDoesNotMarkPaidWhenGuestMarkerStagedButOrderIsNotFreeTrial()
	{
		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->shouldReceive('get_total')->with('numeric')->andReturn(19.99);
		$wc_order->shouldReceive('get_meta')->andReturn('');
		$wc_order->shouldNotReceive('payment_complete');
		when('wc_get_order')->justReturn($wc_order);

		when('wcs_get_subscriptions_for_order')->justReturn([(object) []]);

		$store = [
			'ppcp_guest_payment_for_free_trial' => (object) [
				'customer'       => (object) ['id' => 'CUST-1'],
				'payment_source' => (object) ['card' => (object) []],
			],
		];
		$this->stubStatefulSession($store);

		$this->orderProcessor->shouldReceive('process')->with($wc_order)->once()->andReturn(true);
		$this->sessionHandler->shouldReceive('destroy_session_data')->once();

		$result = $this->freeTrialGateway()->process_payment(1);

		$this->assertEquals('success', $result['result']);
	}

	/**
	 * GIVEN a guest free-trial marker staged in the session
	 * WHEN process_payment() runs for a zero-total free-trial order
	 * THEN the order is marked paid
	 * AND the marker is consumed, so a second order in the same session is not marked paid
	 */
	public function testProcessPaymentMarksPaidAndConsumesGuestMarkerForFreeTrialOrder()
	{
		$order1 = Mockery::mock(WC_Order::class);
		$order1->shouldReceive('get_total')->with('numeric')->andReturn(0.0);
		$order1->shouldReceive('get_customer_id')->andReturn(5);
		$order1->shouldReceive('payment_complete')->once();

		$order2 = Mockery::mock(WC_Order::class);
		$order2->shouldReceive('get_total')->with('numeric')->andReturn(0.0);
		$order2->shouldReceive('get_meta')->andReturn('');
		$order2->shouldNotReceive('payment_complete');

		$orders = [$order1, $order2];
		when('wc_get_order')->alias(function () use (&$orders) {
			return array_shift($orders);
		});

		when('wcs_get_subscriptions_for_order')->justReturn([(object) []]);
		when('update_user_meta')->justReturn(true);

		$store = [
			'ppcp_guest_payment_for_free_trial' => (object) [
				'customer'       => (object) ['id' => 'CUST-1'],
				'payment_source' => (object) ['card' => (object) []],
			],
		];
		$this->stubStatefulSession($store);

		$this->wcPaymentTokens->shouldReceive('create_payment_token_card')->once();
		$this->orderProcessor->shouldReceive('process')->with($order2)->once()->andReturn(true);
		$this->sessionHandler->shouldReceive('destroy_session_data')->twice();

		$gateway = $this->freeTrialGateway();

		$result1 = $gateway->process_payment(1);
		$result2 = $gateway->process_payment(2);

		$this->assertEquals('success', $result1['result']);
		$this->assertEquals('success', $result2['result']);
		$this->assertNull($store['ppcp_guest_payment_for_free_trial']);
	}

	/**
	 * GIVEN an authenticated free-trial marker staged in the session
	 * WHEN process_payment() runs for an order whose total is not zero
	 * THEN the order is not marked paid
	 */
	public function testProcessPaymentDoesNotMarkPaidWhenAuthenticatedMarkerStagedButOrderIsNotFreeTrial()
	{
		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->shouldReceive('get_total')->with('numeric')->andReturn(19.99);
		$wc_order->shouldReceive('get_meta')->andReturn('');
		$wc_order->shouldNotReceive('payment_complete');
		$wc_order->shouldNotReceive('add_payment_token');
		when('wc_get_order')->justReturn($wc_order);

		when('wcs_get_subscriptions_for_order')->justReturn([(object) []]);
		when('get_current_user_id')->justReturn(1);

		$store = ['ppcp_card_payment_token_for_free_trial' => 42];
		$this->stubStatefulSession($store);

		$this->orderProcessor->shouldReceive('process')->with($wc_order)->once()->andReturn(true);
		$this->sessionHandler->shouldReceive('destroy_session_data')->once();

		$result = $this->freeTrialGateway()->process_payment(1);

		$this->assertEquals('success', $result['result']);
	}

	/**
	 * GIVEN a card-token free-trial marker staged in the session
	 * WHEN process_payment() runs for a zero-total free-trial order
	 * THEN the order is marked paid using the matching saved token
	 * AND the marker is consumed, so a second order in the same session is not marked paid
	 */
	public function testProcessPaymentMarksPaidAndConsumesTokenMarkerForFreeTrialOrder()
	{
		$token = Mockery::mock(WC_Payment_Token::class);
		$token->shouldReceive('get_id')->andReturn(42);
		$token_class = Mockery::mock('alias:WC_Payment_Tokens');
		$token_class->shouldReceive('get_customer_tokens')->andReturn([$token]);

		$order1 = Mockery::mock(WC_Order::class);
		$order1->shouldReceive('get_total')->with('numeric')->andReturn(0.0);
		$order1->shouldReceive('get_meta')->andReturn('');
		$order1->shouldReceive('payment_complete')->once();
		$order1->shouldReceive('add_payment_token')->once()->with($token);

		$order2 = Mockery::mock(WC_Order::class);
		$order2->shouldReceive('get_total')->with('numeric')->andReturn(0.0);
		$order2->shouldReceive('get_meta')->andReturn('');
		$order2->shouldNotReceive('payment_complete');
		$order2->shouldNotReceive('add_payment_token');

		$orders = [$order1, $order2];
		when('wc_get_order')->alias(function () use (&$orders) {
			return array_shift($orders);
		});

		when('wcs_get_subscriptions_for_order')->justReturn([(object) []]);
		when('get_current_user_id')->justReturn(1);

		$store = ['ppcp_card_payment_token_for_free_trial' => 42];
		$this->stubStatefulSession($store);

		$this->orderProcessor->shouldReceive('process')->with($order2)->once()->andReturn(true);
		$this->sessionHandler->shouldReceive('destroy_session_data')->twice();

		$gateway = $this->freeTrialGateway();

		$result1 = $gateway->process_payment(1);
		$result2 = $gateway->process_payment(2);

		$this->assertEquals('success', $result1['result']);
		$this->assertEquals('success', $result2['result']);
		$this->assertNull($store['ppcp_card_payment_token_for_free_trial']);
	}

	/**
	 * GIVEN a guest free-trial marker left over from an earlier checkout attempt
	 * WHEN process_payment() runs for an order whose total is not zero
	 * THEN the marker is consumed even though this order is not a free trial
	 * AND a later free-trial order in the same session cannot reuse the stale marker
	 */
	public function testProcessPaymentConsumesGuestMarkerEvenWhenOrderIsNotFreeTrial()
	{
		$order1 = Mockery::mock(WC_Order::class);
		$order1->shouldReceive('get_total')->with('numeric')->andReturn(19.99);
		$order1->shouldReceive('get_meta')->andReturn('');
		$order1->shouldNotReceive('payment_complete');

		$order2 = Mockery::mock(WC_Order::class);
		$order2->shouldReceive('get_total')->with('numeric')->andReturn(0.0);
		$order2->shouldReceive('get_meta')->andReturn('');
		$order2->shouldNotReceive('payment_complete');

		$orders = [$order1, $order2];
		when('wc_get_order')->alias(function () use (&$orders) {
			return array_shift($orders);
		});

		when('wcs_get_subscriptions_for_order')->justReturn([(object) []]);

		$store = [
			'ppcp_guest_payment_for_free_trial' => (object) [
				'customer'       => (object) ['id' => 'CUST-1'],
				'payment_source' => (object) ['card' => (object) []],
			],
		];
		$this->stubStatefulSession($store);

		$this->orderProcessor->shouldReceive('process')->with($order1)->once()->andReturn(true);
		$this->orderProcessor->shouldReceive('process')->with($order2)->once()->andReturn(true);
		$this->sessionHandler->shouldReceive('destroy_session_data')->twice();

		$gateway = $this->freeTrialGateway();

		$result1 = $gateway->process_payment(1);
		$this->assertNull($store['ppcp_guest_payment_for_free_trial']);

		$result2 = $gateway->process_payment(2);

		$this->assertEquals('success', $result1['result']);
		$this->assertEquals('success', $result2['result']);
	}

	/**
	 * GIVEN a card-token free-trial marker left over from an earlier checkout attempt
	 * WHEN process_payment() runs for an order whose total is not zero
	 * THEN the marker is consumed even though this order is not a free trial
	 * AND a later free-trial order in the same session cannot reuse the stale marker
	 */
	public function testProcessPaymentConsumesTokenMarkerEvenWhenOrderIsNotFreeTrial()
	{
		$order1 = Mockery::mock(WC_Order::class);
		$order1->shouldReceive('get_total')->with('numeric')->andReturn(19.99);
		$order1->shouldReceive('get_meta')->andReturn('');
		$order1->shouldNotReceive('payment_complete');
		$order1->shouldNotReceive('add_payment_token');

		$order2 = Mockery::mock(WC_Order::class);
		$order2->shouldReceive('get_total')->with('numeric')->andReturn(0.0);
		$order2->shouldReceive('get_meta')->andReturn('');
		$order2->shouldNotReceive('payment_complete');
		$order2->shouldNotReceive('add_payment_token');

		$orders = [$order1, $order2];
		when('wc_get_order')->alias(function () use (&$orders) {
			return array_shift($orders);
		});

		when('wcs_get_subscriptions_for_order')->justReturn([(object) []]);
		when('get_current_user_id')->justReturn(1);

		$store = ['ppcp_card_payment_token_for_free_trial' => 42];
		$this->stubStatefulSession($store);

		$this->orderProcessor->shouldReceive('process')->with($order1)->once()->andReturn(true);
		$this->orderProcessor->shouldReceive('process')->with($order2)->once()->andReturn(true);
		$this->sessionHandler->shouldReceive('destroy_session_data')->twice();

		$gateway = $this->freeTrialGateway();

		$result1 = $gateway->process_payment(1);
		$this->assertNull($store['ppcp_card_payment_token_for_free_trial']);

		$result2 = $gateway->process_payment(2);

		$this->assertEquals('success', $result1['result']);
		$this->assertEquals('success', $result2['result']);
	}

	/**
	 * Stubs WC()->session with a get()/set() pair backed by the given array,
	 * so a marker written by set() is visible to a later get() in the same test.
	 */
	private function stubStatefulSession(array &$store): void
	{
		$woocommerce = Mockery::mock(\WooCommerce::class);
		$session = Mockery::mock(\WC_Session::class);
		when('WC')->justReturn($woocommerce);
		$woocommerce->session = $session;

		$session->shouldReceive('get')->andReturnUsing(static function (string $key) use (&$store) {
			return $store[$key] ?? null;
		});
		$session->shouldReceive('set')->andReturnUsing(static function (string $key, $value) use (&$store) {
			$store[$key] = $value;
		});
	}

	/**
	 * Builds a CreditCardGateway whose is_free_trial_order() check treats
	 * WooCommerce Subscriptions as active, regardless of whether the real
	 * WC_Subscriptions class is loaded in the test environment.
	 */
	private function freeTrialGateway(): CreditCardGateway
	{
		return new CreditCardGatewayFreeTrialStub(
			$this->orderProcessor,
			$this->config,
			$this->dcc_configuration,
			$this->creditCardIcons,
			$this->sessionHandler,
			$this->refundProcessor,
			$this->transactionUrlProvider,
			$this->subscriptionHelper,
			$this->paymentsEndpoint,
			$this->environment,
			$this->orderEndpoint,
			$this->captureCardPayment,
			$this->wcPaymentTokens,
			$this->logger
		);
	}

	/**
	 * Sets up the request/session state required to reach the subscription
	 * change-payment branch of process_payment().
	 */
	private function enterSubscriptionChangePaymentContext(string $token_id): void
	{
		$_POST['woocommerce_change_payment'] = '1';
		$_POST['wc-ppcp-credit-card-gateway-payment-token'] = $token_id;

		$woocommerce = Mockery::mock(\WooCommerce::class);
		$session = Mockery::mock(\WC_Session::class);
		when('WC')->justReturn($woocommerce);
		$woocommerce->session = $session;
		$session->shouldReceive('set')->andReturn([]);
		$session->shouldReceive('get')->andReturn('');

		$this->subscriptionHelper->shouldReceive('has_subscription')->andReturn(true);
		$this->subscriptionHelper->shouldReceive('is_subscription_change_payment')->andReturn(true);
	}

	/**
	 * Builds a CreditCardGateway whose get_return_url() returns a string. The
	 * shared WC_Payment_Gateway stub returns the order object, which would
	 * violate add_payment_token_to_order()'s string type hint.
	 */
	private function subscriptionChangeGateway(): CreditCardGateway
	{
		return new CreditCardGatewayReturnUrlStub(
			$this->orderProcessor,
			$this->config,
			$this->dcc_configuration,
			$this->creditCardIcons,
			$this->sessionHandler,
			$this->refundProcessor,
			$this->transactionUrlProvider,
			$this->subscriptionHelper,
			$this->paymentsEndpoint,
			$this->environment,
			$this->orderEndpoint,
			$this->captureCardPayment,
			$this->wcPaymentTokens,
			$this->logger
		);
	}

	/**
	 * Builds the WC_Order mock used in the subscription change-payment tests and
	 * wires it to wc_get_order().
	 */
	private function subscriptionChangeOrder()
	{
		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->shouldReceive('get_id')->andReturn(1);
		$wc_order->shouldReceive('get_meta')->andReturn('');
		$wc_order->shouldReceive('get_total')->andReturn(10);
		when('wc_get_order')->justReturn($wc_order);

		return $wc_order;
	}

}

/**
 * Test double that returns a string return URL. The shared WC_Payment_Gateway
 * stub returns the order object, which would violate the string type hint of
 * CreditCardGateway::add_payment_token_to_order().
 */
class CreditCardGatewayReturnUrlStub extends CreditCardGateway
{
	protected function get_return_url( $order = null ) {
		return 'http://example.test/return';
	}
}

/**
 * Test double that treats WooCommerce Subscriptions as active, so
 * is_free_trial_order() is driven purely by the order total and the
 * staged subscriptions rather than by the real WC_Subscriptions class.
 */
class CreditCardGatewayFreeTrialStub extends CreditCardGateway
{
	protected function is_wcs_plugin_active(): bool {
		return true;
	}
}
