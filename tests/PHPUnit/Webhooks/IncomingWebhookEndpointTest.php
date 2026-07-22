<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\WebhookEvent;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookEventFactory;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Exception\PayPalOrderMissingException;
use WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandler;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @covers \WooCommerce\PayPalCommerce\Webhooks\IncomingWebhookEndpoint
 */
class IncomingWebhookEndpointTest extends TestCase
{
	/** @var WebhookEventFactory&Mockery\MockInterface */
	private $webhook_event_factory;

	/** @var WebhookSimulation&Mockery\MockInterface */
	private $simulation;

	/** @var WebhookEventStorage&Mockery\MockInterface */
	private $last_webhook_event_storage;

	/** @var LoggerInterface&Mockery\MockInterface */
	private $logger;

	public function setUp(): void
	{
		parent::setUp();

		$this->webhook_event_factory      = Mockery::mock(WebhookEventFactory::class);
		$this->simulation                 = Mockery::mock(WebhookSimulation::class);
		$this->last_webhook_event_storage  = Mockery::mock(WebhookEventStorage::class);
		$this->logger                     = Mockery::mock(LoggerInterface::class);

		$this->logger->shouldReceive('debug');
		$this->logger->shouldReceive('info');

		$this->last_webhook_event_storage->shouldReceive('save');
		$this->simulation->shouldReceive('is_simulation_event')->andReturn(false);
	}

	private function createRequest(): WP_REST_Request
	{
		/** @var WP_REST_Request&Mockery\MockInterface $request */
		$request = Mockery::mock('WP_REST_Request, ArrayAccess');
		$request->allows('get_params')->andReturn([]);
		$request->allows('offsetExists')->andReturn(true);
		$request->allows('offsetGet')->with('event_type')->andReturn('CHECKOUT.ORDER.APPROVED');

		return $request;
	}

	private function createEndpoint(RequestHandler ...$handlers): IncomingWebhookEndpoint
	{
		$webhook_endpoint = Mockery::mock(WebhookEndpoint::class);

		return new IncomingWebhookEndpoint(
			$webhook_endpoint,
			null,
			$this->logger,
			false,
			$this->webhook_event_factory,
			$this->simulation,
			$this->last_webhook_event_storage,
			...$handlers
		);
	}

	/**
	 * @scenario When a handler's handle_request() throws an exception that is not a
	 * RuntimeException (e.g. PayPalOrderMissingException, which extends the plain
	 * \Exception class), the exception must not propagate uncaught. Previously this
	 * caused an uncaught fatal (translating to an HTTP 500 with no framework-level
	 * catch), which made PayPal retry the webhook indefinitely.
	 */
	public function test_unexpected_exception_from_handler_does_not_propagate_uncaught(): void
	{
		$event = new WebhookEvent('evt-1', null, 'checkout-order', '1.0', 'CHECKOUT.ORDER.APPROVED', '', '', (object) []);
		$this->webhook_event_factory->shouldReceive('from_array')->andReturn($event);

		$handler = Mockery::mock(RequestHandler::class);
		$handler->shouldReceive('responsible_for_request')->andReturn(true);
		$handler->shouldReceive('event_types')->andReturn(['CHECKOUT.ORDER.APPROVED']);
		$handler->shouldReceive('handle_request')->andThrow(
			new PayPalOrderMissingException('There was an error processing your order.')
		);

		$this->logger->shouldReceive('error')->once();

		$sut = $this->createEndpoint($handler);

		$response = $sut->handle_request($this->createRequest());

		$this->assertInstanceOf(WP_REST_Response::class, $response);
		$this->assertSame(200, $response->get_status());
		$this->assertFalse($response->get_data()['success']);
	}

	/**
	 * @scenario The happy path (handler returns a response normally) must keep working
	 * unchanged after wrapping handle_request() in a try/catch.
	 */
	public function test_handler_response_is_returned_unchanged_on_success(): void
	{
		$event = new WebhookEvent('evt-1', null, 'checkout-order', '1.0', 'CHECKOUT.ORDER.APPROVED', '', '', (object) []);
		$this->webhook_event_factory->shouldReceive('from_array')->andReturn($event);

		$expected_response = new WP_REST_Response(['success' => true]);

		$handler = Mockery::mock(RequestHandler::class);
		$handler->shouldReceive('responsible_for_request')->andReturn(true);
		$handler->shouldReceive('event_types')->andReturn(['CHECKOUT.ORDER.APPROVED']);
		$handler->shouldReceive('handle_request')->andReturn($expected_response);

		$this->logger->shouldNotReceive('error');

		$sut = $this->createEndpoint($handler);

		$response = $sut->handle_request($this->createRequest());

		$this->assertSame($expected_response, $response);
	}
}
