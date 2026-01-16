<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\WcGateway\Processor;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

class OrderProcessorConcurrencyTest extends IntegrationMockedTestCase
{
	protected function setupTestContainer(OrderEndpoint $orderEndpoint, array $additionalServices = []): ContainerInterface
	{
		$services = [
			'api.endpoint.order' => fn() => $orderEndpoint,
		];

		return $this->bootstrapModule(array_merge($services, $additionalServices));
	}

	public function testConcurrentCaptureCallsOnlyProcessOnce(): void
	{
		$captureCallCount = 0;

		$mockOrderEndpoint = \Mockery::mock(OrderEndpoint::class);
		$paypalOrder = \Mockery::mock(Order::class)->shouldIgnoreMissing();

		$paypalOrder->shouldReceive('id')->andReturn('TEST-ORDER-123');
		$paypalOrder->shouldReceive('intent')->andReturn('CAPTURE');

		$orderStatus = \Mockery::mock(OrderStatus::class);
		$orderStatus->shouldReceive('is')->with(OrderStatus::COMPLETED)->andReturn(false);
		$orderStatus->shouldReceive('name')->andReturn('APPROVED');
		$paypalOrder->shouldReceive('status')->andReturn($orderStatus);

		$paymentSource = \Mockery::mock(PaymentSource::class);
		$paymentSource->shouldReceive('name')->andReturn('card');
		$paymentSource->shouldReceive('properties')->andReturn((object)['brand' => 'VISA', 'last_digits' => '1234']);
		$paypalOrder->shouldReceive('payment_source')->andReturn($paymentSource);

		$purchaseUnit = \Mockery::mock(PurchaseUnit::class)->shouldIgnoreMissing();
		$payments = \Mockery::mock(Payments::class)->shouldIgnoreMissing();
		$capture = \Mockery::mock(Capture::class)->shouldIgnoreMissing();

		$capture->shouldReceive('id')->andReturn('TEST-CAPTURE-456');
		$captureStatus = \Mockery::mock(CaptureStatus::class)->shouldIgnoreMissing();
		$captureStatus->shouldReceive('name')->andReturn('COMPLETED');
		$captureStatus->shouldReceive('details')->andReturn(null);
		$capture->shouldReceive('status')->andReturn($captureStatus);

		$payments->shouldReceive('captures')->andReturn([$capture]);
		$payments->shouldReceive('authorizations')->andReturn([]);
		$purchaseUnit->shouldReceive('payments')->andReturn($payments);
		$paypalOrder->shouldReceive('purchase_units')->andReturn([$purchaseUnit]);

		$mockOrderEndpoint->shouldReceive('order')->andReturn($paypalOrder);
		$mockOrderEndpoint->shouldReceive('patch_order_with')->andReturn($paypalOrder);
		$mockOrderEndpoint->shouldReceive('capture')->andReturnUsing(function() use (&$captureCallCount, $paypalOrder) {
			$captureCallCount++;
			return $paypalOrder;
		});

		$sessionHandler = \Mockery::mock(SessionHandler::class);
		$sessionHandler->shouldReceive('order')->andReturn($paypalOrder);

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'session.handler' => fn() => $sessionHandler,
		]);

		$wcOrder = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-gateway',
			['simple'],
			[],
			false
		);

		$orderProcessor = $container->get('wcgateway.order-processor');

		$orderProcessor->process($wcOrder);
		$orderProcessor->process($wcOrder);

		$this->assertEquals(1, $captureCallCount, 'PayPal capture should only be called once, not twice');

		$wcOrder = wc_get_order($wcOrder->get_id());
		$this->assertNotEmpty($wcOrder->get_transaction_id());
	}

	public function testOrderWithTransactionIdSkipsProcessing(): void
	{
		$captureCallCount = 0;

		$mockOrderEndpoint = $this->mockOrderEndpoint('CAPTURE', false, true);
		$mockOrderEndpoint->shouldReceive('capture')->andReturnUsing(function() use (&$captureCallCount, $mockOrderEndpoint) {
			$captureCallCount++;
			return $mockOrderEndpoint->order('test');
		});

		$paypalOrder = $mockOrderEndpoint->order('test-order-id');

		$sessionHandler = \Mockery::mock(SessionHandler::class);
		$sessionHandler->shouldReceive('order')->andReturn($paypalOrder);

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'session.handler' => fn() => $sessionHandler,
		]);

		$wcOrder = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-gateway',
			['simple'],
			[],
			false
		);

		$wcOrder->set_transaction_id('EXISTING-TRANSACTION-123');
		$wcOrder->save();

		$orderProcessor = $container->get('wcgateway.order-processor');

		$orderProcessor->process($wcOrder);

		$this->assertEquals(0, $captureCallCount, 'PayPal capture should not be called for orders with transaction ID');
	}

	public function testCompletedOrderSkipsProcessing(): void
	{
		$captureCallCount = 0;

		$mockOrderEndpoint = $this->mockOrderEndpoint('CAPTURE', false, true);
		$mockOrderEndpoint->shouldReceive('capture')->andReturnUsing(function() use (&$captureCallCount, $mockOrderEndpoint) {
			$captureCallCount++;
			return $mockOrderEndpoint->order('test');
		});

		$paypalOrder = $mockOrderEndpoint->order('test-order-id');

		$sessionHandler = \Mockery::mock(SessionHandler::class);
		$sessionHandler->shouldReceive('order')->andReturn($paypalOrder);

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'session.handler' => fn() => $sessionHandler,
		]);

		$wcOrder = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-gateway',
			['simple'],
			[],
			false
		);

		$wcOrder->set_status('processing');
		$wcOrder->save();

		$orderProcessor = $container->get('wcgateway.order-processor');

		$orderProcessor->process($wcOrder);

		$this->assertEquals(0, $captureCallCount, 'PayPal capture should not be called for completed orders');
	}

	public function testProcessingFlagPreventsSecondCall(): void
	{
		$captureCallCount = 0;

		$mockOrderEndpoint = $this->mockOrderEndpoint('CAPTURE', false, true);
		$mockOrderEndpoint->shouldReceive('capture')->andReturnUsing(function() use (&$captureCallCount, $mockOrderEndpoint) {
			$captureCallCount++;
			return $mockOrderEndpoint->order('test');
		});

		$paypalOrder = $mockOrderEndpoint->order('test-order-id');

		$sessionHandler = \Mockery::mock(SessionHandler::class);
		$sessionHandler->shouldReceive('order')->andReturn($paypalOrder);

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'session.handler' => fn() => $sessionHandler,
		]);

		$wcOrder = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-gateway',
			['simple'],
			[],
			false
		);

		$wcOrder->update_meta_data('_ppcp_processing', 'yes');
		$wcOrder->save();

		$orderProcessor = $container->get('wcgateway.order-processor');

		$orderProcessor->process($wcOrder);

		$this->assertEquals(0, $captureCallCount, 'PayPal capture should not be called when processing flag is set');
	}

	public function testCompletedOrderSkipsProcessingMultipleTimes(): void
	{
		$captureCallCount = 0;

		$mockOrderEndpoint = $this->mockOrderEndpoint('CAPTURE', false, true);
		$mockOrderEndpoint->shouldReceive('capture')->andReturnUsing(function() use (&$captureCallCount, $mockOrderEndpoint) {
			$captureCallCount++;
			return $mockOrderEndpoint->order('test');
		});

		$paypalOrder = $mockOrderEndpoint->order('test-order-id');

		$sessionHandler = \Mockery::mock(SessionHandler::class);
		$sessionHandler->shouldReceive('order')->andReturn($paypalOrder);

		$container = $this->setupTestContainer($mockOrderEndpoint, [
			'session.handler' => fn() => $sessionHandler,
		]);

		$wcOrder = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-gateway',
			['simple'],
			[],
			false
		);

		$wcOrder->set_status('processing');
		$wcOrder->set_transaction_id('EXISTING-TRANSACTION-123');
		$wcOrder->save();

		$orderProcessor = $container->get('wcgateway.order-processor');

		$orderProcessor->process($wcOrder);
		$orderProcessor->process($wcOrder);
		$orderProcessor->process($wcOrder);

		$this->assertEquals(0, $captureCallCount, 'PayPal capture should not be called for already completed orders');

		$wcOrder = wc_get_order($wcOrder->get_id());
		$this->assertEquals('processing', $wcOrder->get_status(), 'Order status should remain processing');
		$this->assertEquals('EXISTING-TRANSACTION-123', $wcOrder->get_transaction_id(), 'Transaction ID should remain unchanged');
	}
}
