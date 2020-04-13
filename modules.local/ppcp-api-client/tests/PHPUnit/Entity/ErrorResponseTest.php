<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


use PHPUnit\Framework\TestCase;

class ErrorResponseTest extends TestCase
{

    public function test() {
        $testee = new ErrorResponse(
            'code',
            'message',
            500,
            'url',
            [1,2,3]
        );

        $this->assertEquals('code', $testee->code());
        $this->assertEquals('message', $testee->message());
        $this->assertEquals(500, $testee->httpCode());
        $this->assertEquals('url', $testee->url());
        $this->assertEquals([1,2,3], $testee->args());
    }

    public function testIs() {

        $testee = new ErrorResponse(
            'code',
            'message',
            500,
            'url',
            [1,2,3]
        );

        $this->assertTrue($testee->is('code'));
        $this->assertFalse($testee->is('not-code'));
    }
}