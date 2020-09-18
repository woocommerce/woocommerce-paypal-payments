<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\TestCase;
use Mockery;

class PaymentsFactoryTest extends TestCase
{
    public function testFromPayPalResponse()
    {
        $authorization = Mockery::mock(Authorization::class);
        $authorization->shouldReceive('to_array')->andReturn(['id' => 'foo', 'status' => 'CREATED']);

        $authorizationsFactory = Mockery::mock(AuthorizationFactory::class);
        $authorizationsFactory->shouldReceive('from_paypal_response')->andReturn($authorization);

        $response = (object)[
            'authorizations' => [
                (object)['id' => 'foo', 'status' => 'CREATED'],
            ],
        ];

        $testee = new PaymentsFactory($authorizationsFactory);
        $result = $testee->from_paypal_response($response);

        $this->assertInstanceOf(Payments::class, $result);

        $expectedToArray = [
            'authorizations' => [
                ['id' => 'foo', 'status' => 'CREATED'],
            ],
        ];
        $this->assertEquals($expectedToArray, $result->to_array());
    }
}
