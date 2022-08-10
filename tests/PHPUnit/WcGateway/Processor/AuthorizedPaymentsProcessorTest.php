<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;


use Mockery\MockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Mockery;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;

class AuthorizedPaymentsProcessorTest extends TestCase
{
	/**
	 * @var WC_Order&MockInterface
	 */
	private $wcOrder;

	private $paypalOrderId = 'abc';
	private $authorizationId = 'qwe';
	private $amount = 42.0;
	private $currency = 'EUR';
	private $paypalOrder;
	private $authorization;
	private $orderEndpoint;
	private $paymentsEndpoint;
	private $notice;
	private $config;
	private $captureId = '123qwe';
	private $testee;

	public function setUp(): void {
		parent::setUp();

		$this->wcOrder = $this->createWcOrder($this->paypalOrderId);

		$this->authorization = $this->createAuthorization($this->authorizationId, AuthorizationStatus::CREATED);
		$this->paypalOrder = $this->createPaypalOrder([$this->authorization]);

		$this->orderEndpoint = Mockery::mock(OrderEndpoint::class);
		$this->orderEndpoint
			->shouldReceive('order')
			->with($this->paypalOrderId)
			->andReturnUsing(function () {
				return $this->paypalOrder;
			})
			->byDefault();

		$this->paymentsEndpoint = Mockery::mock(PaymentsEndpoint::class);

		$this->notice = Mockery::mock(AuthorizeOrderActionNotice::class);
		$this->notice->shouldReceive('display_message');

		$this->config = Mockery::mock(ContainerInterface::class);
		$this->subscription_helper = Mockery::mock(SubscriptionHelper::class);

		$this->testee = new AuthorizedPaymentsProcessor(
			$this->orderEndpoint,
			$this->paymentsEndpoint,
			new NullLogger(),
			$this->notice,
			$this->config,
			$this->subscription_helper
		);
	}

	public function testSuccess() {
		$this->paymentsEndpoint
			->expects('capture')
			->with($this->authorizationId, equalTo(new Money($this->amount, $this->currency)))
			->andReturn($this->createCapture($this->captureId, CaptureStatus::COMPLETED));

        $this->assertEquals(AuthorizedPaymentsProcessor::SUCCESSFUL, $this->testee->process($this->wcOrder));
    }

	public function testCapturesLastCaptureable() {
		$authorizations = [
			$this->createAuthorization('id1', AuthorizationStatus::CREATED),
			$this->createAuthorization('id2', AuthorizationStatus::VOIDED),
			$this->createAuthorization('id3', AuthorizationStatus::PENDING),
			$this->createAuthorization('id4', AuthorizationStatus::CAPTURED),
			$this->createAuthorization('id5', AuthorizationStatus::DENIED),
			$this->createAuthorization('id6', AuthorizationStatus::EXPIRED),
			$this->createAuthorization('id7', AuthorizationStatus::COMPLETED),
		];
		$this->paypalOrder = $this->createPaypalOrder($authorizations);

		$this->paymentsEndpoint
			->expects('capture')
			->with($authorizations[2]->id(), equalTo(new Money($this->amount, $this->currency)))
			->andReturn($this->createCapture($this->captureId, CaptureStatus::COMPLETED));

		$this->assertEquals(AuthorizedPaymentsProcessor::SUCCESSFUL, $this->testee->process($this->wcOrder));
    }

    public function testInaccessible() {
        $this->orderEndpoint
            ->expects('order')
            ->with($this->paypalOrderId)
            ->andThrow(RuntimeException::class);

		$this->assertEquals(AuthorizedPaymentsProcessor::INACCESSIBLE, $this->testee->process($this->wcOrder));
    }

    public function testNotFound() {
        $this->orderEndpoint
            ->expects('order')
            ->with($this->paypalOrderId)
            ->andThrow(new RuntimeException('text', 404));

		$this->assertEquals(AuthorizedPaymentsProcessor::NOT_FOUND, $this->testee->process($this->wcOrder));
    }

    public function testCaptureFails() {
		$this->paymentsEndpoint
            ->expects('capture')
            ->with($this->authorizationId, equalTo(new Money($this->amount, $this->currency)))
            ->andThrow(RuntimeException::class);

		$this->assertEquals(AuthorizedPaymentsProcessor::FAILED, $this->testee->process($this->wcOrder));
    }

    public function testAlreadyCaptured() {
		$this->paypalOrder = $this->createPaypalOrder([$this->createAuthorization($this->authorizationId, AuthorizationStatus::CAPTURED)]);

		$this->assertEquals(AuthorizedPaymentsProcessor::ALREADY_CAPTURED, $this->testee->process($this->wcOrder));
    }

