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

class UserIdTokenTest extends TestCase
{
    private $host;
    private $logger;
    private $credentials;
    private $cache;
    private $rateLimiter;
    private $sut;

    public function setUp(): void
    {
        parent::setUp();

        when('WC')->justReturn((object) ['session' => null]);

        $this->host = 'https://example.com';
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->logger->shouldReceive('debug');
        $this->credentials = Mockery::mock(ClientCredentials::class);
        $this->cache = Mockery::mock(Cache::class);
        $this->rateLimiter = Mockery::mock(TokenRateLimiter::class);
        $this->sut = new UserIdToken(
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

    public function testEmptyCredentialsShortCircuit()
    {
        $this->credentials->shouldReceive('is_empty')->andReturn(true);

        expect('wp_remote_get')->never();

        $this->expectException(RuntimeException::class);
        $this->sut->id_token();
    }

    public function testBlockedReturnsFastWithoutRequest()
    {
        $this->credentials->shouldReceive('is_empty')->andReturn(false);
        $this->rateLimiter->shouldReceive('retry_after_seconds')->with('id-token')->andReturn(60);

        expect('wp_remote_get')->never();

        $this->expectException(RuntimeException::class);
        $this->sut->id_token();
    }

    public function test429RegistersFailureAndThrows()
    {
        $this->credentials->shouldReceive('is_empty')->andReturn(false);
        $this->credentials->shouldReceive('credentials')->andReturn('Basic xxx');
        $this->rateLimiter->shouldReceive('retry_after_seconds')->andReturn(null);
        $this->rateLimiter->expects('register_failure')->with('id-token', 429, Mockery::any());

        $headers = $this->headers();
        expect('trailingslashit')->andReturn($this->host . '/');
        expect('wp_remote_get')->once()->andReturn(['body' => '{"error":"rate"}', 'headers' => $headers]);
        expect('wp_remote_retrieve_response_code')->andReturn(429);

        $this->expectException(PayPalApiException::class);
        $this->sut->id_token();
    }

    public function testSuccess()
    {
        $this->credentials->shouldReceive('is_empty')->andReturn(false);
        $this->credentials->shouldReceive('credentials')->andReturn('Basic xxx');
        $this->rateLimiter->shouldReceive('retry_after_seconds')->andReturn(null);
        $this->rateLimiter->expects('clear')->with('id-token');

        $headers = $this->headers();
        expect('trailingslashit')->andReturn($this->host . '/');
        expect('wp_remote_get')->andReturn(['body' => '{"id_token":"idtok"}', 'headers' => $headers]);
        expect('wp_remote_retrieve_response_code')->andReturn(200);

        $this->assertSame('idtok', $this->sut->id_token());
    }

    public function testRetriesOnceOnConnectionError()
    {
        $this->credentials->shouldReceive('is_empty')->andReturn(false);
        $this->credentials->shouldReceive('credentials')->andReturn('Basic xxx');
        $this->rateLimiter->shouldReceive('retry_after_seconds')->andReturn(null);
        $this->rateLimiter->expects('clear')->with('id-token');
        // A blip that recovers on retry must NOT arm the cool-down.
        $this->rateLimiter->shouldNotReceive('register_failure');

        $headers = $this->headers();
        $wpError = Mockery::mock('WP_Error');
        $wpError->shouldReceive('get_error_messages')->andReturn(['blip']);
        expect('trailingslashit')->andReturn($this->host . '/');
        // First attempt: connection error; retry: success.
        expect('wp_remote_get')->twice()->andReturn(
            $wpError,
            ['body' => '{"id_token":"idtok"}', 'headers' => $headers]
        );
        expect('wp_remote_retrieve_response_code')->andReturn(200);

        $this->assertSame('idtok', $this->sut->id_token());
    }
}
