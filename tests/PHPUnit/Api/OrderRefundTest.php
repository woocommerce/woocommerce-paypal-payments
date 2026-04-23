<?php

namespace WooCommerce\PayPalCommerce\Api;

use InvalidArgumentException;
use Mockery;
use RuntimeException;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ModularTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;

class OrderRefundTest extends ModularTestCase
{
	private $refundProcessor;

	private $orderEndpoint;

	public function setUp(): void {
		parent::setUp();

		$this->refundProcessor = Mockery::mock(RefundProcessor::class);
		$this->orderEndpoint = Mockery::mock(OrderEndpoint::class);

		$this->bootstrapModule([
			'wcgateway.processor.refunds' => function () {
				return $this->refundProcessor;
			},
			'api.endpoint.order' => function () {
				return $this->orderEndpoint;
			},
		]);
	}

	public function testSuccess(): void {
		$wcOrder = Mockery::mock(WC_Order::class);

		$this->orderEndpoint
			->expects('order')
			->with($wcOrder)
			->andReturn(Mockery::mock(Order::class))
			->once();

		$this->refundProcessor
			->expects('refund')
			->andReturn('456qwe')
			->once();

		$refund_id = ppcp_refund_order($wcOrder, 42.0, 'reason');
		$this->assertEquals('456qwe', $refund_id);
	}

	public function testOrderWithoutId(): void {
		$wcOrder = Mockery::mock(WC_Order::class);

		$this->orderEndpoint
			->expects('order')
			->with($wcOrder)
			->andThrow(new InvalidArgumentException())
			->once();

		$this->expectException(InvalidArgumentException::class);

		ppcp_refund_order($wcOrder, 42.0, 'reason');
	}

	public function testFailure(): void {
		$wcOrder = Mockery::mock(WC_Order::class);

		$this->orderEndpoint
			->expects('order')
			->with($wcOrder)
			->andReturn(Mockery::mock(Order::class))
			->once();

		$this->refundProcessor
			->expects('refund')
			->andThrow(new RuntimeException())
			->once();

		$this->expectException(RuntimeException::class);

		ppcp_refund_order($wcOrder, 42.0, 'reason');
	}
}
