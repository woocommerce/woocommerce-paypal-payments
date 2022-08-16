<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\TestCase;
use Mockery;

class AmountTest extends TestCase
{

    public function test()
    {
        $money = Mockery::mock(Money::class);
        $money->shouldReceive('currency_code')->andReturn('currencyCode');
        $money->shouldReceive('value')->andReturn(1.10);
        $testee = new Amount($money);

        $this->assertEquals('currencyCode', $testee->currency_code());
        $this->assertEquals(1.10, $testee->value());
    }

    public function testBreakdownIsNull()
    {
        $money = new Money(1.10, 'currencyCode');
        $testee = new Amount($money);

        $this->assertNull($testee->breakdown());

        $expectedArray = [
            'currency_code' => 'currencyCode',
            'value' => 1.10,
        ];
        $this->assertEquals($expectedArray, $testee->to_array());
    }

    public function testBreakdown()
    {
		$money = new Money(1.10, 'currencyCode');
        $breakdown = Mockery::mock(AmountBreakdown::class);
        $breakdown->shouldReceive('to_array')->andReturn([1]);
        $testee = new Amount($money, $breakdown);

        $this->assertEquals($breakdown, $testee->breakdown());

        $expectedArray = [
            'currency_code' => 'currencyCode',
            'value' => 1.10,
            'breakdown' => [1],
        ];
        $this->assertEquals($expectedArray, $testee->to_array());
    }
}
