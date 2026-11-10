<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Webhooks;

use RuntimeException;
use WC_Order;
use WC_Product_Simple;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\Webhooks\Handler\CheckoutOrderCompleted;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureCompleted;
use WP_REST_Request;

class CaptureAmountCheckTest extends IntegrationMockedTestCase
{
	private PaymentCaptureCompleted $capture_handler;

	private CheckoutOrderCompleted $order_handler;

	private WC_Order $wc_order;

	private WC_Product_Simple $product;

	public function setUp(): void
	{
		parent::setUp();

		$container = $this->bootstrapModule();
		$logger = $container->get('woocommerce.logger.woocommerce');

		$order_endpoint = $this->createStub(OrderEndpoint::class);
		$order_endpoint->method('order')->willThrowException(new RuntimeException('No PayPal API in tests.'));

		$this->capture_handler = new PaymentCaptureCompleted($logger, $order_endpoint);
		$this->order_handler = new CheckoutOrderCompleted($logger);

		$this->product = new WC_Product_Simple();
		$this->product->set_name('Capture amount check product');
		$this->product->set_regular_price('900.00');
		$this->product->set_virtual(false);
		$this->product->save();

		$this->wc_order = $this->create_pending_order('900.00', 'USD');
	}

	public function tearDown(): void
	{
		$this->wc_order->delete(true);
		$this->product->delete(true);

		parent::tearDown();
	}

	/**
	 * GIVEN a pending 900.00 USD order
	 * WHEN PayPal reports a capture that does not cover the total, first as PAYMENT.CAPTURE.COMPLETED
	 *      and then as the only capture of CHECKOUT.ORDER.COMPLETED
	 * THEN the order stays pending without a paid date
	 * AND the order has a note that the capture does not cover the order total
	 *
	 * @dataProvider insufficient_capture_provider
	 */
	public function test_order_stays_unpaid_when_capture_does_not_cover_total(string $value, string $currency): void
	{
		$this->capture_handler->handle_request($this->capture_completed_request($value, $currency));
		$this->order_handler->handle_request($this->order_completed_request($value, $currency));

		$order = wc_get_order($this->wc_order->get_id());

		$this->assertSame('pending', $order->get_status());
		$this->assertNull($order->get_date_paid());
		$this->assertStringContainsString('does not cover the order total', $this->order_notes($order));
	}

	/**
	 * GIVEN a pending 900.00 USD order
	 * WHEN PayPal reports a completed capture of 900.00 USD through PAYMENT.CAPTURE.COMPLETED
	 * THEN the order is paid
	 */
	public function test_capture_completed_event_pays_order_when_capture_covers_total(): void
	{
		$this->capture_handler->handle_request($this->capture_completed_request('900.00', 'USD'));

		$order = wc_get_order($this->wc_order->get_id());

		$this->assertSame('processing', $order->get_status());
		$this->assertNotNull($order->get_date_paid());
	}

	/**
	 * GIVEN a pending 900.00 USD order
	 * WHEN PayPal reports a completed capture of 900.00 USD through CHECKOUT.ORDER.COMPLETED
	 * THEN the order is paid
	 */
	public function test_order_completed_event_pays_order_when_capture_covers_total(): void
	{
		$this->order_handler->handle_request($this->order_completed_request('900.00', 'USD'));

		$order = wc_get_order($this->wc_order->get_id());

		$this->assertSame('processing', $order->get_status());
		$this->assertNotNull($order->get_date_paid());
	}

	public function insufficient_capture_provider(): array
	{
		return [
			'a one cent capture in the order currency' => ['0.01', 'USD'],
			'the full amount in another currency' => ['900.00', 'EUR'],
		];
	}

	private function create_pending_order(string $total, string $currency): WC_Order
	{
		$order = wc_create_order();
		$order->set_currency($currency);
		$order->add_product($this->product, 1);
		$order->set_total($total);
		$order->set_status('pending');
		$order->update_meta_data(PayPalGateway::ORDER_ID_META_KEY, 'PAYPAL-ORDER-1');
		$order->save();

		return $order;
	}

	private function capture_completed_request(string $value, string $currency): WP_REST_Request
	{
		$request = new WP_REST_Request('POST', '/paypal/webhook');
		$request->set_body_params([
			'id' => 'WH-' . uniqid(),
			'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
			'resource' => [
				'id' => 'CAPTURE-1',
				'status' => 'COMPLETED',
				'custom_id' => (string) $this->wc_order->get_id(),
				'amount' => ['currency_code' => $currency, 'value' => $value],
				'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL-ORDER-1']],
			],
		]);

		return $request;
	}

	private function order_completed_request(string $value, string $currency): WP_REST_Request
	{
		$request = new WP_REST_Request('POST', '/paypal/webhook');
		$request->set_body_params([
			'id' => 'WH-' . uniqid(),
			'event_type' => 'CHECKOUT.ORDER.COMPLETED',
			'resource' => [
				'id' => 'PAYPAL-ORDER-1',
				'status' => 'COMPLETED',
				'purchase_units' => [
					[
						'custom_id' => (string) $this->wc_order->get_id(),
						'payments' => [
							'captures' => [
								[
									'id' => 'CAPTURE-1',
									'status' => 'COMPLETED',
									'amount' => ['currency_code' => $currency, 'value' => $value],
								],
							],
						],
					],
				],
			],
		]);

		return $request;
	}

	private function order_notes(WC_Order $order): string
	{
		$notes = wc_get_order_notes(['order_id' => $order->get_id()]);

		return implode("\n", array_map(static fn($note): string => $note->content, $notes));
	}
}
