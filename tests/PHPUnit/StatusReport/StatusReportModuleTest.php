<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\StatusReport;

use Mockery;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StatusReport\StatusReportModule
 */
class StatusReportModuleTest extends TestCase
{
	private StatusReportModule $sut;

	public function setUp(): void
	{
		parent::setUp();

		when('wp_parse_url')->alias('parse_url');

		$this->sut = new StatusReportModule();
	}

	private function webhook_delivery_host_status(ContainerInterface $c, bool $is_connected): string
	{
		$method = new ReflectionMethod(StatusReportModule::class, 'webhook_delivery_host_status');
		$method->setAccessible(true);

		return $method->invoke($this->sut, $c, $is_connected);
	}

	/**
	 * GIVEN the store is not connected to PayPal
	 * WHEN determining the webhook delivery host status
	 * THEN the row shows the "not applicable" indicator without querying the container
	 */
	public function test_returns_dash_when_not_connected(): void
	{
		$container = Mockery::mock(ContainerInterface::class);
		$container->shouldNotReceive('get');

		$result = $this->webhook_delivery_host_status($container, false);

		$this->assertSame('<mark class="no">&ndash;</mark>', $result);
	}

	/**
	 * GIVEN the store is connected but fetching the registered webhooks from PayPal fails
	 * WHEN determining the webhook delivery host status
	 * THEN the row shows the "not applicable" indicator instead of erroring out
	 */
	public function test_returns_dash_when_registered_webhooks_lookup_throws(): void
	{
		$container = Mockery::mock(ContainerInterface::class);
		$container->shouldReceive('get')
			->with('webhook.status.registered-webhooks')
			->andThrow(new \Exception('service not found'));

		$result = $this->webhook_delivery_host_status($container, true);

		$this->assertSame('<mark class="no">&ndash;</mark>', $result);
	}

	/**
	 * GIVEN the store is connected but PayPal reports no registered webhooks
	 * WHEN determining the webhook delivery host status
	 * THEN the row shows the "not applicable" indicator
	 *
	 * @dataProvider empty_or_invalid_webhooks_provider
	 */
	public function test_returns_dash_when_no_webhooks_are_registered($webhooks): void
	{
		$container = Mockery::mock(ContainerInterface::class);
		$container->shouldReceive('get')
			->with('webhook.status.registered-webhooks')
			->andReturn($webhooks);

		$result = $this->webhook_delivery_host_status($container, true);

		$this->assertSame('<mark class="no">&ndash;</mark>', $result);
	}

	public function empty_or_invalid_webhooks_provider(): array
	{
		return [
			'no webhooks registered at all' => [[]],
			'container returns a non-array value' => ['not-an-array'],
		];
	}

	/**
	 * GIVEN PayPal has a registered webhook whose host matches this site's host
	 * WHEN determining the webhook delivery host status
	 * THEN the row shows the "delivered here" indicator, even when other foreign
	 * webhooks are also registered on the same account
	 */
	public function test_returns_yes_when_a_registered_webhook_matches_this_site(): void
	{
		when('home_url')->justReturn('https://mysite.com');

		$container = Mockery::mock(ContainerInterface::class);
		$container->shouldReceive('get')
			->with('webhook.status.registered-webhooks')
			->andReturn([
				new Webhook('https://other-clone.com/wp-json/paypal/v1/incoming', [], 'FOREIGN'),
				new Webhook('https://MySite.com/wp-json/paypal/v1/incoming', [], 'OWN'),
			]);

		$result = $this->webhook_delivery_host_status($container, true);

		$this->assertSame('<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>', $result);
	}

	/**
	 * GIVEN PayPal only has webhooks registered for hosts other than this site
	 * WHEN determining the webhook delivery host status
	 * THEN the row reports the delivery is going to the foreign host(s) instead of this site
	 */
	public function test_reports_foreign_hosts_when_no_registered_webhook_matches_this_site(): void
	{
		when('home_url')->justReturn('https://mysite.com');

		$container = Mockery::mock(ContainerInterface::class);
		$container->shouldReceive('get')
			->with('webhook.status.registered-webhooks')
			->andReturn([
				new Webhook('https://other-clone.com/wp-json/paypal/v1/incoming', [], 'FOREIGN'),
			]);

		$result = $this->webhook_delivery_host_status($container, true);

		$this->assertStringContainsString('<mark class="error">', $result);
		$this->assertStringContainsString('Delivered to other-clone.com (this site: mysite.com)', $result);
	}
}
