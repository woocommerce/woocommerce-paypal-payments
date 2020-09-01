<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\TestCase;

class AuthorizationTest extends TestCase
{
    public function testIdAndStatus()
    {
        $authorizationStatus = \Mockery::mock(AuthorizationStatus::class);
        $testee = new Authorization('foo', $authorizationStatus);

        $this->assertEquals('foo', $testee->id());
        $this->assertEquals($authorizationStatus, $testee->status());
    }

    public function testToArray()
    {
        $authorizationStatus = \Mockery::mock(AuthorizationStatus::class);
        $authorizationStatus->expects('name')->andReturn('CAPTURED');

        $testee = new Authorization('foo', $authorizationStatus);

        $expected = [
            'id' => 'foo',
            'status' => 'CAPTURED',
        ];
        $this->assertEquals($expected, $testee->to_array());
    }
}
