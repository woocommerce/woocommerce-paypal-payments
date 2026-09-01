<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use Requests_Utility_CaseInsensitiveDictionary;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\ApiClient\Entity\WebhookEvent;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookEventFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint
 */
class WebhookEndpointTest extends TestCase
{
	private $host = 'https://example.com/';

	/** @var Bearer&Mockery\MockInterface */
	private $bearer;

	/** @var WebhookFactory&Mockery\MockInterface */
	private $webhook_factory;

	/** @var WebhookEventFactory&Mockery\MockInterface */
	private $webhook_event_factory;

	/** @var LoggerInterface&Mockery\MockInterface */
	private $logger;

	public function setUp(): void
	{
		parent::setUp();

		expect('wp_json_encode')->andReturnUsing('json_encode');
		when('trailingslashit')->alias(function (string $url): string {
			return rtrim($url, '/') . '/';
		});

		$this->bearer                 = Mockery::mock(Bearer::class);
		$this->webhook_factory        = Mockery::mock(WebhookFactory::class);
		$this->webhook_event_factory  = Mockery::mock(WebhookEventFactory::class);
		$this->logger                 = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
	}

	private function createEndpoint(): WebhookEndpoint
	{
		return new WebhookEndpoint(
			$this->host,
			$this->bearer,
			$this->webhook_factory,
			$this->webhook_event_factory,
			$this->logger
		);
	}

	private function stubBearerToken(): void
	{
		$token = Mockery::mock(Token::class);
		$token->shouldReceive('token')->andReturn('bearer-token');
		$this->bearer->shouldReceive('bearer')->andReturn($token);
	}

	private function responseWithHeaders(string $body): array
	{
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll')->andReturn([]);

		return [
			'body'    => $body,
			'headers' => $headers,
		];
	}

	/**
	 * GIVEN a webhook without an id yet
	 * WHEN a new webhook is created
	 * THEN the request is sent to the webhook endpoint on the configured host
	 * AND the created webhook returned by the factory is given back to the caller
	 */
	public function test_create_sends_request_to_configured_host_and_returns_created_webhook(): void
	{
		$this->stubBearerToken();

		$hook            = new Webhook('https://mysite.com/incoming', []);
		$created_webhook = new Webhook('https://mysite.com/incoming', [], 'NEW-ID');

		$this->webhook_factory->shouldReceive('from_paypal_response')->andReturn($created_webhook);

		$response = $this->responseWithHeaders(json_encode(['id' => 'NEW-ID']));

		expect('wp_remote_get')->andReturnUsing(
			function ($url, $args) use ($response) {
				if ($url !== $this->host . 'v1/notifications/webhooks') {
					return false;
				}
				if ($args['method'] !== 'POST') {
					return false;
				}
				if ($args['headers']['Authorization'] !== 'Bearer bearer-token') {
					return false;
				}

				return $response;
			}
		);
		expect('is_wp_error')->with($response)->andReturn(false);
		expect('wp_remote_retrieve_response_code')->with($response)->andReturn(201);

		$testee = $this->createEndpoint();
		$result = $testee->create($hook);

		$this->assertSame($created_webhook, $result);
	}

	/**
	 * GIVEN a webhook that already has an id assigned
	 * WHEN create() is called again for that webhook
	 * THEN no request is made and the same webhook instance is returned unchanged
	 */
	public function test_create_returns_existing_webhook_without_request_when_already_created(): void
	{
		$hook = new Webhook('https://mysite.com/incoming', [], 'EXISTING-ID');

		$testee = $this->createEndpoint();
		$result = $testee->create($hook);

		$this->assertSame($hook, $result);
	}

	/**
	 * GIVEN a PayPal API that reports webhooks registered for the current auth token
	 * WHEN the webhook list is requested
	 * THEN the webhooks returned by the factory are given back to the caller
	 */
	public function test_list_returns_webhooks_from_factory(): void
	{
		$this->stubBearerToken();

		$webhook = new Webhook('https://mysite.com/incoming', [], 'ID-1');
		$this->webhook_factory->shouldReceive('from_paypal_response')->andReturn($webhook);

		$response = $this->responseWithHeaders(json_encode(['webhooks' => [new \stdClass()]]));

		expect('wp_remote_get')->andReturnUsing(
			function ($url, $args) use ($response) {
				if ($url !== $this->host . 'v1/notifications/webhooks') {
					return false;
				}
				if ($args['method'] !== 'GET') {
					return false;
				}

				return $response;
			}
		);
		expect('is_wp_error')->with($response)->andReturn(false);
		expect('wp_remote_retrieve_response_code')->with($response)->andReturn(200);

		$testee = $this->createEndpoint();
		$result = $testee->list();

		$this->assertSame([$webhook], $result);
	}

	/**
	 * GIVEN a PayPal API that fails to return the webhook list
	 * WHEN the webhook list is requested
	 * THEN a RuntimeException is thrown
	 */
	public function test_list_throws_when_request_fails(): void
	{
		$this->stubBearerToken();

		$response = $this->responseWithHeaders('');

		expect('wp_remote_get')->andReturn($response);
		expect('is_wp_error')->with($response)->andReturn(true);

		$this->expectException(RuntimeException::class);

		$testee = $this->createEndpoint();
		$testee->list();
	}

