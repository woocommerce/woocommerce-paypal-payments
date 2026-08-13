<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar
 */
class WebhookRegistrarTest extends TestCase
{
	/** @var WebhookFactory&Mockery\MockInterface */
	private $webhook_factory;

	/** @var WebhookEndpoint&Mockery\MockInterface */
	private $endpoint;

	/** @var IncomingWebhookEndpoint&Mockery\MockInterface */
	private $incoming_webhook_endpoint;

	/** @var WebhookEventStorage&Mockery\MockInterface */
	private $last_webhook_event_storage;

	/** @var WebhookSimulation&Mockery\MockInterface */
	private $webhook_simulation;

	/** @var WebhookOrchestrator&Mockery\MockInterface */
	private $webhook_orchestrator;

	/** @var LoggerInterface&Mockery\MockInterface */
	private $logger;

	public function setUp(): void
	{
		parent::setUp();

		when('wp_parse_url')->alias('parse_url');

		$this->webhook_factory            = Mockery::mock(WebhookFactory::class);
		$this->endpoint                   = Mockery::mock(WebhookEndpoint::class);
		$this->incoming_webhook_endpoint  = Mockery::mock(IncomingWebhookEndpoint::class);
		$this->last_webhook_event_storage = Mockery::mock(WebhookEventStorage::class);
		$this->webhook_simulation         = Mockery::mock(WebhookSimulation::class);
		$this->webhook_orchestrator       = Mockery::mock(WebhookOrchestrator::class);
		$this->logger                     = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();

		// with_lock() only wraps its callback with locking; behavior under test is the callback's result.
		$this->webhook_orchestrator
			->shouldReceive('with_lock')
			->andReturnUsing(static fn(string $name, callable $callback) => $callback());
	}

	private function createRegistrar(): WebhookRegistrar
	{
		return new WebhookRegistrar(
			$this->webhook_factory,
			$this->endpoint,
			$this->incoming_webhook_endpoint,
			$this->last_webhook_event_storage,
			$this->webhook_simulation,
			$this->webhook_orchestrator,
			$this->logger
		);
	}

	/**
	 * GIVEN a PayPal account with webhooks belonging to this site and to a different site
	 * WHEN unregistering webhooks
	 * THEN only the webhook whose host matches this site's incoming endpoint is deleted
	 * AND a warning is logged for the foreign webhook that was skipped
	 */
	public function test_unregister_deletes_only_webhooks_belonging_to_this_site(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');

		$own_webhook     = new Webhook('https://mysite.com/wp-json/paypal/v1/incoming', [], 'OWN');
		$foreign_webhook = new Webhook('https://other-clone.com/wp-json/paypal/v1/incoming', [], 'FOREIGN');

		$this->endpoint->shouldReceive('list')->andReturn([$own_webhook, $foreign_webhook]);

		$this->endpoint->shouldReceive('delete')->once()->with($own_webhook);
		$this->endpoint->shouldNotReceive('delete')->with($foreign_webhook);

		$this->logger->shouldReceive('warning')->once();

		expect('delete_option')->once()->with(WebhookRegistrar::KEY);
		$this->last_webhook_event_storage->shouldReceive('clear')->once();

		$this->createRegistrar()->unregister();
	}

	/**
	 * GIVEN a webhook whose host differs only by letter case from this site's host
	 * WHEN unregistering webhooks
	 * THEN the webhook is still recognized as belonging to this site and is deleted
	 */
	public function test_unregister_matches_host_case_insensitively(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://MySite.com/wp-json/paypal/v1/incoming');

		$webhook = new Webhook('https://mysite.com/wp-json/paypal/v1/incoming', [], 'OWN');

		$this->endpoint->shouldReceive('list')->andReturn([$webhook]);
		$this->endpoint->shouldReceive('delete')->once()->with($webhook);

		expect('delete_option')->once();
		$this->last_webhook_event_storage->shouldReceive('clear')->once();

		$this->createRegistrar()->unregister();
	}

