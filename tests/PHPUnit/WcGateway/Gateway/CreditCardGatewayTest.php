<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Mockery;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vaulting\VaultedCreditCardHandler;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use function Brain\Monkey\Functions\when;

class CreditCardGatewayTest extends TestCase
{
	private $settingsRenderer;
	private $orderProcessor;
	private $config;
	private $moduleUrl;
	private $sessionHandler;
	private $refundProcessor;
	private $state;
	private $transactionUrlProvider;
	private $subscriptionHelper;
	private $logger;
	private $paymentsEndpoint;
	private $vaultedCreditCardHandler;
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		$this->settingsRenderer = Mockery::mock(SettingsRenderer::class);
		$this->orderProcessor = Mockery::mock(OrderProcessor::class);
		$this->config = Mockery::mock(ContainerInterface::class);
		$this->moduleUrl = '';
		$this->sessionHandler = Mockery::mock(SessionHandler::class);
		$this->refundProcessor = Mockery::mock(RefundProcessor::class);
		$this->state = Mockery::mock(State::class);
		$this->transactionUrlProvider = Mockery::mock(TransactionUrlProvider::class);
		$this->subscriptionHelper = Mockery::mock(SubscriptionHelper::class);
		$this->logger = Mockery::mock(LoggerInterface::class);
		$this->paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);
		$this->vaultedCreditCardHandler = Mockery::mock(VaultedCreditCardHandler::class);

		$this->state->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);
		$this->config->shouldReceive('has')->andReturn(true);
		$this->config->shouldReceive('get')->andReturn('');

		$this->testee = new CreditCardGateway(
			$this->settingsRenderer,
			$this->orderProcessor,
			$this->config,
			$this->moduleUrl,
			$this->sessionHandler,
			$this->refundProcessor,
			$this->state,
			$this->transactionUrlProvider,
			$this->subscriptionHelper,
			$this->logger,
			$this->paymentsEndpoint,
			$this->vaultedCreditCardHandler
		);
	}

	public function testProcessPayment()
	{
		$wc_order = Mockery::mock(WC_Order::class);
		when('wc_get_order')->justReturn($wc_order);

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
		when('wc_get_order')->justReturn($wc_order);

		$savedCreditCard = 'abc123';
		when('filter_input')->justReturn($savedCreditCard);

		$this->vaultedCreditCardHandler
			->shouldReceive('handle_payment')
			->with($savedCreditCard, $wc_order)
			->andReturn($wc_order);

		$this->sessionHandler->shouldReceive('destroy_session_data')->once();

		$result = $this->testee->process_payment(1);
		$this->assertEquals('success', $result['result']);
	}
}
