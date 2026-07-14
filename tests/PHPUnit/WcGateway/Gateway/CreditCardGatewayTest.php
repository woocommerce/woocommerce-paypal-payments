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
