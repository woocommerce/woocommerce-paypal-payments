<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Entity\ErrorResponseCollection;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Token;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ErrorResponseCollectionFactory;
use Inpsyde\PayPalCommerce\ApiClient\TestCase;
use Mockery;

use Psr\Log\LoggerInterface;
use function Brain\Monkey\Functions\expect;

class PaymentsEndpointTest extends TestCase
{
    public function testAuthorizationDefault()
    {
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
            ->expects('fromPayPalRequest')
            ->andReturn($authorization);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');

        $rawResponse = ['body' => '{"is_correct":true}'];

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

        $rawResponse = ['body' => '{"is_correct":true}'];

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
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn($token);

        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);

        $rawResponse = ['body' => '{"some_error":true}'];

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('log');

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
            ->expects('fromPayPalRequest')
            ->andReturn($authorization);


        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldNotReceive('log');

        $rawResponse = ['body' => '{"is_correct":true}'];

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
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn($token);

        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('log');

        $rawResponse = ['body' => '{"is_correct":true}'];

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            $logger
        );

        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(true);

        $this->expectException(RuntimeException::class);
        $testee->capture($authorizationId);
    }

    public function testAuthorizationIsNot201()
    {
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $token = Mockery::mock(Token::class);
        $token
            ->expects('token')->andReturn('bearer');
        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn($token);

        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);

        $rawResponse = ['body' => '{"some_error":true}'];

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->expects('log');

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
        $testee->capture($authorizationId);
    }
}
