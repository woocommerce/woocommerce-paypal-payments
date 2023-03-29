<?php

namespace WooCommerce\PayPalCommerce\Api;

use InvalidArgumentException;
use Mockery;
use RuntimeException;
use WC_Order;
use WooCommerce\PayPalCommerce\ModularTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;

class OrderCaptureTest extends ModularTestCase
{
	private $authorizedPaymentProcessor;

	public function setUp(): void {
		parent::setUp();

		$this->authorizedPaymentProcessor = Mockery::mock(AuthorizedPaymentsProcessor::class);

		$this->bootstrapModule([
			'wcgateway.processor.authorized-payments' => function () {
				return $this->authorizedPaymentProcessor;
			},
		]);
	}

	public function testSuccess(): void {
		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder->expects('get_meta')
			->with(PayPalGateway::INTENT_META_KEY)
			->andReturn('AUTHORIZE');
		$wcOrder->expects('get_meta')
			->with(AuthorizedPaymentsProcessor::CAPTURED_META_KEY)
			->andReturn(false);

		$this->authorizedPaymentProcessor
			->expects('capture_authorized_payment')
			->andReturnTrue()
			->once();

		ppcp_capture_order($wcOrder);
	}

	public function testFailure(): void {
		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder->expects('get_meta')
			->with(PayPalGateway::INTENT_META_KEY)
			->andReturn('AUTHORIZE');
		$wcOrder->expects('get_meta')
			->with(AuthorizedPaymentsProcessor::CAPTURED_META_KEY)
			->andReturn(false);

		$this->authorizedPaymentProcessor
			->expects('capture_authorized_payment')
			->andReturnFalse()
			->once();

		$this->expectException(RuntimeException::class);

		ppcp_capture_order($wcOrder);
	}

	public function testNotAuthorize(): void {
		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder->shouldReceive('get_meta')
			->with(PayPalGateway::INTENT_META_KEY)
			->andReturn('CAPTURE');
		$wcOrder->shouldReceive('get_meta')
			->with(AuthorizedPaymentsProcessor::CAPTURED_META_KEY)
			->andReturn(false);

		$this->expectException(InvalidArgumentException::class);

		ppcp_capture_order($wcOrder);
	}

	public function testAlreadyCaptured(): void {
		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder->shouldReceive('get_meta')
			->with(PayPalGateway::INTENT_META_KEY)
			->andReturn('CAPTURE');
		$wcOrder->shouldReceive('get_meta')
			->with(AuthorizedPaymentsProcessor::CAPTURED_META_KEY)
			->andReturn(true);

		$this->expectException(InvalidArgumentException::class);

		ppcp_capture_order($wcOrder);
	}
}
