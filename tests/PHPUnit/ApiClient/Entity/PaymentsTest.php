<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\TestCase;

class PaymentsTest extends TestCase
{
    public function testAuthorizations()
    {
        $authorization = \Mockery::mock(Authorization::class);
        $authorizations = [$authorization];

        $testee = new Payments(...$authorizations);

        $this->assertEquals($authorizations, $testee->authorizations());
    }

    public function testToArray()
    {
        $authorization = \Mockery::mock(Authorization::class);
        $authorization->shouldReceive('to_array')->andReturn(
            [
                'id' => 'foo',
                'status' => 'CREATED',
            ]
        );
        $authorizations = [$authorization];

        $testee = new Payments(...$authorizations);

        $this->assertEquals(
            [
                'authorizations' => [
                    [
                        'id' => 'foo',
                        'status' => 'CREATED',
                    ],
                ],
            ],
            $testee->to_array()
        );
    }
}
