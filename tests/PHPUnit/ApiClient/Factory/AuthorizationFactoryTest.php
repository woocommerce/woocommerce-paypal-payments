<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\TestCase;

class AuthorizationFactoryTest extends TestCase
{
    public function testFromPayPalRequestDefault()
    {
        $response = (object)[
            'id' => 'foo',
            'status' => 'CAPTURED',
        ];

        $testee = new AuthorizationFactory();
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

        $testee = new AuthorizationFactory();
        $testee->from_paypal_response($response);
    }

    public function testReturnExceptionStatusIsMissing()
    {
        $this->expectException(RuntimeException::class);
        $response = (object)[
            'id' => 'foo',
        ];

        $testee = new AuthorizationFactory();
        $testee->from_paypal_response($response);
    }
}