	/**
	 * GIVEN a registered webhook whose URL has no parseable host
	 * WHEN unregistering webhooks
	 * THEN the webhook is treated as not belonging to this site and is left in place
	 */
	public function test_unregister_skips_webhook_with_unparseable_host(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');

		$webhook = new Webhook('not-a-valid-url', [], 'BROKEN');

		$this->endpoint->shouldReceive('list')->andReturn([$webhook]);
		$this->endpoint->shouldNotReceive('delete');

		expect('delete_option')->once();
		$this->last_webhook_event_storage->shouldReceive('clear')->once();

		$this->createRegistrar()->unregister();
	}

	/**
	 * GIVEN the PayPal webhook list request fails
	 * WHEN unregistering webhooks
	 * THEN the local webhook bookkeeping (deleting the stored option and clearing
	 * the last event storage) still runs despite the remote failure
	 */
	public function test_unregister_still_clears_local_state_when_listing_webhooks_fails(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');

		$this->endpoint->shouldReceive('list')->andThrow(new RuntimeException('API unavailable'));
		$this->endpoint->shouldNotReceive('delete');

		expect('delete_option')->once()->with(WebhookRegistrar::KEY);
		$this->last_webhook_event_storage->shouldReceive('clear')->once();

		$this->createRegistrar()->unregister();
	}

	/**
	 * GIVEN no webhook is currently registered for this site
	 * WHEN registering webhooks
	 * THEN a new webhook is created for this site's incoming endpoint, stored,
	 * and webhook simulation is started
	 * AND the operation reports success
	 */
	public function test_register_creates_webhook_and_reports_success(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');
		$this->incoming_webhook_endpoint->shouldReceive('handled_event_types')
			->andReturn(['CHECKOUT.ORDER.APPROVED']);

		$this->endpoint->shouldReceive('list')->andReturn([]);

		$new_webhook     = new Webhook('https://mysite.com/wp-json/paypal/v1/incoming', ['CHECKOUT.ORDER.APPROVED']);
		$created_webhook = new Webhook('https://mysite.com/wp-json/paypal/v1/incoming', ['CHECKOUT.ORDER.APPROVED'], 'NEW-ID');

		$this->webhook_factory->shouldReceive('for_url_and_events')->andReturn($new_webhook);
		$this->endpoint->shouldReceive('create')->with($new_webhook)->andReturn($created_webhook);

		expect('update_option')->once()->with(WebhookRegistrar::KEY, $created_webhook->to_array());
		expect('delete_option')->once();

		$this->last_webhook_event_storage->shouldReceive('clear')->twice();
		$this->webhook_simulation->shouldReceive('start')->once()->with($created_webhook);

		$result = $this->createRegistrar()->register();

		$this->assertTrue($result);
	}

	/**
	 * GIVEN PayPal fails to return an id for the newly created webhook
	 * WHEN registering webhooks
	 * THEN the operation reports failure
	 */
	public function test_register_reports_failure_when_created_webhook_has_no_id(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');
		$this->incoming_webhook_endpoint->shouldReceive('handled_event_types')
			->andReturn(['CHECKOUT.ORDER.APPROVED']);

		$this->endpoint->shouldReceive('list')->andReturn([]);

		$new_webhook     = new Webhook('https://mysite.com/wp-json/paypal/v1/incoming', ['CHECKOUT.ORDER.APPROVED']);
		$created_webhook = new Webhook('https://mysite.com/wp-json/paypal/v1/incoming', ['CHECKOUT.ORDER.APPROVED']);

		$this->webhook_factory->shouldReceive('for_url_and_events')->andReturn($new_webhook);
		$this->endpoint->shouldReceive('create')->with($new_webhook)->andReturn($created_webhook);

		expect('delete_option')->once();
		$this->last_webhook_event_storage->shouldReceive('clear')->once();
		$this->webhook_simulation->shouldNotReceive('start');

		$result = $this->createRegistrar()->register();

		$this->assertFalse($result);
	}
}
