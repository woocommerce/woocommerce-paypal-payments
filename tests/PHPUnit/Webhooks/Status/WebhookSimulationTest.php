<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Status;

use Exception;
use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\ApiClient\Entity\WebhookEvent;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class WebhookSimulationTest extends TestCase
{
	private $webhook_endpoint;

	private $webhook;

	private $event_type = 'CHECKOUT.ORDER.APPROVED';
	private $event_version = '2.0';

	private $sut;

	private $storage;

	private $event_id = '123';
	private $event;

	public function setUp(): void
	{
		parent::setUp();

		$this->webhook_endpoint = Mockery::mock(WebhookEndpoint::class);
		$this->webhook = new Webhook('https://example.com', []);

		$this->sut = new WebhookSimulation($this->webhook_endpoint, $this->webhook, $this->event_type, $this->event_version);

		when('update_option')->alias(function ($key, $value) {
			$this->storage[$key] = $value;
		});
		when('get_option')->alias(function ($key, $default = false) {
			return $this->storage[$key] ?? $default;
		});

		$this->event = $this->createEvent($this->event_id);
	}

    public function testSimulation()
    {
		$this->webhook_endpoint
			->expects('simulate')
			->with($this->webhook, $this->event_type, $this->event_version)
			->andReturn($this->event);

		$this->sut->start();

		self::assertTrue($this->sut->is_simulation_event($this->createEvent($this->event_id)));
		self::assertFalse($this->sut->is_simulation_event($this->createEvent('456')));

		self::assertFalse($this->sut->receive($this->createEvent('456')));

		self::assertEquals(WebhookSimulation::STATE_WAITING, $this->sut->get_state());

		self::assertTrue($this->sut->receive($this->createEvent($this->event_id)));
		self::assertEquals(WebhookSimulation::STATE_RECEIVED, $this->sut->get_state());
    }

    public function testIsSimulationNeverThrows()
    {
		self::assertFalse($this->sut->is_simulation_event($this->createEvent($this->event_id)));
    }

    public function testSimulationWhenNoWebhook()
    {
		$this->sut = new WebhookSimulation($this->webhook_endpoint, null, $this->event_type, $this->event_version);

		self::assertFalse($this->sut->is_simulation_event($this->createEvent($this->event_id)));

		$this->expectException(Exception::class);

		$this->sut->start();
    }

	private function createEvent(string $id): WebhookEvent
	{
		return new WebhookEvent($id, null, '', '', $this->event_type, '', '', (object) []);
	}
}
