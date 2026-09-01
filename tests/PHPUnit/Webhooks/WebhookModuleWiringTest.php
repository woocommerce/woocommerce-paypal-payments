<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ModularTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

/**
 * Regression coverage for PCP-174: webhook registration runs synchronously right after
 * ConnectionState::connect(), so 'api.host', 'api.endpoint.webhook' and 'webhook.registrar'
 * must resolve fresh on every container call instead of being cached as singletons -
 * otherwise a stale, pre-connect host/instance would be reused for the webhook call that
 * immediately follows.
 */
class WebhookModuleWiringTest extends ModularTestCase
{
	/**
	 * GIVEN the app container is booted with the merchant on the production environment
	 * WHEN the environment switches to sandbox mid-request (as ConnectionState::connect()
	 * does) and 'api.host' is resolved before and after that switch
	 * THEN the two resolutions differ, proving 'api.host' is read fresh on every call
	 * instead of returning a host cached from before the switch
	 */
	public function test_api_host_reflects_environment_changes_mid_request(): void
	{
		$environment = new Environment( false );

		$container = $this->bootstrapModule(
			array(
				'settings.environment' => function () use ( $environment ) {
					return $environment;
				},
			)
		);

		$host_before_connect = $container->get( 'api.host' );

		$environment->set_environment( true );

		$host_after_connect = $container->get( 'api.host' );

		$this->assertNotSame( $host_before_connect, $host_after_connect );
	}

	/**
	 * GIVEN the app container is booted
	 * WHEN 'api.endpoint.webhook' is resolved twice
	 * THEN each call returns a distinct WebhookEndpoint instance, proving the key is wired
	 * as a factory rather than memoized as a singleton service
	 */
	public function test_webhook_endpoint_is_not_memoized_across_container_calls(): void
	{
		$container = $this->bootstrapModule();

		$first  = $container->get( 'api.endpoint.webhook' );
		$second = $container->get( 'api.endpoint.webhook' );

		$this->assertInstanceOf( WebhookEndpoint::class, $first );
		$this->assertNotSame( $first, $second );
	}

	/**
	 * GIVEN the app container is booted
	 * WHEN 'webhook.registrar' is resolved twice
	 * THEN each call returns a distinct WebhookRegistrar instance, proving the key is wired
	 * as a factory rather than memoized as a singleton service
	 */
	public function test_webhook_registrar_is_not_memoized_across_container_calls(): void
	{
		$container = $this->bootstrapModule();

		$first  = $container->get( 'webhook.registrar' );
		$second = $container->get( 'webhook.registrar' );

		$this->assertInstanceOf( WebhookRegistrar::class, $first );
		$this->assertNotSame( $first, $second );
	}

	/**
	 * GIVEN the app container is booted
	 * WHEN sibling endpoints that also depend on 'api.host' but were deliberately left wired
	 * as singleton services are resolved twice
	 * THEN each call returns the same cached instance, guarding against the factory
	 * treatment accidentally widening to endpoints that don't need per-call freshness
	 *
	 * @dataProvider sibling_singleton_endpoint_provider
	 */
	public function test_sibling_api_host_dependent_endpoints_remain_memoized( string $container_id, string $expected_class ): void
	{
		$container = $this->bootstrapModule();

		$first  = $container->get( $container_id );
		$second = $container->get( $container_id );

		$this->assertInstanceOf( $expected_class, $first );
		$this->assertSame( $first, $second );
	}

	public function sibling_singleton_endpoint_provider(): array
	{
		return array(
			'partners endpoint stays a singleton'       => array( 'api.endpoint.partners', PartnersEndpoint::class ),
			'payment tokens endpoint stays a singleton' => array( 'api.endpoint.payment-tokens', PaymentTokensEndpoint::class ),
		);
	}
}
