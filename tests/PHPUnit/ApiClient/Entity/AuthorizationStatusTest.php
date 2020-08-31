<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\TestCase;

class AuthorizationStatusTest extends TestCase
{
    /**
     * @dataProvider statusDataProvider
     * @param $status
     */
    public function testValidStatusProvided($status)
    {
        $authorizationStatus = new AuthorizationStatus($status);

        $this->assertEquals($authorizationStatus->name(), $status);
    }

    public function testInvalidStatusProvided()
    {
        $this->expectException(RuntimeException::class);

        new AuthorizationStatus('invalid');
    }

    public function testStatusComparision()
    {
        $authorizationStatus = new AuthorizationStatus('CREATED');

        $this->assertTrue($authorizationStatus->is('CREATED'));
        $this->assertFalse($authorizationStatus->is('NOT_CREATED'));
    }

    public function statusDataProvider(): array
    {
        return [
            ['INTERNAL'],
            ['CREATED'],
            ['CAPTURED'],
            ['DENIED'],
            ['EXPIRED'],
            ['PARTIALLY_CAPTURED'],
            ['VOIDED'],
            ['PENDING'],
        ];
    }
}