	/**
	 * GIVEN a webhook that has already been persisted with an id
	 * WHEN the webhook is deleted
	 * THEN the delete request is sent for that webhook's id on the configured host
	 */
	public function test_delete_sends_request_for_webhook_id(): void
	{
		$this->stubBearerToken();

		$hook = new Webhook('https://mysite.com/incoming', [], 'ID-TO-DELETE');

		$response = $this->responseWithHeaders('');

		expect('wp_remote_get')->andReturnUsing(
			function ($url, $args) use ($response) {
				if ($url !== $this->host . 'v1/notifications/webhooks/ID-TO-DELETE') {
					return false;
				}
				if ($args['method'] !== 'DELETE') {
					return false;
				}

				return $response;
			}
		);
		expect('is_wp_error')->with($response)->andReturn(false);
		expect('wp_remote_retrieve_response_code')->with($response)->andReturn(204);

		$testee = $this->createEndpoint();
		$testee->delete($hook);

		$this->addToAssertionCount(1);
	}

	/**
	 * GIVEN a webhook that was never persisted (no id)
	 * WHEN delete() is called for that webhook
	 * THEN no request is made
	 */
	public function test_delete_does_nothing_when_webhook_has_no_id(): void
	{
		$hook = new Webhook('https://mysite.com/incoming', []);

		$testee = $this->createEndpoint();
		$testee->delete($hook);

		$this->addToAssertionCount(1);
	}

	/**
	 * GIVEN a PayPal API that rejects the delete request with a non-204 status
	 * WHEN the webhook is deleted
	 * THEN a PayPalApiException is thrown
	 */
	public function test_delete_throws_paypal_api_exception_on_unexpected_status(): void
	{
		$this->stubBearerToken();

		$hook = new Webhook('https://mysite.com/incoming', [], 'ID-TO-DELETE');

		$response = $this->responseWithHeaders(json_encode(['message' => 'not found']));

		expect('wp_remote_get')->andReturn($response);
		expect('is_wp_error')->with($response)->andReturn(false);
		expect('wp_remote_retrieve_response_code')->with($response)->andReturn(404);

		$this->expectException(PayPalApiException::class);

		$testee = $this->createEndpoint();
		$testee->delete($hook);
	}

	/**
	 * GIVEN a webhook subscription and an event type to simulate
	 * WHEN a simulated webhook event is requested
	 * THEN the resulting event returned by the factory is given back to the caller
	 */
	public function test_simulate_returns_event_from_factory(): void
	{
		$this->stubBearerToken();

		$hook  = new Webhook('https://mysite.com/incoming', [], 'ID-1');
		$event = Mockery::mock(WebhookEvent::class);
		$this->webhook_event_factory->shouldReceive('from_paypal_response')->andReturn($event);

		$response = $this->responseWithHeaders(json_encode(['id' => 'EVENT-1']));

		expect('wp_remote_get')->andReturnUsing(
			function ($url, $args) use ($response) {
				if ($url !== $this->host . 'v1/notifications/simulate-event') {
					return false;
				}
				if ($args['method'] !== 'POST') {
					return false;
				}

				return $response;
			}
		);
		expect('is_wp_error')->with($response)->andReturn(false);
		expect('wp_remote_retrieve_response_code')->with($response)->andReturn(202);

		$testee = $this->createEndpoint();
		$result = $testee->simulate($hook, 'CHECKOUT.ORDER.APPROVED', null);

		$this->assertSame($event, $result);
	}

	/**
	 * GIVEN a PayPal API that reports the webhook signature as successfully verified
	 * WHEN the event is verified
	 * THEN the verification returns true
	 *
	 * GIVEN a PayPal API that reports the webhook signature as failed
	 * WHEN the event is verified
	 * THEN the verification returns false
	 *
	 * @dataProvider verification_status_provider
	 */
	public function test_verify_event_reflects_paypal_verification_status(string $verification_status, bool $expected): void
	{
		$this->stubBearerToken();

		$response = $this->responseWithHeaders(json_encode(['verification_status' => $verification_status]));

		expect('wp_remote_get')->andReturnUsing(
			function ($url, $args) use ($response) {
				if ($url !== $this->host . 'v1/notifications/verify-webhook-signature') {
					return false;
				}
				if ($args['method'] !== 'POST') {
					return false;
				}

				return $response;
			}
		);
		expect('is_wp_error')->with($response)->andReturn(false);

		$testee = $this->createEndpoint();
		$result = $testee->verify_event(
			'SHA256withRSA',
			'https://api.paypal.com/cert',
			'transmission-id',
			'transmission-sig',
			'transmission-time',
			'WEBHOOK-ID',
			new \stdClass()
		);

		$this->assertSame($expected, $result);
	}

	public function verification_status_provider(): array
	{
		return [
			'success status verifies the event' => ['SUCCESS', true],
			'failure status rejects the event'  => ['FAILURE', false],
		];
	}

	/**
	 * GIVEN a PayPal API that cannot be reached to verify the webhook event
	 * WHEN the event is verified
	 * THEN a RuntimeException is thrown
	 */
	public function test_verify_event_throws_when_request_fails(): void
	{
		$this->stubBearerToken();

		$response = $this->responseWithHeaders('');

		expect('wp_remote_get')->andReturn($response);
		expect('is_wp_error')->with($response)->andReturn(true);

		$this->expectException(RuntimeException::class);

		$testee = $this->createEndpoint();
		$testee->verify_event(
			'SHA256withRSA',
			'https://api.paypal.com/cert',
			'transmission-id',
			'transmission-sig',
			'transmission-time',
			'WEBHOOK-ID',
			new \stdClass()
		);
	}
}
