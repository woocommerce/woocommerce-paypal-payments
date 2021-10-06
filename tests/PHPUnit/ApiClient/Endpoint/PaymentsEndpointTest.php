<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\NullLogger;
use Requests_Utility_CaseInsensitiveDictionary;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ErrorResponseCollection;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ErrorResponseCollectionFactory;
use WooCommerce\PayPalCommerce\ApiClient\TestCase;
use Mockery;

use Psr\Log\LoggerInterface;
use function Brain\Monkey\Functions\expect;

class PaymentsEndpointTest extends TestCase
{
    public function testAuthorizationDefault()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $bearer = Mockery::mock(Bearer::class);
        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer
            ->expects('bearer')->andReturn($token);

        $authorization = Mockery::mock(Authorization::class);
        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);
        $authorizationFactory
            ->expects('from_paypal_response')
            ->andReturn($authorization);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');
        $logger->shouldReceive('debug');

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"is_correct":true}',
			'headers' => $headers,
			];

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            $logger
        );

        expect('wp_remote_get')->andReturnUsing(
            function ($url, $args) use ($rawResponse, $host, $authorizationId) {
                if ($url !== $host . 'v2/payments/authorizations/' . $authorizationId) {
                    return false;
                }
                if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                    return false;
                }
                if ($args['headers']['Content-Type'] !== 'application/json') {
                    return false;
                }

                return $rawResponse;
            }
        );
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(200);

        $result = $testee->authorization($authorizationId);
        $this->assertEquals($authorization, $result);
    }

    public function testAuthorizationWpError()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn($token);

        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');
        $logger->shouldReceive('debug');

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"is_correct":true}',
			'headers' => $headers,
			];

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            $logger
        );

        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(true);

        $this->expectException(RuntimeException::class);
        $testee->authorization($authorizationId);
    }

    public function testAuthorizationIsNot200()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn($token);

        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"some_error":true}',
			'headers' => $headers,
			];

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');
        $logger->shouldReceive('debug');

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            $logger
        );

        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(500);

        $this->expectException(RuntimeException::class);
        $testee->authorization($authorizationId);
    }

    public function testCaptureDefault()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')->andReturn($token);

        $authorization = Mockery::mock(Authorization::class);
        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);
        $authorizationFactory
            ->expects('from_paypal_response')
            ->andReturn($authorization);


        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');
        $logger->shouldReceive('debug');

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"is_correct":true}',
			'headers' => $headers,
			];

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            $logger
        );

        expect('wp_remote_get')->andReturnUsing(
            function ($url, $args) use ($rawResponse, $host, $authorizationId) {
                if ($url !== $host . 'v2/payments/authorizations/' . $authorizationId . '/capture') {
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

                return $rawResponse;
            }
        );
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(201);

        $result = $testee->capture($authorizationId);
        $this->assertEquals($authorization, $result);
    }

    public function testCaptureIsWpError()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn($token);

        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"is_correct":true}',
			'headers' => $headers,
			];

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            new NullLogger()
        );

        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(true);

        $this->expectException(RuntimeException::class);
        $testee->capture($authorizationId);
    }

    public function testAuthorizationIsNot201()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn($token);

        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"some_error":true}',
			'headers' => $headers,
			];

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            new NullLogger()
        );

        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(500);

        $this->expectException(RuntimeException::class);
        $testee->capture($authorizationId);
    }
}
