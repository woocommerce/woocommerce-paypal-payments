<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use Mockery;
use Psr\Log\LoggerInterface;
use Requests_Utility_CaseInsensitiveDictionary;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class SdkClientTokenTest extends TestCase
{
    private $host;
    private $logger;
    private $credentials;
    private $cache;
    private $rateLimiter;
    private $sut;

    private const CLOCK_TOLERANCE = 5;

    private $store = [];
    private $cacheWrites = [];
    private $retryAfter = null;

    private const TEST_DOMAIN = 'shop.example.com';
    private const TEST_CACHE_KEY = SdkClientToken::CACHE_KEY . '-' . self::TEST_DOMAIN;
    private const TEST_FRESH_KEY = self::TEST_CACHE_KEY . '-fresh';

    public function setUp(): void
    {
        parent::setUp();

        $this->store = [];
        $this->cacheWrites = [];
        $this->retryAfter = null;

        when('home_url')->justReturn('https://' . self::TEST_DOMAIN);
        when('wp_parse_url')->alias('parse_url');

        $this->host = 'https://example.com';
        $this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
        $this->credentials = Mockery::mock(ClientCredentials::class);
        $this->credentials->shouldReceive('is_empty')->andReturn(false)->byDefault();
        $this->credentials->shouldReceive('credentials')->andReturn('Basic xxx');

        $this->cache = Mockery::mock(Cache::class);
        $this->cache->shouldReceive('get')->andReturnUsing(function ($key) {
            return $this->store[$key] ?? false;
        });
        $this->cache->shouldReceive('set')->andReturnUsing(function ($key, $value, $lifetime) {
            $this->cacheWrites[] = [$key, $value, $lifetime];
            $this->store[$key] = $value;
            return true;
        });

        $this->rateLimiter = Mockery::mock(TokenRateLimiter::class)->shouldIgnoreMissing();
        $this->rateLimiter->shouldReceive('retry_after_seconds')->andReturnUsing(function () {
            return $this->retryAfter;
        });

        $this->sut = new SdkClientToken(
            $this->host,
            $this->logger,
            $this->credentials,
            $this->cache,
            $this->rateLimiter
        );
    }

    private function headers()
    {
        $headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
        $headers->shouldReceive('getAll');

        return $headers;
    }

    private function cacheToken(string $token, bool $fresh, int $remaining = 600): void
    {
        $this->store[self::TEST_CACHE_KEY] = [
            'token' => $token,
            'expires_at' => time() + $remaining,
        ];
        if ($fresh) {
            $this->store[self::TEST_FRESH_KEY] = 1;
        }
    }

    private function paypalReturnsToken(string $token, int $expiresIn): void
    {
        expect('trailingslashit')->andReturn($this->host . '/');
        expect('wp_remote_get')->once()->andReturn([
            'body' => '{"access_token":"' . $token . '","expires_in":' . $expiresIn . '}',
            'headers' => $this->headers(),
        ]);
        expect('wp_remote_retrieve_response_code')->andReturn(200);
    }

    private function paypalFails(int $status): void
    {
        expect('trailingslashit')->andReturn($this->host . '/');
        expect('wp_remote_get')->once()->andReturn(['body' => '{"error":"failed"}', 'headers' => $this->headers()]);
        expect('wp_remote_retrieve_response_code')->andReturn($status);
    }

    /**
     * GIVEN a cached token with a fresh entry that ends in 30 seconds
     * WHEN the token is requested
     * THEN the cached token is returned without a request to PayPal
     * AND expires_in is the remaining lifetime
     */
    public function test_fresh_token_comes_from_cache_with_remaining_lifetime()
    {
        $this->cacheToken('cached-token', true, 30);

        expect('wp_remote_get')->never();

        $data =$this->sut->sdk_client_token_data();

        $this->assertSame('cached-token', $data['token']);
        $this->assertEqualsWithDelta(30, $data['expires_in'], self::CLOCK_TOLERANCE);
    }

    /**
     * GIVEN a cached token with a fresh entry
     * WHEN only the token string is requested
     * THEN just the token string is returned
     */
    public function test_sdk_client_token_returns_only_the_token_string()
    {
        $this->cacheToken('cached-token', true, 30);

        expect('wp_remote_get')->never();

        $this->assertSame('cached-token', $this->sut->sdk_client_token());
    }

    /**
     * GIVEN a cached token whose fresh entry is missing
     * WHEN the token is requested
     * THEN a new token is requested from PayPal, cached and returned
     * AND the fresh entry is already set when the request to PayPal starts, so other requests keep the old token
     */
    public function test_expired_fresh_entry_gets_a_new_token_and_holds_off_other_requests()
    {
        $this->cacheToken('old-token', false);
        $freshDuringRequest = null;

        expect('trailingslashit')->andReturn($this->host . '/');
        expect('wp_remote_get')->once()->andReturnUsing(function () use (&$freshDuringRequest) {
            $freshDuringRequest = $this->store[self::TEST_FRESH_KEY] ?? false;
            return [
                'body' => '{"access_token":"new-token","expires_in":3600}',
                'headers' => $this->headers(),
            ];
        });
        expect('wp_remote_retrieve_response_code')->andReturn(200);

        $data = $this->sut->sdk_client_token_data();

        $this->assertSame('new-token', $data['token']);
        $this->assertEqualsWithDelta(3600, $data['expires_in'], self::CLOCK_TOLERANCE);
        $this->assertNotFalse($freshDuringRequest);
        $this->assertSame('new-token', $this->store[self::TEST_CACHE_KEY]['token']);
        $this->assertEqualsWithDelta(time() + 3600, $this->store[self::TEST_CACHE_KEY]['expires_at'], self::CLOCK_TOLERANCE);
        $this->assertSame(1, $this->store[self::TEST_FRESH_KEY]);
    }

    /**
     * GIVEN a cached token with 600 seconds left and a missing fresh entry
     * WHEN PayPal fails to deliver a new token
     * THEN the cached token is returned with its real remaining lifetime
     */
    public function test_paypal_failure_during_rotation_returns_the_cached_token()
    {
        $this->cacheToken('old-token', false, 600);
        $this->paypalFails(500);

        $data = $this->sut->sdk_client_token_data();

        $this->assertSame('old-token', $data['token']);
        $this->assertEqualsWithDelta(600, $data['expires_in'], self::CLOCK_TOLERANCE);
    }

    /**
     * GIVEN a cached token with 600 seconds left and a missing fresh entry
     * AND PayPal requests are paused after a previous failure
     * WHEN the token is requested
     * THEN the cached token is returned with its real remaining lifetime without a request to PayPal
     */
    public function test_cached_token_is_returned_during_a_rate_limit_cool_down()
    {
        $this->cacheToken('old-token', false, 600);
        $this->retryAfter = 30;

        expect('wp_remote_get')->never();

        $data = $this->sut->sdk_client_token_data();

        $this->assertSame('old-token', $data['token']);
        $this->assertEqualsWithDelta(600, $data['expires_in'], self::CLOCK_TOLERANCE);
    }

    /**
     * GIVEN a cached value that is not an array with a token and an expiry
     * AND the fresh entry is still set
     * WHEN the token is requested
     * THEN the value counts as no token and a new token is requested from PayPal
     *
     * @dataProvider invalidCachedValueProvider
     */
    public function test_invalid_cached_value_counts_as_no_token($cached)
    {
        $this->store[self::TEST_CACHE_KEY] = $cached;
        $this->store[self::TEST_FRESH_KEY] = 1;
        $this->paypalReturnsToken('new-token', 3600);

        $this->assertSame('new-token', $this->sut->sdk_client_token());
    }

    public function invalidCachedValueProvider(): array
    {
        return [
            'plain string from the previous format' => ['old-token'],
            'array without expiry' => [['token' => 'old-token']],
            'array without token' => [['expires_at' => 9999999999]],
        ];
    }

    public function testEmptyCredentialsShortCircuit()
    {
        $this->credentials->shouldReceive('is_empty')->andReturn(true);

        expect('wp_remote_get')->never();

        $this->expectException(RuntimeException::class);
        $this->sut->sdk_client_token();
    }

    public function testBlockedReturnsFastWithoutRequest()
    {
        $this->retryAfter = 60;

        expect('wp_remote_get')->never();

        $this->expectException(RuntimeException::class);
        $this->sut->sdk_client_token();
    }

    public function test429RegistersFailureAndThrows()
    {
        $this->rateLimiter->expects('register_failure')->with('sdk-client-token', 429, Mockery::any());
        $this->paypalFails(429);

        $this->expectException(PayPalApiException::class);
        $this->sut->sdk_client_token();
    }

    /**
     * GIVEN no cached token
     * WHEN PayPal reports a token lifetime
     * THEN a long-lived token is cached 30 seconds short of its reported lifetime
     * AND a short-lived token (150 seconds or less) is cached unchanged
     * AND the cached data holds the token with its real expiry timestamp
     * AND the fresh entry is a marker set for 60 seconds
     * AND the token is returned with the reported lifetime
     *
     * @dataProvider cachedLifetimeProvider
     */
    public function testCachesTokenForAdjustedLifetime(int $expiresIn, int $expectedCachedLifetime)
    {
        $this->rateLimiter->expects('clear')->with('sdk-client-token');
        $this->paypalReturnsToken('tok', $expiresIn);

        $data = $this->sut->sdk_client_token_data();

        $this->assertSame('tok', $data['token']);
        $this->assertEqualsWithDelta($expiresIn, $data['expires_in'], self::CLOCK_TOLERANCE);
        $this->assertCount(2, $this->cacheWrites);
        [$key, $value, $lifetime] = $this->cacheWrites[0];
        $this->assertSame(self::TEST_CACHE_KEY, $key);
        $this->assertSame('tok', $value['token']);
        $this->assertEqualsWithDelta(time() + $expiresIn, $value['expires_at'], self::CLOCK_TOLERANCE);
        $this->assertSame($expectedCachedLifetime, $lifetime);
        $this->assertSame([self::TEST_FRESH_KEY, 1, 60], $this->cacheWrites[1]);
    }

    public function cachedLifetimeProvider(): array
    {
        return [
            'long-lived token cached 30 seconds short of its reported lifetime' => [3600, 3570],
            'short-lived token at the 150 second boundary cached unchanged' => [150, 150],
            'short-lived token below the boundary cached unchanged' => [60, 60],
        ];
    }

    public function testRetriesOnceOnConnectionError()
    {
        $this->rateLimiter->expects('clear')->with('sdk-client-token');
        // A blip that recovers on retry must NOT arm the cool-down.
        $this->rateLimiter->shouldNotReceive('register_failure');

        $wpError = Mockery::mock('WP_Error');
        $wpError->shouldReceive('get_error_messages')->andReturn(['blip']);
        expect('trailingslashit')->andReturn($this->host . '/');
        // First attempt: connection error; retry: success.
        expect('wp_remote_get')->twice()->andReturn(
            $wpError,
            ['body' => '{"access_token":"tok","expires_in":3600}', 'headers' => $this->headers()]
        );
        expect('wp_remote_retrieve_response_code')->andReturn(200);

        $this->assertSame('tok', $this->sut->sdk_client_token());
        $this->assertSame(3570, $this->cacheWrites[0][2]);
    }
}
