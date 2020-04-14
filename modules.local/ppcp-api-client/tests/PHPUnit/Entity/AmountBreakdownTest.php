<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


use Inpsyde\PayPalCommerce\ApiClient\TestCase;
use Mockery;

class AmountBreakdownTest extends TestCase
{

    public function test() {
        $itemTotal = Mockery::mock(Money::class);
        $itemTotal
            ->expects('toArray')->andReturn(['itemTotal']);
        $shipping = Mockery::mock(Money::class);
        $shipping
            ->expects('toArray')->andReturn(['shipping']);
        $taxTotal = Mockery::mock(Money::class);
        $taxTotal
            ->expects('toArray')->andReturn(['taxTotal']);
        $handling = Mockery::mock(Money::class);
        $handling
            ->expects('toArray')->andReturn(['handling']);
        $insurance = Mockery::mock(Money::class);
        $insurance
            ->expects('toArray')->andReturn(['insurance']);
        $shippingDiscount = Mockery::mock(Money::class);
        $shippingDiscount
            ->expects('toArray')->andReturn(['shippingDiscount']);
        $discount = Mockery::mock(Money::class);
        $discount
            ->expects('toArray')->andReturn(['discount']);
        $testee = new AmountBreakdown(
            $itemTotal,
            $shipping,
            $taxTotal,
            $handling,
            $insurance,
            $shippingDiscount,
            $discount
        );

        $this->assertEquals($itemTotal, $testee->itemTotal());
        $this->assertEquals($shipping, $testee->shipping());
        $this->assertEquals($taxTotal, $testee->taxTotal());
        $this->assertEquals($handling, $testee->handling());
        $this->assertEquals($insurance, $testee->insurance());
        $this->assertEquals($shippingDiscount, $testee->shippingDiscount());
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

        $this->assertEquals($expected, $testee->toArray());
    }

    /**
     * @dataProvider dataDropArrayKeyIfNoValueGiven
     */
    public function testDropArrayKeyIfNoValueGiven($keyMissing, $methodName) {

        $itemTotal = Mockery::mock(Money::class);
        $itemTotal
            ->shouldReceive('toArray')->zeroOrMoreTimes()->andReturn(['itemTotal']);
        $shipping = Mockery::mock(Money::class);
        $shipping
            ->shouldReceive('toArray')->zeroOrMoreTimes()->andReturn(['shipping']);
        $taxTotal = Mockery::mock(Money::class);
        $taxTotal
            ->shouldReceive('toArray')->zeroOrMoreTimes()->andReturn(['taxTotal']);
        $handling = Mockery::mock(Money::class);
        $handling
            ->shouldReceive('toArray')->zeroOrMoreTimes()->andReturn(['handling']);
        $insurance = Mockery::mock(Money::class);
        $insurance
            ->shouldReceive('toArray')->zeroOrMoreTimes()->andReturn(['insurance']);
        $shippingDiscount = Mockery::mock(Money::class);
        $shippingDiscount
            ->shouldReceive('toArray')->zeroOrMoreTimes()->andReturn(['shippingDiscount']);
        $discount = Mockery::mock(Money::class);
        $discount
            ->shouldReceive('toArray')->zeroOrMoreTimes()->andReturn(['discount']);


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
        $array = $testee->toArray();
        $result = ! array_key_exists($keyMissing, $array);
        $this->assertTrue($result);
        $this->assertNull($testee->{$methodName}(), "$methodName should return null");
    }

    public function dataDropArrayKeyIfNoValueGiven() : array {
        return [
            ['item_total', 'itemTotal'],
            ['shipping', 'shipping'],
            ['tax_total', 'taxTotal'],
            ['handling', 'handling'],
            ['insurance', 'insurance'],
            ['shipping_discount', 'shippingDiscount'],
            ['discount', 'discount'],
        ];
    }
}