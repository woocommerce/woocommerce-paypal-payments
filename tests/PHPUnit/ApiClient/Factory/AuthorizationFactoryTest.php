<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;

class AuthorizationFactoryTest extends TestCase
{
    public function testFromPayPalRequestDefault()
    {
        $response = (object)[
            'id' => 'foo',
            'status' => 'CAPTURED',
        ];
		$fraudProcessorResponseFactory = \Mockery::mock(FraudProcessorResponseFactory::class);

        $testee = new AuthorizationFactory($fraudProcessorResponseFactory);
        $result = $testee->from_paypal_response($response);

        $this->assertInstanceOf(Authorization::class, $result);

        $this->assertEquals('foo', $result->id());
        $this->assertInstanceOf(AuthorizationStatus::class, $result->status());

        $this->assertEquals('CAPTURED', $result->status()->name());
    }

    public function testReturnExceptionIdIsMissing()
    {
        $this->expectException(RuntimeException::class);
        $response = (object)[
            'status' => 'CAPTURED',
        ];

		$fraudProcessorResponseFactory = \Mockery::mock(FraudProcessorResponseFactory::class);

        $testee = new AuthorizationFactory($fraudProcessorResponseFactory);
        $testee->from_paypal_response($response);
    }

    public function testReturnExceptionStatusIsMissing()
    {
        $this->expectException(RuntimeException::class);
        $response = (object)[
            'id' => 'foo',
        ];

		$fraudProcessorResponseFactory = \Mockery::mock(FraudProcessorResponseFactory::class);

        $testee = new AuthorizationFactory($fraudProcessorResponseFactory);
        $testee->from_paypal_response($response);
    }
}
