<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\TestCase;
use Mockery;
use function Brain\Monkey\Functions\expect;

class IdentityTokenTest extends TestCase
{
    private $host;
    private $bearer;
    private $logger;
    private $prefix;
    private $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->host = 'https://example.com/';
        $this->bearer = Mockery::mock(Bearer::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->prefix = 'prefix';
        $this->sut = new IdentityToken($this->host, $this->bearer, $this->logger, $this->prefix);
    }

    public function testGenerateForCustomerReturnsToken()
    {
        define( 'PPCP_FLAG_SUBSCRIPTION', true );
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $this->bearer
            ->expects('bearer')->andReturn($token);

        $rawResponse = ['body' => '{"client_token":"abc123", "expires_in":3600}'];
        $host = $this->host;
        expect('wp_remote_get')
            ->andReturnUsing(function ($url, $args) use ($rawResponse, $host) {
                if ($url !== $host . 'v1/identity/generate-token') {
                    return false;
                }
                if ($args['method'] !== 'POST') {
                    return false;
                }
                if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                    return false;
                }
                if ($args['headers']['Content-Type'] !== 'application/json') {
                    return false;
                }
                if ($args['body'] !== '{"customer_id":"prefix1"}') {
                    return false;
                }

                return $rawResponse;
            });

        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(200);

        $result = $this->sut->generate_for_customer(1);
        $this->assertInstanceOf(Token::class, $result);
    }

    public function testGenerateForCustomerFailsBecauseWpError()
    {
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $this->bearer
            ->expects('bearer')->andReturn($token);

        expect('wp_remote_get')->andReturn();
        expect('is_wp_error')->andReturn(true);
        $this->logger->shouldReceive('log');

        $this->expectException(RuntimeException::class);
        $this->sut->generate_for_customer(1);
    }

    public function testGenerateForCustomerFailsBecauseResponseCodeIsNot200()
    {
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $this->bearer
            ->expects('bearer')->andReturn($token);

        expect('wp_remote_get')->andReturn(['body' => '',]);
        expect('is_wp_error')->andReturn(false);
        expect('wp_remote_retrieve_response_code')->andReturn(500);
        $this->logger->shouldReceive('log');

        $this->expectException(PayPalApiException::class);
        $this->sut->generate_for_customer(1);
    }
}
