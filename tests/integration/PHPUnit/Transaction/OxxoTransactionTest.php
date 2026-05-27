<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Transaction;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\OXXOGateway;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * OXXO Gateway Integration Tests
 *
 * Tests OXXO payment processing against the real plugin container. Only the
 * third-party PayPal Orders endpoint is mocked.
 *
 * @group transactions
 */
class OxxoTransactionTest extends IntegrationMockedTestCase
{
	private const PP_ORDER_ID = 'PP-ORDER-123';
	private const PAYER_ACTION_URL = 'https://www.sandbox.paypal.com/payment/oxxo?token=TEST_TOKEN_123';

	/**
	 * Boots the plugin container with the OXXO Orders endpoint replaced by a mock.
	 *
	 * @param Orders $orders_endpoint Mocked Orders endpoint.
	 * @return ContainerInterface
	 */
	protected function setupTestContainer(Orders $orders_endpoint): ContainerInterface
	{
		return $this->bootstrapModule([
			'api.endpoint.orders' => function () use ($orders_endpoint) {
				return $orders_endpoint;
			},
		]);
	}

	/**
	 * Data provider for different product combinations. OXXO orders stay
	 * "pending" until the buyer pays the voucher (a webhook later moves them
	 * to on-hold); webhooks are not exercised here.
	 *
	 * @return array
	 */
	public function paymentProcessingDataProvider(): array
	{
		return [
			'simple product only' => [
				'products' => ['simple'],
				'expected_status' => 'pending',
			],
			'expensive product' => [
				'products' => ['simple_expensive'],
				'expected_status' => 'pending',
			],
			'multiple products' => [
				'products' => [
					['preset' => 'simple', 'quantity' => 2],
					'simple_expensive',
				],
				'expected_status' => 'pending',
			],
		];
	}

	/**
	 * Processing an OXXO payment creates and confirms the PayPal order, stores
	 * the payer-action voucher link, and leaves the order pending.
	 *
	 * @dataProvider paymentProcessingDataProvider
	 *
	 * @param array  $products Product configuration.
	 * @param string $expected_status Expected order status after payment.
	 * @return void
	 */
	public function testProcessPayment(array $products, string $expected_status): void
	{
		$container = $this->setupTestContainer($this->mockOrdersForSuccess());

		$order = $this->getConfiguredOrder(
			$this->customer_id,
			OXXOGateway::ID,
			$products,
			[],
			false
		);
		$order->set_billing_country('MX');
		$order->set_billing_first_name('Juan');
		$order->set_billing_last_name('Pérez');
		$order->set_billing_email('juan.perez@example.com');
		$order->save();

		$gateway = $container->get('ppcp-local-apms.oxxo.wc-gateway');
		$result = $gateway->process_payment($order->get_id());

		$this->assertEquals('success', $result['result']);
		$this->assertStringContainsString('paypal.com/payment/oxxo', $result['redirect']);

		$order = wc_get_order($order->get_id());
		$this->assertEquals($expected_status, $order->get_status());
		$this->assertEquals(self::PAYER_ACTION_URL, $order->get_meta('ppcp_oxxo_payer_action'));
		$this->assertEquals(self::PP_ORDER_ID, $order->get_meta(PayPalGateway::ORDER_ID_META_KEY));
		$this->assertTrue(WC()->cart->is_empty());
	}

	/**
	 * When the PayPal API fails while confirming the payment source the order
	 * is marked failed and the buyer is sent back to checkout.
	 *
	 * Regression guard: the gateway must catch the API-layer exceptions
	 * (\RuntimeException / PayPalApiException) thrown by the Orders endpoint.
	 *
	 * @return void
	 */
	public function testProcessPaymentApiFailure(): void
	{
		$container = $this->setupTestContainer($this->mockOrdersForApiFailure());

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

		$gateway = $container->get('ppcp-local-apms.oxxo.wc-gateway');
		$result = $gateway->process_payment($order->get_id());

		$this->assertEquals('failure', $result['result']);
		$this->assertEquals(wc_get_checkout_url(), $result['redirect']);

		$order = wc_get_order($order->get_id());
		$this->assertEquals('failed', $order->get_status());
	}

	/**
	 * An invalid order id fails fast without touching the PayPal API.
	 *
	 * @return void
	 */
	public function testProcessPaymentInvalidOrder(): void
	{
		$container = $this->setupTestContainer($this->mockOrdersForSuccess());

		$gateway = $container->get('ppcp-local-apms.oxxo.wc-gateway');
		$result = $gateway->process_payment(0);

		$this->assertEquals('failure', $result['result']);
		$this->assertEquals(wc_get_checkout_url(), $result['redirect']);
	}

	/**
	 * Mocks the Orders endpoint for a successful OXXO flow. Both calls return
	 * the raw `['body' => <json>]` shape the gateway decodes.
	 *
	 * @return Orders
	 */
	private function mockOrdersForSuccess(): Orders
	{
		$orders = Mockery::mock(Orders::class);

		$orders->shouldReceive('create')->andReturn([
			'body' => wp_json_encode(['id' => self::PP_ORDER_ID]),
		]);

		$orders->shouldReceive('confirm_payment_source')->andReturn([
			'body' => wp_json_encode([
				'id'    => self::PP_ORDER_ID,
				'links' => [
					['rel' => 'approve', 'href' => 'https://example.test/approve'],
					['rel' => 'payer-action', 'href' => self::PAYER_ACTION_URL],
				],
			]),
		]);

		return $orders;
	}

	/**
	 * Mocks the Orders endpoint so the order is created but confirming the
	 * payment source throws a realistic PayPal API exception.
	 *
	 * @return Orders
	 */
	private function mockOrdersForApiFailure(): Orders
	{
		$orders = Mockery::mock(Orders::class);

		$orders->shouldReceive('create')->andReturn([
			'body' => wp_json_encode(['id' => self::PP_ORDER_ID]),
		]);

		$orders->shouldReceive('confirm_payment_source')->andThrow(
			new PayPalApiException((object) ['message' => 'API error'], 422)
		);

		return $orders;
	}
}
