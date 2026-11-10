<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\WcGateway\Processor;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Webhooks\CustomIds;

class OrderProcessorRejectedPatchTest extends IntegrationMockedTestCase
{
	private ?\Closure $customIdFilter = null;

	public function testRejectedPatchDoesNotCaptureOrderOfAnotherWcOrder(): void
	{
		$mockOrderEndpoint = \Mockery::mock(OrderEndpoint::class);
		$sessionHandler = \Mockery::mock(SessionHandler::class);

		$container = $this->setupTestContainer($mockOrderEndpoint, $sessionHandler);

		$otherWcOrder = $this->getConfiguredOrder($this->customer_id, 'ppcp-gateway', ['simple'], [], false);
		$wcOrder = $this->getConfiguredOrder($this->customer_id, 'ppcp-gateway', ['simple'], [], false);

		// Same amount, but the webhooks would complete the other WC order after the capture.
		$approvedOrder = $this->createPayPalOrder($container, 'APPROVED', (string) $wcOrder->get_total(), (string) $otherWcOrder->get_id());

		$sessionHandler->shouldReceive('order')->andReturn($approvedOrder);
		$mockOrderEndpoint->shouldReceive('order')->andReturn($approvedOrder);
		$mockOrderEndpoint->shouldReceive('patch_order_with')->once()->andThrow($this->rejectedPatch());
		$mockOrderEndpoint->shouldNotReceive('capture');

		try {
			$container->get('wcgateway.order-processor')->process($wcOrder);
			$this->fail('Processing should fail when the PayPal order cannot be bound to the WC order');
		} catch (PayPalApiException $exception) {
			$this->assertSame(422, $exception->status_code());
		}

		$wcOrder = wc_get_order($wcOrder->get_id());
		$this->assertEquals('pending', $wcOrder->get_status());
		$this->assertEmpty($wcOrder->get_transaction_id());
		$this->assertEmpty($wcOrder->get_meta(PayPalGateway::ORDER_ID_META_KEY));
	}

	public function testRejectedPatchIsSkippedWhenFilteredCustomIdMatchesPayPalOrder(): void
	{
		$mockOrderEndpoint = \Mockery::mock(OrderEndpoint::class);
		$sessionHandler = \Mockery::mock(SessionHandler::class);

		$container = $this->setupTestContainer($mockOrderEndpoint, $sessionHandler);

		$wcOrder = $this->getConfiguredOrder($this->customer_id, 'ppcp-gateway', ['simple'], [], false);
		$customId = 'store1-' . $wcOrder->get_id();
		$this->addCustomIdFilter($customId);

		$approvedOrder = $this->createPayPalOrder($container, 'APPROVED', (string) $wcOrder->get_total(), $customId);
		$capturedOrder = $this->createPayPalOrder($container, 'COMPLETED', (string) $wcOrder->get_total(), $customId);

		$sessionHandler->shouldReceive('order')->andReturn($approvedOrder);
		$mockOrderEndpoint->shouldReceive('order')->andReturn($approvedOrder);
		$mockOrderEndpoint->shouldReceive('patch_order_with')->once()->andThrow($this->rejectedPatch());
		$mockOrderEndpoint->shouldReceive('capture')->once()->andReturn($capturedOrder);

		$container->get('wcgateway.order-processor')->process($wcOrder);

		$this->assertEquals('processing', wc_get_order($wcOrder->get_id())->get_status());
	}

	public function testRejectedPatchIsNotSkippedWhenFilteredCustomIdDiffersFromPayPalOrder(): void
	{
		$mockOrderEndpoint = \Mockery::mock(OrderEndpoint::class);
		$sessionHandler = \Mockery::mock(SessionHandler::class);

		$container = $this->setupTestContainer($mockOrderEndpoint, $sessionHandler);

		$otherWcOrder = $this->getConfiguredOrder($this->customer_id, 'ppcp-gateway', ['simple'], [], false);
		$wcOrder = $this->getConfiguredOrder($this->customer_id, 'ppcp-gateway', ['simple'], [], false);
		$this->addCustomIdFilter('store1-' . $wcOrder->get_id());

		$approvedOrder = $this->createPayPalOrder($container, 'APPROVED', (string) $wcOrder->get_total(), 'store1-' . $otherWcOrder->get_id());

		$sessionHandler->shouldReceive('order')->andReturn($approvedOrder);
		$mockOrderEndpoint->shouldReceive('order')->andReturn($approvedOrder);
		$mockOrderEndpoint->shouldReceive('patch_order_with')->once()->andThrow($this->rejectedPatch());
		$mockOrderEndpoint->shouldNotReceive('capture');

		try {
			$container->get('wcgateway.order-processor')->process($wcOrder);
			$this->fail('Processing should fail when the PayPal order names another WC order');
		} catch (PayPalApiException $exception) {
			$this->assertSame(422, $exception->status_code());
		}

		$this->assertEquals('pending', wc_get_order($wcOrder->get_id())->get_status());
	}

