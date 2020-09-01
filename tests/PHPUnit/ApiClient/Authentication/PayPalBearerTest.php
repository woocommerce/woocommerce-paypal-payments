<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Authentication;

use Brain\Monkey\Expectation\Exception\ExpectationArgsRequired;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\TestCase;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Mockery;
use function Brain\Monkey\Functions\expect;

class PayPalBearerTest extends TestCase
{

	public function testDefault()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $json = '{"access_token":"abc","expires_in":100, "created":' . time() . '}';
        $cache = Mockery::mock(CacheInterface::class);
        $cache
            ->expects('get')
            ->andReturn('{"access_token":"abc","expires_in":100, "created":100}');
        $cache
            ->expects('set');
        $host = 'https://example.com';
        $key = 'key';
        $secret = 'secret';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');

        $bearer = new PayPalBearer($cache, $host, $key, $secret, $logger);

        expect('trailingslashit')
            ->with($host)
            ->andReturn($host . '/');
        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($json, $key, $secret, $host) {
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
        $cache = Mockery::mock(CacheInterface::class);
        $cache
            ->expects('get')
            ->andReturn('');
        $cache
            ->expects('set');
        $host = 'https://example.com';
        $key = 'key';
        $secret = 'secret';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');

        $bearer = new PayPalBearer($cache, $host, $key, $secret, $logger);

        expect('trailingslashit')
            ->with($host)
            ->andReturn($host . '/');
        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($json, $key, $secret, $host) {
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
        $cache = Mockery::mock(CacheInterface::class);
        $cache
            ->expects('get')
            ->andReturn($json);
        $host = 'https://example.com';
        $key = 'key';
        $secret = 'secret';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');

        $bearer = new PayPalBearer($cache, $host, $key, $secret, $logger);

        $token = $bearer->bearer();
        $this->assertEquals("abc", $token->token());
        $this->assertTrue($token->is_valid());
    }

    public function testExceptionThrownOnError()
    {
        $json = '{"access_token":"abc","expires_in":100, "created":' . time() . '}';
        $cache = Mockery::mock(CacheInterface::class);
        $cache
            ->expects('get')
            ->andReturn('');
        $host = 'https://example.com';
        $key = 'key';
        $secret = 'secret';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');

        $bearer = new PayPalBearer($cache, $host, $key, $secret, $logger);

        expect('trailingslashit')
            ->with($host)
            ->andReturn($host . '/');
        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($json, $key, $secret, $host) {
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
        $cache = Mockery::mock(CacheInterface::class);
        $cache
            ->expects('get')
            ->andReturn('');
        $host = 'https://example.com';
        $key = 'key';
        $secret = 'secret';
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');

        $bearer = new PayPalBearer($cache, $host, $key, $secret, $logger);

        expect('trailingslashit')
            ->with($host)
            ->andReturn($host . '/');
        expect('wp_remote_get')
            ->andReturnUsing(
                function ($url, $args) use ($json, $key, $secret, $host) {
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
}
