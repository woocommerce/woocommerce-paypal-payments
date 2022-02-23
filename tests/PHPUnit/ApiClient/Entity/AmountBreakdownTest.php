<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\TestCase;
use Mockery;

class AmountBreakdownTest extends TestCase
{

    public function test()
    {
        $itemTotal = Mockery::mock(Money::class);
        $itemTotal
            ->expects('to_array')->andReturn(['itemTotal']);
        $shipping = Mockery::mock(Money::class);
        $shipping
            ->expects('to_array')->andReturn(['shipping']);
        $taxTotal = Mockery::mock(Money::class);
        $taxTotal
            ->expects('to_array')->andReturn(['taxTotal']);
        $handling = Mockery::mock(Money::class);
        $handling
            ->expects('to_array')->andReturn(['handling']);
        $insurance = Mockery::mock(Money::class);
        $insurance
            ->expects('to_array')->andReturn(['insurance']);
        $shippingDiscount = Mockery::mock(Money::class);
        $shippingDiscount
            ->expects('to_array')->andReturn(['shippingDiscount']);
        $discount = Mockery::mock(Money::class);
        $discount
            ->expects('to_array')->andReturn(['discount']);
        $testee = new AmountBreakdown(
            $itemTotal,
            $shipping,
            $taxTotal,
            $handling,
            $insurance,
            $shippingDiscount,
            $discount
        );

        $this->assertEquals($itemTotal, $testee->item_total());
        $this->assertEquals($shipping, $testee->shipping());
        $this->assertEquals($taxTotal, $testee->tax_total());
        $this->assertEquals($handling, $testee->handling());
        $this->assertEquals($insurance, $testee->insurance());
        $this->assertEquals($shippingDiscount, $testee->shipping_discount());
        $this->assertEquals($discount, $testee->discount());

        $expected = [
            'item_total' => ['itemTotal'],
            'shipping' => ['shipping'],
            'tax_total' => ['taxTotal'],
            'handling' => ['handling'],
            'insurance' => ['insurance'],
            'shipping_discount' => ['shippingDiscount'],
            'discount' => ['discount'],
        ];

        $this->assertEquals($expected, $testee->to_array());
    }

    /**
     * @dataProvider dataDropArrayKeyIfNoValueGiven
     */
    public function testDropArrayKeyIfNoValueGiven($keyMissing, $methodName)
    {
        $itemTotal = Mockery::mock(Money::class);
        $itemTotal
            ->shouldReceive('to_array')->zeroOrMoreTimes()->andReturn(['itemTotal']);
        $shipping = Mockery::mock(Money::class);
        $shipping
            ->shouldReceive('to_array')->zeroOrMoreTimes()->andReturn(['shipping']);
        $taxTotal = Mockery::mock(Money::class);
        $taxTotal
            ->shouldReceive('to_array')->zeroOrMoreTimes()->andReturn(['taxTotal']);
        $handling = Mockery::mock(Money::class);
        $handling
            ->shouldReceive('to_array')->zeroOrMoreTimes()->andReturn(['handling']);
        $insurance = Mockery::mock(Money::class);
        $insurance
            ->shouldReceive('to_array')->zeroOrMoreTimes()->andReturn(['insurance']);
        $shippingDiscount = Mockery::mock(Money::class);
        $shippingDiscount
            ->shouldReceive('to_array')->zeroOrMoreTimes()->andReturn(['shippingDiscount']);
        $discount = Mockery::mock(Money::class);
        $discount
            ->shouldReceive('to_array')->zeroOrMoreTimes()->andReturn(['discount']);

        $items = [
            'item_total' => $itemTotal,
            'shipping' => $shipping,
            'tax_total' => $taxTotal,
            'handling' => $handling,
            'insurance' => $insurance,
            'shipping_discount' => $shippingDiscount,
            'discount' => $discount,
        ];
        $items[$keyMissing] = null;

        $testee = new AmountBreakdown(...array_values($items));
        $array = $testee->to_array();
        $result = ! array_key_exists($keyMissing, $array);
        $this->assertTrue($result);
        $this->assertNull($testee->{$methodName}(), "$methodName should return null");
    }

    public function dataDropArrayKeyIfNoValueGiven() : array
    {
        return [
            ['item_total', 'item_total'],
            ['shipping', 'shipping'],
            ['tax_total', 'tax_total'],
            ['handling', 'handling'],
            ['insurance', 'insurance'],
            ['shipping_discount', 'shipping_discount'],
            ['discount', 'discount'],
        ];
    }
}
