<?php

namespace PHPUnit\Vaulting;

use Mockery;
use Psr\Container\ContainerInterface;
use WC_Customer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSourceCard;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\Vaulting\VaultedCreditCardHandler;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class VaultedCreditCardHandlerTest extends TestCase
{
	private $subscriptionHelper;
	private $paymentTokenRepository;
	private $purchaseUnitFactory;
	private $payerFactory;
	private $shippingPreferenceFactory;
	private $orderEndpoint;
	private $environment;
	private $authorizedPaymentProcessor;
	private $config;
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		$this->subscriptionHelper = Mockery::mock(SubscriptionHelper::class);
		$this->paymentTokenRepository = Mockery::mock(PaymentTokenRepository::class);
		$this->purchaseUnitFactory = Mockery::mock(PurchaseUnitFactory::class);
		$this->payerFactory = Mockery::mock(PayerFactory::class);
		$this->shippingPreferenceFactory = Mockery::mock(ShippingPreferenceFactory::class);
		$this->orderEndpoint = Mockery::mock(OrderEndpoint::class);
		$this->environment = Mockery::mock(Environment::class);
		$this->authorizedPaymentProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);
		$this->config = Mockery::mock(ContainerInterface::class);

		$this->testee = new VaultedCreditCardHandler(
			$this->subscriptionHelper,
			$this->paymentTokenRepository,
			$this->purchaseUnitFactory,
			$this->payerFactory,
			$this->shippingPreferenceFactory,
			$this->orderEndpoint,
			$this->environment,
			$this->authorizedPaymentProcessor,
			$this->config
		);
	}

	public function testHandlePaymentChangingPayment()
	{
		when('filter_input')->justReturn(1);
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->shouldReceive('get_id')->andReturn(1);
		$this->subscriptionHelper->shouldReceive('has_subscription')->andReturn(true);
		$this->subscriptionHelper->shouldReceive('is_subscription_change_payment')->andReturn(true);
		expect('update_post_meta')->with(1, 'payment_token_id', 'abc123');

		$customer = Mockery::mock(WC_Customer::class);

		$result = $this->testee->handle_payment('abc123', $wcOrder, $customer);
		$this->assertInstanceOf(\WC_Order::class, $result);
	}

	public function testHandlePayment()
	{
		$wcOrder = Mockery::mock(\WC_Order::class);
		$wcOrder->shouldReceive('get_id')->andReturn(1);
		$wcOrder->shouldReceive('get_customer_id')->andReturn(1);
		$wcOrder->shouldReceive('update_meta_data')->andReturn(1);
		$wcOrder->shouldReceive('save')->once();
		$wcOrder->shouldReceive('payment_complete')->andReturn(true);

		$token = Mockery::mock(PaymentToken::class);
		$tokenId = 'abc123';
		$token->shouldReceive('id')->andReturn($tokenId);
		$this->paymentTokenRepository->shouldReceive('all_for_user_id')
			->andReturn([$token]);

		$purchaseUnit = Mockery::mock(PurchaseUnit::class);
		$this->purchaseUnitFactory->shouldReceive('from_wc_order')
			->andReturn($purchaseUnit);

		$customer = Mockery::mock(WC_Customer::class);

		$payer = Mockery::mock(Payer::class);
		$this->payerFactory->shouldReceive('from_customer')
			->andReturn($payer);
		$this->shippingPreferenceFactory->shouldReceive('from_state')
			->andReturn('some_preference');

		$order = Mockery::mock(Order::class);
		$order->shouldReceive('id')->andReturn('1');
		$order->shouldReceive('intent')->andReturn('CAPTURE');
		$paymentSource = Mockery::mock(PaymentSource::class);
		$paymentSourceCard = Mockery::mock(PaymentSourceCard::class);
		$paymentSource->shouldReceive('card')->andReturn($paymentSourceCard);
		$order->shouldReceive('payment_source')->andReturn($paymentSource);
		$orderStatus = Mockery::mock(OrderStatus::class);
		$orderStatus->shouldReceive('is')->andReturn(true);
		$order->shouldReceive('status')->andReturn($orderStatus);

		$order->shouldReceive('purchase_units')->andReturn([$purchaseUnit]);
		$payments = Mockery::mock(Payments::class);
		$capture = Mockery::mock(Capture::class);
		$capture->shouldReceive('id')->andReturn('1');
		$captureStatus = Mockery::mock(CaptureStatus::class);
		$captureStatus->shouldReceive('details')->andReturn(null);
		$captureStatus->shouldReceive('name')->andReturn(CaptureStatus::COMPLETED);
		$capture->shouldReceive('status')->andReturn($captureStatus);
		$payments->shouldReceive('captures')->andReturn([$capture]);
		$purchaseUnit->shouldReceive('payments')->andReturn($payments);

		$this->orderEndpoint->shouldReceive('create')
			->with([$purchaseUnit], 'some_preference', $payer, $token)
			->andReturn($order);

		$this->environment->shouldReceive('current_environment_is')->andReturn(true);

		$this->config->shouldReceive('has')->andReturn(false);

		$result = $this->testee->handle_payment($tokenId, $wcOrder, $customer);
		$this->assertInstanceOf(\WC_Order::class, $result);
	}
}
