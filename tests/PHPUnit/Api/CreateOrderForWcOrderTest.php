<?php

namespace WooCommerce\PayPalCommerce\Api;

use Mockery;
use RuntimeException;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ModularTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;

class CreateOrderForWcOrderTest extends ModularTestCase
{
	private $orderProcesor;

	public function setUp(): void {
		parent::setUp();

		$this->orderProcesor = Mockery::mock(OrderProcessor::class);

		$this->bootstrapModule([
			'wcgateway.order-processor' => function () {
				return $this->orderProcesor;
			},
		]);
	}

	public function testSuccess(): void {
		$wcOrder = Mockery::mock(WC_Order::class);
		$ret = Mockery::mock(Order::class);
		$this->orderProcesor
			->expects('create_order')
			->with($wcOrder)
			->andReturn($ret)
			->once();

		self::assertEquals($ret, ppcp_create_paypal_order_for_wc_order($wcOrder));
	}

	public function testFailure(): void {
		$wcOrder = Mockery::mock(WC_Order::class);
		$this->orderProcesor
			->expects('create_order')
			->with($wcOrder)
			->andThrow(new RuntimeException())
			->once();

		$this->expectException(RuntimeException::class);

		ppcp_create_paypal_order_for_wc_order($wcOrder);
	}
}
