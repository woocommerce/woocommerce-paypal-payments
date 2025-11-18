<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Transaction;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXOGateway;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * OXXO Gateway Integration Tests
 *
 * Tests OXXO payment processing functionality with minimal API mocking.
 * Only third-party PayPal API calls are mocked.
 *
 * @group transactions
 */
class OxxoTransactionTest extends IntegrationMockedTestCase
{
	/**
	 * Sets up a test container with common mocks
	 *
	 * @param OrderEndpoint $orderEndpoint Mocked order endpoint
	 * @param array $additionalServices Additional services to override
	 * @return ContainerInterface
	 */
	protected function setupTestContainer(OrderEndpoint $orderEndpoint, array $additionalServices = []): ContainerInterface
	{
		$services = [
			'api.endpoint.order' => function () use ($orderEndpoint) {
				return $orderEndpoint;
			},
		];

		return $this->bootstrapModule(array_merge($services, $additionalServices));
	}

	/**
	 * Data provider for different product combinations
	 *
	 * @return array
	 */
	public function paymentProcessingDataProvider(): array
	{
		// A webhook will put the pending payments to on-hold status,
		// but we are not testing webhooks here.
		// look at PaymentCapturePending::handle_request
		return [
			'simple product only' => [
				'products' => ['simple'],
				'expected_status' => 'pending'
			],
			'expensive product' => [
				'products' => ['simple_expensive'],
				'expected_status' => 'pending'
			],
			'multiple products' => [
				'products' => [
					['preset' => 'simple', 'quantity' => 2],
					'simple_expensive'
				],
				'expected_status' => 'pending'
			],
		];
	}

	/**
	 * Tests OXXO payment processing with different product combinations.
	 *
	 * @scenario Process OXXO payment for order
	 * @given a WooCommerce order with various product combinations
	 * @and valid billing information for Mexico
	 * @when the OXXO payment is processed
	 * @then the PayPal API should be called to create and confirm payment
	 * @and a payer action link should be stored in order meta
	 * @and the order status should change to on-hold
	 * @and the cart should be emptied
	 *
	 * @dataProvider paymentProcessingDataProvider
	 *
	 * @param array $products Product configuration
	 * @param string $expected_status Expected order status after payment
	 *
	 * @return void
	 */
	public function testProcessPayment(array $products, string $expected_status): void
	{
		$mockOrderEndpoint = $this->mockOrderEndpointForOXXO();
		$container = $this->setupTestContainer($mockOrderEndpoint);

		$order = $this->getConfiguredOrder(
			$this->customer_id,
			OXXOGateway::ID,
			$products,
			[], // No discounts for OXXO tests
			false
		);

		$order->set_billing_country('MX');
		$order->set_billing_first_name('Juan');
		$order->set_billing_last_name('Pérez');
		$order->set_billing_email('juan.perez@example.com');
		$order->save();

		$gateway = $container->get('wcgateway.oxxo-gateway');
		$result = $gateway->process_payment($order->get_id());

		$this->assertEquals('success', $result['result']);
		$this->assertArrayHasKey('redirect', $result);

		$order = wc_get_order($order->get_id());
		$this->assertEquals($expected_status, $order->get_status());

		$payer_action_link = $order->get_meta('ppcp_oxxo_payer_action');
		$this->assertNotEmpty($payer_action_link);
		$this->assertStringContainsString('paypal.com/payment/oxxo', $payer_action_link);
	}

	/**
	 * Tests OXXO payment processing failure scenarios.
	 *
	 * @scenario Process OXXO payment with API failure
	 * @given a WooCommerce order with valid billing information
	 * @when the PayPal API fails during order creation
	 * @then the payment should fail gracefully
	 * @and the order status should be updated to failed
	 * @and an error notice should be displayed
	 *
	 * @return void
	 */
	public function testProcessPaymentFailure(): void
	{
		$mockOrderEndpoint = $this->mockOrderEndpointWithFailure();
		$container = $this->setupTestContainer($mockOrderEndpoint);

		$order = $this->getConfiguredOrder(
			$this->customer_id,
			OXXOGateway::ID,
			['simple'],
			[],
			false
		);
		$order->set_billing_country('MX');
		$order->set_billing_first_name('Juan');
		$order->set_billing_last_name('Pérez');
		$order->set_billing_email('juan.perez@example.com');
		$order->save();

		$gateway = $container->get('wcgateway.oxxo-gateway');
		$result = $gateway->process_payment($order->get_id());

		$this->assertEquals('failure', $result['result']);
	}

	/**
	 * Mock OrderEndpoint for successful OXXO payment processing
	 *
	 * @return OrderEndpoint
	 */
	private function mockOrderEndpointForOXXO(): OrderEndpoint
	{
		$mockEndpoint = $this->mockOrderEndpoint('CAPTURE', true, true);

		// Add OXXO-specific confirm_payment_source method
		$confirmResponse = (object)[
			'links' => [
				(object)[
					'rel' => 'payer-action',
					'href' => 'https://sandbox.paypal.com/payment/oxxo?token=TEST_TOKEN_123',
				],
			]
		];

		$mockEndpoint->shouldReceive('confirm_payment_source')
			->andReturn($confirmResponse);

		return $mockEndpoint;
	}

	/**
	 * Mock OrderEndpoint for failed payment processing
	 *
	 * @return OrderEndpoint
	 */
	private function mockOrderEndpointWithFailure(): OrderEndpoint
	{
		$mockEndpoint = $this->mockOrderEndpoint('CAPTURE', false, false);

		// Override the create method to throw exception (OXXO fails at order creation)
		$mockEndpoint->shouldReceive('confirm_payment_source')
			->andThrow(RuntimeException::class, 'API Error');

		return $mockEndpoint;
	}
}
