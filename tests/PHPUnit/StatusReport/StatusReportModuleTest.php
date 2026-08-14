<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\StatusReport;

use Mockery;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use function Brain\Monkey\Functions\expect;
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

		if (!defined('MINUTE_IN_SECONDS')) {
			define('MINUTE_IN_SECONDS', 60);
		}

		$this->sut = new StatusReportModule();
	}

	private function webhook_delivery_host_status(array $registered_webhooks): string
	{
		$method = new ReflectionMethod(StatusReportModule::class, 'webhook_delivery_host_status');
		$method->setAccessible(true);

		return $method->invoke($this->sut, $registered_webhooks);
	}

	private function registered_webhooks(ContainerInterface $c, bool $is_connected): array
	{
		$method = new ReflectionMethod(StatusReportModule::class, 'registered_webhooks');
		$method->setAccessible(true);

		return $method->invoke($this->sut, $c, $is_connected);
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

		$result = $this->webhook_delivery_host_status([
			new Webhook('https://other-clone.com/wp-json/paypal/v1/incoming', [], 'FOREIGN'),
			new Webhook('https://MySite.com/wp-json/paypal/v1/incoming', [], 'OWN'),
		]);

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

		$result = $this->webhook_delivery_host_status([
			new Webhook('https://other-clone.com/wp-json/paypal/v1/incoming', [], 'FOREIGN'),
		]);

		$this->assertStringContainsString('<mark class="error">', $result);
		$this->assertStringContainsString('Delivered to other-clone.com (this site: mysite.com)', $result);
	}

	/**
	 * GIVEN the registered webhooks list contains only malformed entries that are not
	 * Webhook instances (e.g. a stray container value leaked into the list)
	 * WHEN determining the webhook delivery host status
	 * THEN the row shows the "not applicable" indicator instead of erroring out
	 */
	public function test_returns_dash_when_list_contains_only_non_webhook_items(): void
	{
		when('home_url')->justReturn('https://mysite.com');

		$result = $this->webhook_delivery_host_status([new \stdClass(), 'a-string']);

		$this->assertSame('<mark class="no">&ndash;</mark>', $result);
	}

	/**
	 * GIVEN a registered webhook whose URL has no parseable host
	 * WHEN determining the webhook delivery host status
	 * THEN the unparseable webhook is ignored and the row shows the "not applicable" indicator
	 */
	public function test_returns_dash_when_webhooks_have_unparseable_hosts(): void
	{
		when('home_url')->justReturn('https://mysite.com');

		$result = $this->webhook_delivery_host_status([
			new Webhook('not a url', [], 'BROKEN'),
			new Webhook('', [], 'EMPTY'),
		]);

		$this->assertSame('<mark class="no">&ndash;</mark>', $result);
	}

	/**
	 * GIVEN no webhooks are registered on PayPal's side
	 * WHEN determining the webhook delivery host status
	 * THEN the row shows the "not applicable" indicator
	 */
	public function test_returns_dash_for_empty_webhook_list(): void
	{
		when('home_url')->justReturn('https://mysite.com');

		$result = $this->webhook_delivery_host_status([]);

		$this->assertSame('<mark class="no">&ndash;</mark>', $result);
	}

	/**
	 * GIVEN the store is not connected to PayPal
	 * WHEN fetching the live registered webhooks
	 * THEN an empty list is returned without querying the container
	 */
	public function test_registered_webhooks_returns_empty_array_when_not_connected(): void
	{
		$container = Mockery::mock(ContainerInterface::class);
		$container->shouldNotReceive('get');

		$result = $this->registered_webhooks($container, false);

		$this->assertSame([], $result);
	}

	/**
	 * GIVEN the registered webhooks were already fetched and cached in a transient
	 * WHEN fetching the live registered webhooks again
	 * THEN the cached value is returned without querying the container
	 */
	public function test_registered_webhooks_returns_cached_value_without_querying_container(): void
	{
		$cached = [new Webhook('https://mysite.com/wp-json/paypal/v1/incoming', [], 'CACHED')];

		when('get_transient')->justReturn($cached);

		$container = Mockery::mock(ContainerInterface::class);
		$container->shouldNotReceive('get');

		$result = $this->registered_webhooks($container, true);

		$this->assertSame($cached, $result);
	}

	/**
	 * GIVEN the container fails to resolve the live registered webhooks with an error that
	 * is not an Exception (e.g. a TypeError)
	 * WHEN fetching the live registered webhooks
	 * THEN the failure is caught and an empty list is returned instead of erroring out
	 */
	public function test_registered_webhooks_returns_empty_array_when_container_throws_a_throwable(): void
	{
		when('get_transient')->justReturn(false);

		$container = Mockery::mock(ContainerInterface::class);
		$container->shouldReceive('get')
			->with('webhook.status.registered-webhooks')
			->andThrow(new \TypeError('unexpected type'));

		$result = $this->registered_webhooks($container, true);

		$this->assertSame([], $result);
	}

	/**
	 * GIVEN the container returns a mix of Webhook instances and unrelated values
	 * WHEN fetching the live registered webhooks
	 * THEN only the Webhook instances are kept and the filtered list is cached
	 */
	public function test_registered_webhooks_filters_out_non_webhook_items_and_caches_result(): void
	{
		when('get_transient')->justReturn(false);

		$own_webhook = new Webhook('https://mysite.com/wp-json/paypal/v1/incoming', [], 'OWN');

		$container = Mockery::mock(ContainerInterface::class);
		$container->shouldReceive('get')
			->with('webhook.status.registered-webhooks')
			->andReturn([$own_webhook, new \stdClass(), 'not-a-webhook']);

		expect('set_transient')->once()->with(
			'ppcp-status-registered-webhooks',
			[$own_webhook],
			5 * MINUTE_IN_SECONDS
		);

		$result = $this->registered_webhooks($container, true);

		$this->assertSame([$own_webhook], $result);
	}
}
