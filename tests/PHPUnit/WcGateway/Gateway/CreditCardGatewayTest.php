<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vaulting\VaultedCreditCardHandler;
use WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\CaptureCardPayment;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCGatewayConfiguration;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

class CreditCardGatewayTest extends TestCase
{
	private $settingsRenderer;
	private $orderProcessor;
	private $config;
	private $dcc_configuration;
	private $creditCardIcons;
	private $moduleUrl;
	private $sessionHandler;
	private $refundProcessor;
	private $state;
	private $transactionUrlProvider;
	private $subscriptionHelper;
	private $captureCardPayment;
	private $prefix;
	private $paymentTokensEndpoint;
	private $wcPaymentTokens;
	private $logger;
	private $paymentsEndpoint;
	private $vaultedCreditCardHandler;
	private $environment;
	private $orderEndpoint;
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		$this->settingsRenderer = Mockery::mock(SettingsRenderer::class);
		$this->orderProcessor = Mockery::mock(OrderProcessor::class);
		$this->config = Mockery::mock(ContainerInterface::class);
		$this->dcc_configuration = Mockery::mock(DCCGatewayConfiguration::class);
		$this->creditCardIcons = [];
		$this->moduleUrl = '';
		$this->sessionHandler = Mockery::mock(SessionHandler::class);
		$this->refundProcessor = Mockery::mock(RefundProcessor::class);
		$this->state = Mockery::mock(State::class);
		$this->transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
		$this->subscriptionHelper = Mockery::mock(SubscriptionHelper::class);
		$this->captureCardPayment = Mockery::mock(CaptureCardPayment::class);
		$this->prefix = 'some-prefix';
		$this->paymentTokensEndpoint = Mockery::mock(PaymentTokensEndpoint::class);
		$this->wcPaymentTokens = Mockery::mock(WooCommercePaymentTokens::class);
		$this->logger = Mockery::mock(LoggerInterface::class);
		$this->paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
		$this->vaultedCreditCardHandler = Mockery::mock(VaultedCreditCardHandler::class);
		$this->environment = Mockery::mock(Environment::class);
		$this->orderEndpoint = Mockery::mock(OrderEndpoint::class);

		$this->state->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
		$this->config->shouldReceive('has')->andReturn(true);
		$this->config->shouldReceive('get')->andReturn('');

		$this->dcc_configuration->shouldReceive('is_enabled')->andReturn(true);
		$this->dcc_configuration->shouldReceive('gateway_title')->andReturn('');
		$this->dcc_configuration->shouldReceive('gateway_description')->andReturn('');

		when('wc_clean')->returnArg();

		$this->testee = new CreditCardGateway(
			$this->settingsRenderer,
			$this->orderProcessor,
			$this->config,
			$this->dcc_configuration,
			$this->creditCardIcons,
			$this->moduleUrl,
			$this->sessionHandler,
			$this->refundProcessor,
			$this->state,
			$this->transactionUrlProvider,
			$this->subscriptionHelper,
			$this->paymentsEndpoint,
			$this->vaultedCreditCardHandler,
			$this->environment,
			$this->orderEndpoint,
			$this->captureCardPayment,
			$this->prefix,
			$this->paymentTokensEndpoint,
			$this->wcPaymentTokens,
			$this->logger
		);
	}

	public function testProcessPayment()
	{
		$wc_order = Mockery::mock(WC_Order::class);
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

	public function testProcessPaymentVaultedCard()
	{
		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->shouldReceive('get_customer_id')->andReturn(1);
		when('wc_get_order')->justReturn($wc_order);

		$woocommerce = Mockery::mock(\WooCommerce::class);
		$session = Mockery::mock(\WC_Session::class);
		when('WC')->justReturn($woocommerce);
		$woocommerce->session = $session;
		$session->shouldReceive('set')->andReturn([]);
		$session->shouldReceive('get')->andReturn('');

		when('is_checkout')->justReturn(true);

		$savedCreditCard = 'abc123';
		$_POST['saved_credit_card'] = $savedCreditCard;

		$this->vaultedCreditCardHandler
			->shouldReceive('handle_payment')
			->with($savedCreditCard, $wc_order)
			->andReturn($wc_order);

		$this->sessionHandler->shouldReceive('destroy_session_data')->once();

		$result = $this->testee->process_payment(1);
		$this->assertEquals('success', $result['result']);
	}
}
