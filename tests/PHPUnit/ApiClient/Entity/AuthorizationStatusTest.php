<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;

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
