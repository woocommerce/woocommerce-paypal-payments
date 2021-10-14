<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;


use Psr\Log\NullLogger;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Mockery;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;

class AuthorizedPaymentsProcessorTest extends TestCase
{
	private $wcOrder;
	private $paypalOrderId = 'abc';
	private $authorizationId = 'qwe';
	private $paypalOrder;
	private $orderEndpoint;
	private $paymentsEndpoint;
	private $notice;
	private $testee;

	public function setUp(): void {
		parent::setUp();

		$this->wcOrder = $this->createWcOrder($this->paypalOrderId);

		$this->paypalOrder = $this->createPaypalOrder([$this->createAuthorization($this->authorizationId, AuthorizationStatus::CREATED)]);

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

		$this->testee = new AuthorizedPaymentsProcessor(
			$this->orderEndpoint,
			$this->paymentsEndpoint,
			new NullLogger(),
			$this->notice
		);
	}

	public function testSuccess() {
		$this->paymentsEndpoint
			->expects('capture')
			->with($this->authorizationId)
			->andReturn($this->createCapture(CaptureStatus::COMPLETED));

        $this->assertEquals(AuthorizedPaymentsProcessor::SUCCESSFUL, $this->testee->process($this->wcOrder));
    }

	public function testCapturesAllCaptureable() {
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

		foreach ([$authorizations[0], $authorizations[2]] as $authorization) {
			$this->paymentsEndpoint
				->expects('capture')
				->with($authorization->id())
				->andReturn($this->createCapture(CaptureStatus::COMPLETED));
		}

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
            ->with($this->authorizationId)
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
			->with($this->authorizationId)
			->andReturn($this->createCapture(CaptureStatus::COMPLETED));

		$this->wcOrder->shouldReceive('payment_complete')->andReturn(true);
		$this->wcOrder->expects('add_order_note');
		$this->wcOrder->expects('update_meta_data');
		$this->wcOrder->expects('save');

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

	private function createWcOrder(string $paypalOrderId): WC_Order {
		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder
			->shouldReceive('get_meta')
			->with(PayPalGateway::ORDER_ID_META_KEY)
			->andReturn($paypalOrderId);
		return $wcOrder;
	}

	private function createAuthorization(string $id, string $status): Authorization {
		$authorization = Mockery::mock(Authorization::class);
		$authorization
			->shouldReceive('id')
			->andReturn($id);
		$authorization
			->shouldReceive('status')
			->andReturn(new AuthorizationStatus($status));
		return $authorization;
	}

	private function createCapture(string $status): Capture {
		$capture = Mockery::mock(Capture::class);
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
