<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ApiHostResolver;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

/**
 * @covers \WooCommerce\PayPalCommerce\ApiClient\Authentication\ResolvingBearer
 */
class ResolvingBearerTest extends TestCase
{
    /**
     * Builds a Cache stub whose get() returns an already-valid, non-expired
     * cached token JSON, so PayPalBearer::bearer() short-circuits on its
     * cache hit and never performs a real HTTP request.
     */
    private function cache_with_valid_token(string $access_token): Cache
    {
        $cache = Mockery::mock(Cache::class);
        $cache->shouldReceive('get')
            ->andReturn('{"access_token":"' . $access_token . '","expires_in":100, "created":' . time() . '}');

        return $cache;
    }

    private function host_resolver(): ApiHostResolver
    {
        $host_resolver = Mockery::mock(ApiHostResolver::class);
        $host_resolver->shouldReceive('host')->andReturn('https://example.com');

        return $host_resolver;
    }

    private function logger(): LoggerInterface
    {
        return Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
    }

    /**
     * GIVEN a merchant who has not completed onboarding
     * WHEN the current bearer is resolved
     * THEN the placeholder ConnectBearer token is returned instead of a real PayPal token
     */
    public function test_bearer_reflects_disconnected_state(): void
    {
        $connection_state = new ConnectionState(false, new Environment(false));

        $testee = new ResolvingBearer(
            $connection_state,
            $this->cache_with_valid_token('paypal-token'),
            $this->host_resolver(),
            'key',
            'secret',
            $this->logger(),
            null,
            Mockery::mock(TokenRateLimiter::class)
        );

        $this->assertSame('token', $testee->bearer()->token());
    }

    /**
     * GIVEN a merchant who has completed onboarding and has a valid cached PayPal token
     * WHEN the current bearer is resolved
     * THEN the cached PayPal token is returned instead of the ConnectBearer placeholder
     */
    public function test_bearer_reflects_connected_state(): void
    {
        $connection_state = new ConnectionState(true, new Environment(false));

        $testee = new ResolvingBearer(
            $connection_state,
            $this->cache_with_valid_token('paypal-token'),
            $this->host_resolver(),
            'key',
            'secret',
            $this->logger(),
            null,
            Mockery::mock(TokenRateLimiter::class)
        );

        $this->assertSame('paypal-token', $testee->bearer()->token());
    }

    /**
     * GIVEN a merchant who is not yet connected, resolved once before onboarding completes
     * WHEN the merchant's connection state switches to connected mid-request, as
     * ConnectionState::connect() does right after onboarding finishes
     * AND the bearer is resolved again on the same resolver instance
     * THEN the second resolution reflects the new connection state instead of returning a
     * bearer cached from before the switch
     */
    public function test_bearer_reflects_connection_state_changes_mid_request_without_caching(): void
    {
        $connection_state = new ConnectionState(false, new Environment(false));

        $testee = new ResolvingBearer(
            $connection_state,
            $this->cache_with_valid_token('paypal-token'),
            $this->host_resolver(),
            'key',
            'secret',
            $this->logger(),
            null,
            Mockery::mock(TokenRateLimiter::class)
        );

        $bearer_before_connect = $testee->bearer();

        $connection_state->connect(false);

        $bearer_after_connect = $testee->bearer();

        $this->assertSame('token', $bearer_before_connect->token());
        $this->assertSame('paypal-token', $bearer_after_connect->token());
        $this->assertNotSame($bearer_before_connect->token(), $bearer_after_connect->token());
    }
}
