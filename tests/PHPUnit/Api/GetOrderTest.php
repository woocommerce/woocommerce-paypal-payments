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

class GetOrderTest extends ModularTestCase
{
	private $orderEndpoint;

	public function setUp(): void {
		parent::setUp();

		$this->orderEndpoint = Mockery::mock(OrderEndpoint::class);

		$this->bootstrapModule([
			'api.endpoint.order' => function () {
				return $this->orderEndpoint;
			},
		]);
	}

	public function testSuccess(): void {
		$this->orderEndpoint
			->expects('order')
			->with('123abc')
			->andReturn(Mockery::mock(Order::class))
			->once();

		ppcp_get_paypal_order('123abc');
	}

	public function testSuccessWithOrder(): void {
		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder->expects('get_meta')
			->with(PayPalGateway::ORDER_ID_META_KEY)
			->andReturn('123abc');

		$this->orderEndpoint
			->expects('order')
			->with('123abc')
			->andReturn(Mockery::mock(Order::class))
			->once();

		ppcp_get_paypal_order($wcOrder);
	}

	public function testOrderWithoutId(): void {
		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder->expects('get_meta')
			->with(PayPalGateway::ORDER_ID_META_KEY)
			->andReturn(false);

		$this->expectException(InvalidArgumentException::class);

		ppcp_get_paypal_order($wcOrder);
	}

	public function testFailure(): void {
		$this->orderEndpoint
			->expects('order')
			->with('123abc')
			->andThrow(new RuntimeException())
			->once();

		$this->expectException(RuntimeException::class);

		ppcp_get_paypal_order('123abc');
	}

	public function testInvalidId(): void {
		$this->expectException(InvalidArgumentException::class);

		ppcp_get_paypal_order(123);
	}
}
