<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase
{

    public function test() {
        $unitAmount = \Mockery::mock(Money::class);
        $tax = \Mockery::mock(Money::class);
        $testee = new Item(
            'name',
            $unitAmount,
            1,
            'description',
            $tax,
            'sku',
            'PHYSICAL_GOODS'
        );

        $this->assertEquals('name', $testee->name());
        $this->assertEquals($unitAmount, $testee->unitAmount());
        $this->assertEquals(1, $testee->quantity());
        $this->assertEquals('description', $testee->description());
        $this->assertEquals($tax, $testee->tax());
        $this->assertEquals('sku', $testee->sku());
        $this->assertEquals('PHYSICAL_GOODS', $testee->category());
    }

    public function testDigitalGoodsCategory() {
        $unitAmount = \Mockery::mock(Money::class);
        $tax = \Mockery::mock(Money::class);
        $testee = new Item(
            'name',
            $unitAmount,
            1,
            'description',
            $tax,
            'sku',
            'DIGITAL_GOODS'
        );

        $this->assertEquals('DIGITAL_GOODS', $testee->category());
    }

    public function testToArray() {

        $unitAmount = \Mockery::mock(Money::class);
        $unitAmount
            ->expects('toArray')
            ->andReturn([1]);
        $tax = \Mockery::mock(Money::class);
        $tax
            ->expects('toArray')
            ->andReturn([2]);
        $testee = new Item(
            'name',
            $unitAmount,
            1,
            'description',
            $tax,
            'sku',
            'PHYSICAL_GOODS'
        );

        $expected = [
            'name' => 'name',
            'unit_amount' => [1],
            'quantity' => 1,
            'description' => 'description',
            'sku' => 'sku',
            'category' => 'PHYSICAL_GOODS',
            'tax' => [2],
        ];

        $this->assertEquals($expected, $testee->toArray());
    }
}