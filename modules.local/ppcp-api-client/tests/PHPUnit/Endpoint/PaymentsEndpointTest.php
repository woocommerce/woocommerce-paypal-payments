<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Entity\ErrorResponseCollection;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ErrorResponseCollectionFactory;
use Inpsyde\PayPalCommerce\ApiClient\TestCase;
use Mockery;

use function Brain\Monkey\Functions\expect;

class PaymentsEndpointTest extends TestCase
{
    public function testAuthorizationDefault()
    {
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')->andReturn('bearer');

        $authorization = Mockery::mock(Authorization::class);
        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);
        $authorizationFactory
            ->expects('fromPayPalRequest')
            ->andReturn($authorization);

        $errorResponseCollectionFactory = Mockery::mock(ErrorResponseCollectionFactory::class);

        $rawResponse = ['body' => '{"is_correct":true}'];

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            $errorResponseCollectionFactory
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

        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn('bearer');

        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);

        $error = Mockery::mock(ErrorResponseCollection::class);
        $errorResponseCollectionFactory = Mockery::mock(ErrorResponseCollectionFactory::class);
        $errorResponseCollectionFactory
            ->expects('unknownError')
            ->withSomeOfArgs($host . 'v2/payments/authorizations/' . $authorizationId)
            ->andReturn($error);

        $rawResponse = ['body' => '{"is_correct":true}'];

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            $errorResponseCollectionFactory
        );

        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(true);
        expect('do_action')
            ->with('woocommerce-paypal-commerce-gateway.error', $error);

        $this->expectException(RuntimeException::class);
        $testee->authorization($authorizationId);
    }

    public function testAuthorizationIsNot200()
    {
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn('bearer');

        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);

        $rawResponse = ['body' => '{"some_error":true}'];

        $error = Mockery::mock(ErrorResponseCollection::class);
        $errorResponseCollectionFactory = Mockery::mock(ErrorResponseCollectionFactory::class);
        $errorResponseCollectionFactory
            ->expects('fromPayPalResponse')
            ->andReturnUsing(
                function ($json, $status, $url, $args) use ($error, $host, $authorizationId): ?ErrorResponseCollection {
                    $wrongError = Mockery::mock(ErrorResponseCollection::class);
                    if (!$json->some_error) {
                        return $wrongError;
                    }
                    if ($status !== 500) {
                        return $wrongError;
                    }
                    if ($url !== $host . 'v2/payments/authorizations/' . $authorizationId) {
                        return $wrongError;
                    }
                    if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                        return $wrongError;
                    }
                    if ($args['headers']['Content-Type'] !== 'application/json') {
                        return $wrongError;
                    }

                    return $error;
                }
            );

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            $errorResponseCollectionFactory
        );

        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(500);
        expect('do_action')
            ->with('woocommerce-paypal-commerce-gateway.error', $error);

        $this->expectException(RuntimeException::class);
        $testee->authorization($authorizationId);
    }

    public function testCaptureDefault() {
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $bearer = Mockery::mock(Bearer::class);
        $bearer
            ->expects('bearer')->andReturn('bearer');

        $authorization = Mockery::mock(Authorization::class);
        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);
        $authorizationFactory
            ->expects('fromPayPalRequest')
            ->andReturn($authorization);

        $errorResponseCollectionFactory = Mockery::mock(ErrorResponseCollectionFactory::class);

        $rawResponse = ['body' => '{"is_correct":true}'];

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            $errorResponseCollectionFactory
        );

        expect('wp_remote_post')->andReturnUsing(
            function ($url, $args) use ($rawResponse, $host, $authorizationId) {
                if ($url !== $host . 'v2/payments/authorizations/' . $authorizationId . '/capture') {
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

    public function testCaptureIsWpError() {
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn('bearer');

        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);

        $error = Mockery::mock(ErrorResponseCollection::class);
        $errorResponseCollectionFactory = Mockery::mock(ErrorResponseCollectionFactory::class);
        $errorResponseCollectionFactory
            ->expects('unknownError')
            ->withSomeOfArgs($host . 'v2/payments/authorizations/' . $authorizationId . '/capture')
            ->andReturn($error);

        $rawResponse = ['body' => '{"is_correct":true}'];

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            $errorResponseCollectionFactory
        );

        expect('wp_remote_post')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(true);
        expect('do_action')
            ->with('woocommerce-paypal-commerce-gateway.error', $error);

        $this->expectException(RuntimeException::class);
        $testee->capture($authorizationId);
    }

    public function testAuthorizationIsNot201()
    {
        $host = 'https://example.com/';
        $authorizationId = 'somekindofid';

        $bearer = Mockery::mock(Bearer::class);
        $bearer->expects('bearer')->andReturn('bearer');

        $authorizationFactory = Mockery::mock(AuthorizationFactory::class);

        $rawResponse = ['body' => '{"some_error":true}'];

        $error = Mockery::mock(ErrorResponseCollection::class);
        $errorResponseCollectionFactory = Mockery::mock(ErrorResponseCollectionFactory::class);
        $errorResponseCollectionFactory
            ->expects('fromPayPalResponse')
            ->andReturnUsing(
                function ($json, $status, $url, $args) use ($error, $host, $authorizationId): ?ErrorResponseCollection {
                    $wrongError = Mockery::mock(ErrorResponseCollection::class);
                    if (!$json->some_error) {
                        return $wrongError;
                    }
                    if ($status !== 500) {
                        return $wrongError;
                    }
                    if ($url !== $host . 'v2/payments/authorizations/' . $authorizationId . '/capture') {
                        return $wrongError;
                    }
                    if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                        return $wrongError;
                    }
                    if ($args['headers']['Content-Type'] !== 'application/json') {
                        return $wrongError;
                    }

                    return $error;
                }
            );

        $testee = new PaymentsEndpoint(
            $host,
            $bearer,
            $authorizationFactory,
            $errorResponseCollectionFactory
        );

        expect('wp_remote_post')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(500);
        expect('do_action')
            ->with('woocommerce-paypal-commerce-gateway.error', $error);

        $this->expectException(RuntimeException::class);
        $testee->capture($authorizationId);
    }

}
