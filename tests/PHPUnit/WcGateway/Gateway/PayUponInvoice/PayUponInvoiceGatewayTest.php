<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PayUponInvoiceOrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CheckoutHelper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceHelper;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use function Brain\Monkey\Functions\when;

class PayUponInvoiceGatewayTest extends TestCase
{
	private $order_endpoint;
	private $purchase_unit_factory;
	private $payment_source_factory;
	private $environment;
	private $transaction_url_provider;
	private $logger;
	private $testee;
	private $pui_helper;
	private $checkout_helper;
	private $state;
	private $refund_processor;

	public function setUp(): void
	{
		parent::setUp();

		$this->order_endpoint = Mockery::mock(PayUponInvoiceOrderEndpoint::class);
		$this->purchase_unit_factory = Mockery::mock(PurchaseUnitFactory::class);
		$this->payment_source_factory = Mockery::mock(PaymentSourceFactory::class);
		$this->environment = Mockery::mock(Environment::class);
		$this->logger = Mockery::mock(LoggerInterface::class);
		$this->transaction_url_provider = Mockery::mock(TransactionUrlProvider::class);
		$this->pui_helper = Mockery::mock(PayUponInvoiceHelper::class);
		$this->checkout_helper = Mockery::mock(CheckoutHelper::class);

		$this->state = Mockery::mock(State::class);
		$this->state->shouldReceive('current_state')->andReturn(State::STATE_ONBOARDED);

		$this->refund_processor = Mockery::mock(RefundProcessor::class);

		$this->setInitStubs();
		when('wc_clean')->returnArg();

		$this->testee = new PayUponInvoiceGateway(
			$this->order_endpoint,
			$this->purchase_unit_factory,
			$this->payment_source_factory,
			$this->environment,
			$this->transaction_url_provider,
			$this->logger,
			$this->pui_helper,
			$this->checkout_helper,
			$this->state,
			$this->refund_processor,
			''
		);
	}

	public function testProcessPayment()
	{
		$this->markTestSkipped('must be revisited.');
		list($order, $purchase_unit, $payment_source) = $this->setTestStubs();

		$this->order_endpoint->shouldReceive('create')->with(
			[$purchase_unit],
			$payment_source
		)->andReturn($order);

		define( 'MINUTE_IN_SECONDS', 60 );
		when('as_schedule_single_action')->justReturn();

		$result = $this->testee->process_payment(1);
		$this->assertEquals('success', $result['result']);
	}

	public function testProcessPaymentError()
	{
		list($order, $purchase_unit, $payment_source) = $this->setTestStubs();

		$this->logger->shouldReceive('error');
		when('wc_add_notice')->justReturn();
		when('wc_get_checkout_url')->justReturn();

		$this->order_endpoint->shouldReceive('create')->with(
			[$purchase_unit],
			$payment_source,
			''
		)->andThrows(\RuntimeException::class);

		$result = $this->testee->process_payment(1);
		$this->assertEquals('failure', $result['result']);
	}

	private function setInitStubs(): void
	{
		when('get_option')->justReturn([
			'title' => 'foo',
			'description' => 'bar',
		]);
		when('get_bloginfo')->justReturn('Foo');

		$woocommerce = Mockery::mock(\WooCommerce::class);
		$cart = Mockery::mock(\WC_Cart::class);
		when('WC')->justReturn($woocommerce);
		$woocommerce->cart = $cart;
		$cart->shouldReceive('empty_cart');
	}

	/**
	 * @return array
	 */
	private function setTestStubs(): array
	{
		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder->shouldReceive('update_meta_data');
		$wcOrder->shouldReceive('update_status');
		$wcOrder->shouldReceive('save');
		when('wc_get_order')->justReturn($wcOrder);

		$order = Mockery::mock(Order::class);
		$order->shouldReceive('id')->andReturn('1');
		$order->shouldReceive('intent')->andReturn('CAPTURE');
		$order->shouldReceive('payment_source')->andReturn('');

		$purchase_unit = Mockery::mock(PurchaseUnit::class);
		$payment_source = Mockery::mock(PaymentSource::class);

		$this->payment_source_factory->shouldReceive('from_wc_order')
			->with($wcOrder, '')
			->andReturn($payment_source);

		$this->purchase_unit_factory->shouldReceive('from_wc_order')
			->with($wcOrder)
			->andReturn($purchase_unit);

		$this->environment->shouldReceive('current_environment_is')->andReturn(true);

		return array($order, $purchase_unit, $payment_source);
	}
}