    public function testBadAuthorization() {
		$this->paypalOrder = $this->createPaypalOrder([$this->createAuthorization($this->authorizationId, AuthorizationStatus::DENIED)]);

		$this->assertEquals(AuthorizedPaymentsProcessor::BAD_AUTHORIZATION, $this->testee->process($this->wcOrder));
    }

    public function testCaptureAuthorizedPayment()
	{
		$this->orderEndpoint->shouldReceive('order')->andReturn($this->paypalOrder);

		$this->paymentsEndpoint
			->expects('capture')
			->with($this->authorizationId, equalTo(new Money($this->amount, $this->currency)))
			->andReturn($this->createCapture($this->captureId, CaptureStatus::COMPLETED));

		$this->wcOrder->shouldReceive('payment_complete')->andReturn(true);
		$this->wcOrder->expects('add_order_note')->twice();
		$this->wcOrder->expects('update_meta_data');
		$this->wcOrder->expects('set_transaction_id')->with($this->captureId);
		$this->wcOrder->shouldReceive('save')->atLeast()->times(1);

		$this->assertTrue(
			$this->testee->capture_authorized_payment($this->wcOrder)
		);
	}

	public function testCaptureAuthorizedPaymentAlreadyCaptured()
	{
		$paypalOrder = $this->createPaypalOrder([$this->createAuthorization($this->authorizationId, AuthorizationStatus::CAPTURED)]);
		$this->orderEndpoint->shouldReceive('order')->andReturn($paypalOrder);

		$this->wcOrder->shouldReceive('get_status')->andReturn('on-hold');
		$this->wcOrder->expects('add_order_note');
		$this->wcOrder->expects('update_meta_data');
		$this->wcOrder->expects('save');
		$this->wcOrder->expects('payment_complete');

		$this->assertTrue(
			$this->testee->capture_authorized_payment($this->wcOrder)
		);
	}

	public function testVoid()
	{
		$authorizations = [
			$this->createAuthorization('id1', AuthorizationStatus::CREATED),
			$this->createAuthorization('id2', AuthorizationStatus::VOIDED),
			$this->createAuthorization('id3', AuthorizationStatus::PENDING),
			$this->createAuthorization('id4', AuthorizationStatus::CAPTURED),
			$this->createAuthorization('id5', AuthorizationStatus::DENIED),
			$this->createAuthorization('id6', AuthorizationStatus::EXPIRED),
			$this->createAuthorization('id7', AuthorizationStatus::COMPLETED),
		];
		$this->paypalOrder = $this->createPaypalOrder($authorizations);

		$this->paymentsEndpoint
			->expects('void')
			->with($authorizations[0]);
		$this->paymentsEndpoint
			->expects('void')
			->with($authorizations[2]);

		$this->testee->void_authorizations($this->paypalOrder);

		self::assertTrue(true); // fix no assertions warning
	}

	public function testVoidWhenNoVoidable()
	{
		$exception = new RuntimeException('void error');
		$this->paymentsEndpoint
			->expects('void')
			->with($this->authorization)
			->andThrow($exception);

		$this->expectExceptionObject($exception);

		$this->testee->void_authorizations($this->paypalOrder);
	}

	public function testVoidWhenNoError()
	{
		$authorizations = [
			$this->createAuthorization('id1', AuthorizationStatus::VOIDED),
			$this->createAuthorization('id2', AuthorizationStatus::EXPIRED),
		];
		$this->paypalOrder = $this->createPaypalOrder($authorizations);

		$this->expectException(RuntimeException::class);

		$this->testee->void_authorizations($this->paypalOrder);
	}

	private function createWcOrder(string $paypalOrderId): WC_Order {
		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder
			->shouldReceive('get_meta')
			->with(PayPalGateway::ORDER_ID_META_KEY)
			->andReturn($paypalOrderId);
		$wcOrder
			->shouldReceive('get_total')
			->andReturn($this->amount);
		$wcOrder
			->shouldReceive('get_currency')
			->andReturn($this->currency);
		return $wcOrder;
	}

	private function createAuthorization(string $id, string $status): Authorization {
		return new Authorization($id, new AuthorizationStatus($status));
	}

	private function createCapture(string $id, string $status): Capture {
		$capture = Mockery::mock(Capture::class);
		$capture
			->shouldReceive('id')
			->andReturn($id);
		$capture
			->shouldReceive('status')
			->andReturn(new CaptureStatus($status));
		return $capture;
	}

	private function createPaypalOrder(array $authorizations): Order {
		$payments = Mockery::mock(Payments::class);
		$payments
			->shouldReceive('authorizations')
			->andReturn($authorizations);

		$purchaseUnit = Mockery::mock(PurchaseUnit::class);
		$purchaseUnit
			->shouldReceive('payments')
			->andReturn($payments);

		$order = Mockery::mock(Order::class);
		$order
			->shouldReceive('purchase_units')
			->andReturn([$purchaseUnit]);
		return $order;
	}
}
