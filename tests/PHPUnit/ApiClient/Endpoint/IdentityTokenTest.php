<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use Requests_Utility_CaseInsensitiveDictionary;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Repository\CustomerRepository;
use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class IdentityTokenTest extends TestCase
{
    private $host;
    private $bearer;
    private $logger;
    private $settings;
    private $customer_repository;
    private $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->host = 'https://example.com/';
        $this->bearer = Mockery::mock(Bearer::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->settings = Mockery::mock(Settings::class);
        $this->customer_repository = Mockery::mock(CustomerRepository::class);

        $this->sut = new IdentityToken(
        	$this->host,
			$this->bearer,
			$this->logger,
			$this->settings,
			$this->customer_repository
		);
    }

    public function testGenerateForCustomerReturnsToken()
    {
        $id = 1;
		!defined('PPCP_FLAG_SUBSCRIPTION') && define('PPCP_FLAG_SUBSCRIPTION', true);
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $this->bearer
            ->expects('bearer')->andReturn($token);

        $host = $this->host;
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
		$this->logger->shouldReceive('debug');
		$this->settings->shouldReceive('has')->andReturn(true);
		$this->settings->shouldReceive('get')->andReturn(true);
		$this->customer_repository->shouldReceive('customer_id_for_user')->andReturn('prefix1');
        expect('update_user_meta')->with($id, 'ppcp_customer_id', 'prefix1');

		$rawResponse = [
			'body' => '{"client_token":"abc123", "expires_in":3600}',
			'headers' => $headers,
		];

        expect('wp_remote_get')
            ->andReturnUsing(function ($url, $args) use ($rawResponse, $host, $headers) {
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
		when('get_user_meta')->justReturn('');

        $result = $this->sut->generate_for_user(1);
        $this->assertInstanceOf(Token::class, $result);
    }

    public function testGenerateForCustomerFailsBecauseWpError()
    {
        $id = 1;
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $this->bearer
            ->expects('bearer')->andReturn($token);

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        expect('wp_remote_get')->andReturn(['headers' => $headers,]);
        expect('is_wp_error')->andReturn(true);
        $this->logger->shouldReceive('log');
        $this->logger->shouldReceive('debug');
		$this->settings->shouldReceive('has')->andReturn(true);
		$this->settings->shouldReceive('get')->andReturn(true);
        $this->customer_repository->shouldReceive('customer_id_for_user')->andReturn('prefix1');
        expect('update_user_meta')->with($id, 'ppcp_customer_id', 'prefix1');

        $this->expectException(RuntimeException::class);
        $this->sut->generate_for_user(1);
    }

    public function testGenerateForCustomerFailsBecauseResponseCodeIsNot200()
    {
        $id = 1;
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $this->bearer
            ->expects('bearer')->andReturn($token);

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        expect('wp_remote_get')->andReturn([
        	'body' => '',
			'headers' => $headers,
			]);
        expect('is_wp_error')->andReturn(false);
        expect('wp_remote_retrieve_response_code')->andReturn(500);
        $this->logger->shouldReceive('log');
        $this->logger->shouldReceive('debug');
		$this->settings->shouldReceive('has')->andReturn(true);
		$this->settings->shouldReceive('get')->andReturn(true);
        $this->customer_repository->shouldReceive('customer_id_for_user')->andReturn('prefix1');
        expect('update_user_meta')->with($id, 'ppcp_customer_id', 'prefix1');

        $this->expectException(PayPalApiException::class);
        $this->sut->generate_for_user(1);
    }
}
