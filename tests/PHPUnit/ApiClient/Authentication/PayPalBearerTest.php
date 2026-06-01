<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use Requests_Utility_CaseInsensitiveDictionary;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use Psr\Log\LoggerInterface;
use Mockery;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class PayPalBearerTest extends TestCase
{
    private function settings(string $clientId = 'key', string $clientSecret = 'secret'): SettingsProvider
    {
        $settings = Mockery::mock(SettingsProvider::class);
        $settings->shouldReceive('merchant_data')->andReturn(new MerchantConnectionDTO(
            true,
            $clientId,
            $clientSecret,
            '123'
        ));

        return $settings;
    }

    private function headers()
    {
        $headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
        $headers->shouldReceive('getAll');

        return $headers;
    }

	public function testDefault()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $json = '{"access_token":"abc","expires_in":100, "created":' . time() . '}';
        $cache = Mockery::mock(Cache::class);
        $cache
            ->expects('get')
            ->andReturn('{"access_token":"abc","expires_in":100, "created":100}');
        $cache
            ->expects('set');
        $host = 'https://example.com';
        $key = 'key';
        $secret = 'secret';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug');
        $settings = $this->settings();
        $headers = $this->headers();
        $rateLimiter = Mockery::mock(TokenRateLimiter::class);
        $rateLimiter->shouldReceive('retry_after_seconds')->andReturn(null);
        $rateLimiter->shouldReceive('clear');

        $bearer = new PayPalBearer($cache, $host, $key, $secret, $logger, $settings, $rateLimiter);

        expect('trailingslashit')
            ->with($host)
            ->andReturn($host . '/');
        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($json, $key, $secret, $host, $headers) {
                    if ($url !== $host . '/v1/oauth2/token?grant_type=client_credentials') {
                        return false;
                    }
                    if ($args['method'] !== 'POST') {
                        return false;
                    }
                    if ($args['headers']['Authorization'] !== 'Basic ' . base64_encode($key . ':' . $secret)) {
                        return false;
                    }

                    return [
                        'body' => $json,
						'headers' => $headers
                    ];
                }
            );
        expect('is_wp_error')
            ->andReturn(false);
        expect('wp_remote_retrieve_response_code')
            ->andReturn(200);

        $token = $bearer->bearer();
        $this->assertEquals("abc", $token->token());
        $this->assertTrue($token->is_valid());
    }

    public function testNoTokenCached()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $json = '{"access_token":"abc","expires_in":100, "created":' . time() . '}';
        $cache = Mockery::mock(Cache::class);
        $cache
            ->expects('get')
            ->andReturn('');
        $cache
            ->expects('set');
        $host = 'https://example.com';
        $key = 'key';
        $secret = 'secret';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug');
	    $settings = $this->settings();
		$headers = $this->headers();
        $rateLimiter = Mockery::mock(TokenRateLimiter::class);
        $rateLimiter->shouldReceive('retry_after_seconds')->andReturn(null);
        $rateLimiter->shouldReceive('clear');

        $bearer = new PayPalBearer($cache, $host, $key, $secret, $logger, $settings, $rateLimiter);

        expect('trailingslashit')
            ->with($host)
            ->andReturn($host . '/');
        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($json, $key, $secret, $host, $headers) {
                    if ($url !== $host . '/v1/oauth2/token?grant_type=client_credentials') {
                        return false;
                    }
                    if ($args['method'] !== 'POST') {
                        return false;
                    }
                    if ($args['headers']['Authorization'] !== 'Basic ' . base64_encode($key . ':' . $secret)) {
                        return false;
                    }

                    return [
                        'body' => $json,
						'headers' => $headers,
                    ];
                }
            );
        expect('is_wp_error')
            ->andReturn(false);
        expect('wp_remote_retrieve_response_code')
            ->andReturn(200);

        $token = $bearer->bearer();
        $this->assertEquals("abc", $token->token());
        $this->assertTrue($token->is_valid());
    }

    public function testCachedTokenIsStillValid()
    {
        $json = '{"access_token":"abc","expires_in":100, "created":' . time() . '}';
        $cache = Mockery::mock(Cache::class);
        $cache
            ->expects('get')
            ->andReturn($json);
        $host = 'https://example.com';
        $key = 'key';
        $secret = 'secret';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('warning');
	    $settings = $this->settings();
        // A valid cached token must never touch the network or the rate limiter.
        $rateLimiter = Mockery::mock(TokenRateLimiter::class);

        $bearer = new PayPalBearer($cache, $host, $key, $secret, $logger, $settings, $rateLimiter);

        expect('wp_remote_get')->never();

        $token = $bearer->bearer();
        $this->assertEquals("abc", $token->token());
        $this->assertTrue($token->is_valid());
    }

    public function testExceptionThrownOnError()
    {
        $json = '{"access_token":"abc","expires_in":100, "created":' . time() . '}';
        $cache = Mockery::mock(Cache::class);
        $cache
            ->expects('get')
            ->andReturn('');
        $host = 'https://example.com';
        $key = 'key';
        $secret = 'secret';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning');
        $logger->shouldReceive('debug');
	    $settings = $this->settings();
		$headers = $this->headers();
        $rateLimiter = Mockery::mock(TokenRateLimiter::class);
        $rateLimiter->shouldReceive('retry_after_seconds')->andReturn(null);
        $rateLimiter->shouldReceive('register_failure')->with('bearer', 0, Mockery::any());

        $bearer = new PayPalBearer($cache, $host, $key, $secret, $logger, $settings, $rateLimiter);

        expect('trailingslashit')
            ->with($host)
            ->andReturn($host . '/');
        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($json, $key, $secret, $host, $headers) {
                    if ($url !== $host . '/v1/oauth2/token?grant_type=client_credentials') {
                        return false;
                    }
                    if ($args['method'] !== 'POST') {
                        return false;
                    }
                    if ($args['headers']['Authorization'] !== 'Basic ' . base64_encode($key . ':' . $secret)) {
                        return false;
                    }

                    return [
                        'body' => $json,
						'headers' => $headers,
                    ];
                }
            );
        expect('is_wp_error')
            ->andReturn(true);

        $this->expectException(RuntimeException::class);
        $bearer->bearer();
    }

    public function testExceptionThrownBecauseOfHttpStatusCode()
    {
        $json = '{"access_token":"abc","expires_in":100, "created":' . time() . '}';
        $cache = Mockery::mock(Cache::class);
        $cache
            ->expects('get')
            ->andReturn('');
        $host = 'https://example.com';
        $key = 'key';
        $secret = 'secret';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning');
        $logger->shouldReceive('debug');
	    $settings = $this->settings();
		$headers = $this->headers();
        $rateLimiter = Mockery::mock(TokenRateLimiter::class);
        $rateLimiter->shouldReceive('retry_after_seconds')->andReturn(null);
        $rateLimiter->shouldReceive('register_failure')->with('bearer', 500, Mockery::any());

        $bearer = new PayPalBearer($cache, $host, $key, $secret, $logger, $settings, $rateLimiter);

        expect('trailingslashit')
            ->with($host)
            ->andReturn($host . '/');
        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($json, $key, $secret, $host, $headers) {
                    if ($url !== $host . '/v1/oauth2/token?grant_type=client_credentials') {
                        return false;
                    }
                    if ($args['method'] !== 'POST') {
                        return false;
                    }
                    if ($args['headers']['Authorization'] !== 'Basic ' . base64_encode($key . ':' . $secret)) {
                        return false;
                    }

                    return [
                        'body' => $json,
						'headers' => $headers,
                    ];
                }
            );
        expect('is_wp_error')
            ->andReturn(false);
        expect('wp_remote_retrieve_response_code')
            ->andReturn(500);

        $this->expectException(RuntimeException::class);
        $bearer->bearer();
    }

    public function testNoRequestWhileInCooldown()
    {
        $cache = Mockery::mock(Cache::class);
        $cache->expects('get')->andReturn('');
        $host = 'https://example.com';
        $logger = Mockery::mock(LoggerInterface::class);
        $settings = $this->settings();
        $rateLimiter = Mockery::mock(TokenRateLimiter::class);
        $rateLimiter->shouldReceive('retry_after_seconds')->with('bearer')->andReturn(120);

        $bearer = new PayPalBearer($cache, $host, 'key', 'secret', $logger, $settings, $rateLimiter);

        expect('wp_remote_get')->never();

        $this->expectException(RuntimeException::class);
        $bearer->bearer();
    }

    /**
     * A failing refresh of an expired-but-cached token must request a token only
     * ONCE. The previous implementation called newBearer() in both the try and
     * the catch, producing two requests per call.
     */
    public function testNoDoubleFetch()
    {
        $expired = '{"access_token":"abc","expires_in":100, "created":' . (time() - 1000) . '}';
        $cache = Mockery::mock(Cache::class);
        $cache->expects('get')->andReturn($expired);
        $host = 'https://example.com';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning');
        $logger->shouldReceive('debug');
        $settings = $this->settings();
        $headers = $this->headers();
        $rateLimiter = Mockery::mock(TokenRateLimiter::class);
        $rateLimiter->shouldReceive('retry_after_seconds')->andReturn(null);
        $rateLimiter->shouldReceive('register_failure')->with('bearer', 500, Mockery::any());

        $bearer = new PayPalBearer($cache, $host, 'key', 'secret', $logger, $settings, $rateLimiter);

        expect('trailingslashit')->andReturn($host . '/');
        expect('is_wp_error')->andReturn(false);
        expect('wp_remote_retrieve_response_code')->andReturn(500);
        // Exactly one HTTP request despite the failure.
        expect('wp_remote_get')
            ->once()
            ->andReturn(['body' => '{}', 'headers' => $headers]);

        $this->expectException(RuntimeException::class);
        $bearer->bearer();
    }

	public function testEmptyCredentialsShortCircuit()
	{
		$cache = Mockery::mock(Cache::class);
		$cache->expects('get')->andReturn('');
		$host = 'https://example.com';
		$logger = Mockery::mock(LoggerInterface::class);
		$settings = $this->settings('', '');
		// Nothing on the rate limiter should be called.
		$rateLimiter = Mockery::mock(TokenRateLimiter::class);

		$bearer = new PayPalBearer($cache, $host, '', '', $logger, $settings, $rateLimiter);

		expect('wp_remote_get')->never();

		$this->expectException(RuntimeException::class);
		$bearer->bearer();
	}

    public function testSuccessClearsCooldown()
    {
        expect('wp_json_encode')->andReturnUsing('json_encode');
        $json = '{"access_token":"abc","expires_in":100, "created":' . time() . '}';
        $cache = Mockery::mock(Cache::class);
        $cache->expects('get')->andReturn('');
        $cache->expects('set');
        $host = 'https://example.com';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('debug');
        $settings = $this->settings();
        $headers = $this->headers();
        $rateLimiter = Mockery::mock(TokenRateLimiter::class);
        $rateLimiter->shouldReceive('retry_after_seconds')->andReturn(null);
        $rateLimiter->expects('clear')->with('bearer');

        $bearer = new PayPalBearer($cache, $host, 'key', 'secret', $logger, $settings, $rateLimiter);

        expect('trailingslashit')->andReturn($host . '/');
        expect('is_wp_error')->andReturn(false);
        expect('wp_remote_retrieve_response_code')->andReturn(200);
        expect('wp_remote_get')->andReturn(['body' => $json, 'headers' => $headers]);

        $token = $bearer->bearer();
        $this->assertEquals('abc', $token->token());
    }
}
