<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payments;
use Inpsyde\PayPalCommerce\ApiClient\TestCase;
use Mockery;

class PaymentsFactoryTest extends TestCase
{
    public function testFromPayPalResponse()
    {
        $authorization = Mockery::mock(Authorization::class);
        $authorization->shouldReceive('toArray')->andReturn(['id' => 'foo', 'status' => 'CREATED']);

        $authorizationsFactory = Mockery::mock(AuthorizationFactory::class);
        $authorizationsFactory->shouldReceive('fromPayPalRequest')->andReturn($authorization);

        $response = (object)[
            'authorizations' => [
                (object)['id' => 'foo', 'status' => 'CREATED'],
            ],
        ];

        $testee = new PaymentsFactory($authorizationsFactory);
        $result = $testee->fromPayPalResponse($response);

        $this->assertInstanceOf(Payments::class, $result);

        $expectedToArray = [
            'authorizations' => [
                ['id' => 'foo', 'status' => 'CREATED'],
            ],
        ];
        $this->assertEquals($expectedToArray, $result->toArray());
    }
}
