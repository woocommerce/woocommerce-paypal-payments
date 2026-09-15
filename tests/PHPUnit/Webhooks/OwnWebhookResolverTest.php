<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Webhooks\OwnWebhookResolver
 */
class OwnWebhookResolverTest extends TestCase
{
	/** @var IncomingWebhookEndpoint&Mockery\MockInterface */
	private $incoming_webhook_endpoint;

	public function setUp(): void
	{
		parent::setUp();

		when('wp_parse_url')->alias('parse_url');

		// Default: no webhook was previously stored for this install; individual
		// tests override this when exercising the stored-id matching path.
		when('get_option')->justReturn([]);

		$this->incoming_webhook_endpoint = Mockery::mock(IncomingWebhookEndpoint::class);
	}

	private function createResolver(): OwnWebhookResolver
	{
		return new OwnWebhookResolver($this->incoming_webhook_endpoint);
	}

	/**
	 * GIVEN webhook URLs that only differ by host case, a trailing slash, a port, a
	 * query string, or the scheme
	 * WHEN computing their identity
	 * THEN all variants normalize to the same identity
	 *
	 * @dataProvider identity_normalization_provider
	 */
	public function test_identity_normalizes_url_variants(string $url): void
	{
		$this->assertSame(
			'mysite.com/wp-json/paypal/v1/incoming',
			$this->createResolver()->identity($url)
		);
	}

	public function identity_normalization_provider(): array
	{
		return [
			'plain'                => ['https://mysite.com/wp-json/paypal/v1/incoming'],
			'host case differs'    => ['https://MySite.com/wp-json/paypal/v1/incoming'],
			'trailing slash'       => ['https://mysite.com/wp-json/paypal/v1/incoming/'],
			'port is ignored'      => ['https://mysite.com:8080/wp-json/paypal/v1/incoming'],
			'query string ignored' => ['https://mysite.com/wp-json/paypal/v1/incoming?token=abc'],
			'scheme is ignored'    => ['http://mysite.com/wp-json/paypal/v1/incoming'],
		];
	}

	/**
	 * GIVEN a URL that cannot be parsed into a host
	 * WHEN computing its identity
	 * THEN an empty string is returned
	 *
	 * @dataProvider unparseable_url_provider
	 */
	public function test_identity_returns_empty_string_for_unparseable_url(string $url): void
	{
		$resolver = $this->createResolver();

		$this->assertSame('', $resolver->identity($url));
	}

	public function unparseable_url_provider(): array
	{
		return [
			'not a valid URL' => ['not-a-valid-url'],
			'empty string'    => [''],
		];
	}

	/**
	 * GIVEN this install previously stored a webhook id, and the webhook's host has since
	 * changed (e.g. an NGROK_HOST rotation or domain migration)
	 * WHEN checking whether a webhook with that stored id belongs to this install
	 * THEN it is recognized as ours despite the host mismatch
	 */
	public function test_is_own_true_when_stored_id_matches_despite_host_change(): void
	{
		when('get_option')->justReturn([
			'id'  => 'STORED_ID',
			'url' => 'https://old-host.com/wp-json/paypal/v1/incoming',
		]);

		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://new-host.com/wp-json/paypal/v1/incoming');

		$webhook = new Webhook('https://old-host.com/wp-json/paypal/v1/incoming', [], 'STORED_ID');

		$this->assertTrue($this->createResolver()->is_own($webhook));
	}

	/**
	 * GIVEN no webhook id was previously stored for this install
	 * WHEN checking whether a webhook whose URL matches this install's incoming endpoint host+path belongs to it
	 * THEN it is recognized as ours
	 */
	public function test_is_own_true_on_url_identity_match_with_no_stored_id(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');

		$webhook = new Webhook('https://mysite.com/wp-json/paypal/v1/incoming', [], 'SOME_ID');

		$this->assertTrue($this->createResolver()->is_own($webhook));
	}

	/**
	 * GIVEN a webhook registered on the same host as this install but under a different path
	 * (e.g. sibling subdirectory installs)
	 * WHEN checking whether it belongs to this install
	 * THEN it is treated as foreign
	 */
	public function test_is_own_false_for_same_host_different_path(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://example.com/shop/wp-json/paypal/v1/incoming');

		$webhook = new Webhook('https://example.com/staging/wp-json/paypal/v1/incoming', [], 'SIBLING');

		$this->assertFalse($this->createResolver()->is_own($webhook));
	}

	/**
	 * GIVEN a webhook registered for a completely different host
	 * WHEN checking whether it belongs to this install
	 * THEN it is treated as foreign
	 */
	public function test_is_own_false_for_foreign_host(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');

		$webhook = new Webhook('https://other-clone.com/wp-json/paypal/v1/incoming', [], 'FOREIGN');

		$this->assertFalse($this->createResolver()->is_own($webhook));
	}

	/**
	 * GIVEN a webhook whose URL has no parseable host
	 * WHEN checking whether it belongs to this install
	 * THEN it is treated as foreign
	 */
	public function test_is_own_false_for_unparseable_webhook_url(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');

		$webhook = new Webhook('not-a-valid-url', [], 'BROKEN');

		$this->assertFalse($this->createResolver()->is_own($webhook));
	}

	/**
	 * GIVEN a foreign webhook is listed FIRST and this install's own webhook is listed SECOND
	 * WHEN finding this install's own webhook in the list
	 * THEN the own webhook is still returned regardless of its position
	 *
	 * Regression case for PCP-6885: the previous code took list()[0], so a foreign
	 * webhook returned first on the PayPal app hid the own webhook entirely.
	 */
	public function test_find_own_returns_own_webhook_when_foreign_webhook_is_listed_first(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');

		$foreign_webhook = new Webhook('https://other-clone.com/wp-json/paypal/v1/incoming', [], 'FOREIGN');
		$own_webhook     = new Webhook('https://mysite.com/wp-json/paypal/v1/incoming', [], 'OWN');

		$found = $this->createResolver()->find_own([$foreign_webhook, $own_webhook]);

		$this->assertNotNull($found);
		$this->assertSame('OWN', $found->id());
	}

	/**
	 * GIVEN a list of webhooks none of which belong to this install
	 * WHEN finding this install's own webhook
	 * THEN null is returned
	 */
	public function test_find_own_returns_null_when_no_webhook_matches(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');

		$foreign_webhook = new Webhook('https://other-clone.com/wp-json/paypal/v1/incoming', [], 'FOREIGN');

		$this->assertNull($this->createResolver()->find_own([$foreign_webhook]));
	}

	/**
	 * GIVEN no webhooks are registered on PayPal's side
	 * WHEN finding this install's own webhook
	 * THEN null is returned
	 */
	public function test_find_own_returns_null_for_empty_list(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');

		$this->assertNull($this->createResolver()->find_own([]));
	}

	/**
	 * GIVEN a webhook list contaminated with non-Webhook values
	 * WHEN finding this install's own webhook
	 * THEN the malformed entries are skipped and the actual own webhook is still found
	 */
	public function test_find_own_skips_non_webhook_values(): void
	{
		$this->incoming_webhook_endpoint->shouldReceive('url')
			->andReturn('https://mysite.com/wp-json/paypal/v1/incoming');

		$own_webhook = new Webhook('https://mysite.com/wp-json/paypal/v1/incoming', [], 'OWN');

		$found = $this->createResolver()->find_own([new \stdClass(), 'a-string', $own_webhook]);

		$this->assertNotNull($found);
		$this->assertSame('OWN', $found->id());
	}
}
