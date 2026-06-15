<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Transaction;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PayUponInvoiceOrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Pay upon Invoice Gateway Integration Tests
 *
 * Tests PUI payment processing against the real plugin container. Only the
 * third-party PayPal PayUponInvoiceOrderEndpoint is mocked.
 *
 * @group transactions
 */
class PayUponInvoiceTransactionTest extends IntegrationMockedTestCase
{
	private const PP_ORDER_ID = 'PP-ORDER-PUI-123';

	public function tearDown(): void
	{
		unset($_POST['billing_birth_date'], $_POST['billing_phone']);
		parent::tearDown();
	}

	/**
	 * Boots the plugin container with the PUI order endpoint replaced by a mock.
	 *
	 * @param PayUponInvoiceOrderEndpoint $order_endpoint Mocked endpoint.
	 * @return ContainerInterface
	 */
	protected function setupTestContainer(PayUponInvoiceOrderEndpoint $order_endpoint): ContainerInterface
	{
		return $this->bootstrapModule([
			'ppcp-local-apms.pui.order-endpoint' => function () use ($order_endpoint) {
				return $order_endpoint;
			},
		]);
	}

	/**
	 * Data provider for different product combinations. PUI orders move to
	 * on-hold while awaiting the Ratepay payment instructions.
	 *
	 * @return array
	 */
	public function paymentProcessingDataProvider(): array
	{
		return [
			'simple product only' => [
				'products' => ['simple'],
			],
			'expensive product' => [
				'products' => ['simple_expensive'],
			],
			'multiple products' => [
				'products' => [
					['preset' => 'simple', 'quantity' => 2],
					'simple_expensive',
				],
			],
		];
	}

	/**
	 * Processing a PUI payment creates the PayPal order, stores the PayPal order
	 * id, leaves the WC order on-hold and empties the cart.
	 *
	 * @dataProvider paymentProcessingDataProvider
	 *
	 * @param array $products Product configuration.
	 * @return void
	 */
	public function testProcessPayment(array $products): void
	{
		$container = $this->setupTestContainer($this->mockOrderEndpointForSuccess());

		$_POST['billing_birth_date'] = '1990-01-01';
		$_POST['billing_phone']      = '1701234567';

		$order = $this->getConfiguredOrder(
			$this->customer_id,
			PayUponInvoiceGateway::ID,
			$products,
			[],
			false
		);
		$order->set_billing_country('DE');
		$order->set_billing_first_name('Max');
		$order->set_billing_last_name('Mustermann');
		$order->set_billing_email('max.mustermann@example.com');
		$order->set_billing_phone('1701234567');
		$order->save();

		$gateway = $container->get('ppcp-local-apms.pui.wc-gateway');
		$result  = $gateway->process_payment($order->get_id());

		$this->assertEquals('success', $result['result']);

		$order = wc_get_order($order->get_id());
		$this->assertEquals('on-hold', $order->get_status());
		$this->assertEquals(self::PP_ORDER_ID, $order->get_meta(PayPalGateway::ORDER_ID_META_KEY));
		$this->assertTrue(WC()->cart->is_empty());
	}

	/**
	 * When the PayPal API fails while creating the order the WC order is marked
	 * failed and the buyer is sent back to checkout.
	 *
	 * Regression guard: the gateway must catch the API-layer exceptions
	 * (\RuntimeException / PayPalApiException) thrown by the order endpoint.
	 *
	 * @return void
	 */
	public function testProcessPaymentApiFailure(): void
	{
		$container = $this->setupTestContainer($this->mockOrderEndpointForApiFailure());

		$_POST['billing_birth_date'] = '1990-01-01';
		$_POST['billing_phone']      = '1701234567';

		$order = $this->getConfiguredOrder(
			$this->customer_id,
			PayUponInvoiceGateway::ID,
			['simple'],
			[],
			false
		);
		$order->set_billing_country('DE');
		$order->set_billing_first_name('Max');
		$order->set_billing_last_name('Mustermann');
		$order->set_billing_email('max.mustermann@example.com');
		$order->set_billing_phone('1701234567');
		$order->save();

		$gateway = $container->get('ppcp-local-apms.pui.wc-gateway');
		$result  = $gateway->process_payment($order->get_id());

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
		$container = $this->setupTestContainer($this->mockOrderEndpointForSuccess());

		$gateway = $container->get('ppcp-local-apms.pui.wc-gateway');
		$result  = $gateway->process_payment(0);

		$this->assertEquals('failure', $result['result']);
		$this->assertEquals(wc_get_checkout_url(), $result['redirect']);
	}

	/**
	 * Mocks the PUI order endpoint for a successful flow. `create()` returns a
	 * minimal PayPal Order entity carrying only the data the gateway reads.
	 *
	 * @return PayUponInvoiceOrderEndpoint
	 */
	private function mockOrderEndpointForSuccess(): PayUponInvoiceOrderEndpoint
	{
		$endpoint = Mockery::mock(PayUponInvoiceOrderEndpoint::class);
		$endpoint->shouldReceive('create')->andReturn($this->mockPayPalOrder());

		return $endpoint;
	}

	/**
	 * Mocks the PUI order endpoint so creating the order throws a realistic
	 * PayPal API exception.
	 *
	 * @return PayUponInvoiceOrderEndpoint
	 */
	private function mockOrderEndpointForApiFailure(): PayUponInvoiceOrderEndpoint
	{
		$endpoint = Mockery::mock(PayUponInvoiceOrderEndpoint::class);
		$endpoint->shouldReceive('create')->andThrow(
			new PayPalApiException((object) ['message' => 'API error'], 422)
		);

		return $endpoint;
	}

	/**
	 * Minimal PayPal Order entity mock for the gateway's metadata handling.
	 *
	 * @return Order
	 */
	private function mockPayPalOrder(): Order
	{
		$order = Mockery::mock(Order::class);
		$order->shouldReceive('id')->andReturn(self::PP_ORDER_ID);
		$order->shouldReceive('intent')->andReturn('CAPTURE');
		$order->shouldReceive('payment_source')->andReturn(null);
		$order->shouldReceive('payer')->andReturn(null);
		$order->shouldReceive('purchase_units')->andReturn([]);

		return $order;
	}
}
