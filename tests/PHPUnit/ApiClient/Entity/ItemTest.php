<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\TestCase;
use Mockery;

class ItemTest extends TestCase
{

    public function test()
    {
        $unitAmount = Mockery::mock(Money::class);
        $tax = Mockery::mock(Money::class);
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
        $this->assertEquals($unitAmount, $testee->unit_amount());
        $this->assertEquals(1, $testee->quantity());
        $this->assertEquals('description', $testee->description());
        $this->assertEquals($tax, $testee->tax());
        $this->assertEquals('sku', $testee->sku());
        $this->assertEquals('PHYSICAL_GOODS', $testee->category());
    }

    public function testDigitalGoodsCategory()
    {
        $unitAmount = Mockery::mock(Money::class);
        $tax = Mockery::mock(Money::class);
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

    public function testToArray()
    {
        $unitAmount = Mockery::mock(Money::class);
        $unitAmount
            ->expects('to_array')
            ->andReturn([1]);
        $tax = Mockery::mock(Money::class);
        $tax
            ->expects('to_array')
            ->andReturn([2]);
        $testee = new Item(
            'name',
            $unitAmount,
            1,
            'description',
            $tax,
            'sku',
            'PHYSICAL_GOODS',
			'url',
			'image_url'
        );

        $expected = [
            'name' => 'name',
            'unit_amount' => [1],
            'quantity' => 1,
            'description' => 'description',
            'sku' => 'sku',
            'category' => 'PHYSICAL_GOODS',
            'url' => 'url',
            'image_url' => 'image_url',
            'tax' => [2],
        ];

        $this->assertEquals($expected, $testee->to_array());
    }
}
