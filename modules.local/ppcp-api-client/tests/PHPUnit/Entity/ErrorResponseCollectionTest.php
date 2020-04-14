<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\TestCase;

class ErrorResponseCollectionTest extends TestCase
{

    public function testHasErrorCode() {
        $error1 = \Mockery::mock(ErrorResponse::class);
        $error1
            ->expects('code')
            ->times(3)
            ->andReturn('code-1');
        $error2 = \Mockery::mock(ErrorResponse::class);
        $error2
            ->expects('code')
            ->times(3)
            ->andReturn('code-2');
        $testee = new ErrorResponseCollection($error1, $error2);
        $this->assertTrue($testee->hasErrorCode('code-1'), 'code-1 should return true');
        $this->assertTrue($testee->hasErrorCode('code-2'), 'code-2 should return true');
        $this->assertFalse($testee->hasErrorCode('code-3'), 'code-3 should not return true');
    }
    public function testCodes() {
        $error1 = \Mockery::mock(ErrorResponse::class);
        $error1
            ->expects('code')
            ->andReturn('code-1');
        $error2 = \Mockery::mock(ErrorResponse::class);
        $error2
            ->expects('code')
            ->andReturn('code-2');
        $testee = new ErrorResponseCollection($error1, $error2);
        $expected = ['code-1', 'code-2'];
        $this->assertEquals($expected, $testee->codes());
    }
    public function testErrors() {
        $error1 = \Mockery::mock(ErrorResponse::class);
        $error2 = \Mockery::mock(ErrorResponse::class);
        $testee = new ErrorResponseCollection($error1, $error2);

        $errors = $testee->errors();
        $this->assertEquals(2, count($errors), 'Two errors stored.');
        $this->assertEquals($error1, $errors[0]);
        $this->assertEquals($error2, $errors[1]);
    }
}