	/**
	 * @dataProvider unboundCustomIds
	 */
	public function testRejectedPatchIsSkippedWhenNoOtherWcOrderIsNamed(callable $customId): void
	{
		$mockOrderEndpoint = \Mockery::mock(OrderEndpoint::class);
		$sessionHandler = \Mockery::mock(SessionHandler::class);

		$container = $this->setupTestContainer($mockOrderEndpoint, $sessionHandler);

		$wcOrder = $this->getConfiguredOrder($this->customer_id, 'ppcp-gateway', ['simple'], [], false);

		$approvedOrder = $this->createPayPalOrder($container, 'APPROVED', (string) $wcOrder->get_total(), $customId($wcOrder));
		$capturedOrder = $this->createPayPalOrder($container, 'COMPLETED', (string) $wcOrder->get_total(), $customId($wcOrder));

		$sessionHandler->shouldReceive('order')->andReturn($approvedOrder);
		$mockOrderEndpoint->shouldReceive('order')->andReturn($approvedOrder);
		$mockOrderEndpoint->shouldReceive('patch_order_with')->once()->andThrow($this->rejectedPatch());
		$mockOrderEndpoint->shouldReceive('capture')->once()->andReturn($capturedOrder);

		$container->get('wcgateway.order-processor')->process($wcOrder);

		$this->assertEquals('processing', wc_get_order($wcOrder->get_id())->get_status());
	}

	public function unboundCustomIds(): array
	{
		return [
			'empty' => [fn(\WC_Order $wcOrder): string => ''],
			'session' => [fn(\WC_Order $wcOrder): string => CustomIds::CUSTOMER_ID_PREFIX . 't_abc123'],
			'same WC order' => [fn(\WC_Order $wcOrder): string => (string) $wcOrder->get_id()],
		];
	}

	private function addCustomIdFilter(string $customId): void
	{
		$this->customIdFilter = static function ($purchaseUnit) use ($customId) {
			$purchaseUnit->set_custom_id($customId);
			return $purchaseUnit;
		};
		add_filter('woocommerce_paypal_payments_purchase_unit_from_wc_order', $this->customIdFilter);
	}

	public function tearDown(): void
	{
		if ($this->customIdFilter !== null) {
			remove_filter('woocommerce_paypal_payments_purchase_unit_from_wc_order', $this->customIdFilter);
			$this->customIdFilter = null;
		}

		parent::tearDown();
	}

	private function setupTestContainer(OrderEndpoint $orderEndpoint, SessionHandler $sessionHandler): ContainerInterface
	{
		return $this->bootstrapModule([
			'api.endpoint.order' => fn() => $orderEndpoint,
			'session.handler' => fn() => $sessionHandler,
		]);
	}

	private function rejectedPatch(): PayPalApiException
	{
		return new PayPalApiException(
			(object) [
				'name' => 'UNPROCESSABLE_ENTITY',
				'message' => 'The requested action could not be performed, semantically incorrect, or failed business validation.',
				'details' => [(object) ['issue' => 'SHIPPING_ADDRESS_INVALID']],
			],
			422
		);
	}

	private function createPayPalOrder(ContainerInterface $container, string $status, string $amount, string $customId): Order
	{
		$amountData = ['currency_code' => get_woocommerce_currency(), 'value' => $amount];
		$purchaseUnit = ['reference_id' => 'default', 'amount' => $amountData, 'custom_id' => $customId];

		if ($status === 'COMPLETED') {
			$purchaseUnit['payments'] = [
				'captures' => [
					[
						'id' => 'TEST-CAPTURE-456',
						'status' => 'COMPLETED',
						'amount' => $amountData,
						'final_capture' => true,
					],
				],
			];
		}

		return $container->get('api.factory.order')->from_paypal_response(
			json_decode(
				(string) wp_json_encode([
					'id' => 'TEST-ORDER-123',
					'intent' => 'CAPTURE',
					'status' => $status,
					'payment_source' => [
						'paypal' => [
							'email_address' => 'buyer@example.com',
							'account_id' => 'TESTPAYERID123',
						],
					],
					'purchase_units' => [$purchaseUnit],
				])
			)
		);
	}
}
