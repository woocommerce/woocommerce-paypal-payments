<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


use PHPUnit\Framework\TestCase;

class PurchaseUnitTest extends TestCase
{

    public function test() {

        $amount = \Mockery::mock(Amount::class);
        $amount->expects('breakdown')->andReturnNull();
        $amount->expects('toArray')->andReturn(['amount']);
        $item1 = \Mockery::mock(Item::class);
        $item1->expects('toArray')->andReturn(['item1']);
        $item2 = \Mockery::mock(Item::class);
        $item2->expects('toArray')->andReturn(['item2']);
        $shipping = \Mockery::mock(Shipping::class);
        $shipping->expects('toArray')->andReturn(['shipping']);
        $testee = new PurchaseUnit(
            $amount,
            [],
            $shipping,
            'referenceId',
            'description',
            null,
            'customId',
            'invoiceId',
            'softDescriptor'
        );

        $this->assertEquals($amount, $testee->amount());
        $this->assertEquals('referenceId', $testee->referenceId());
        $this->assertEquals('description', $testee->description());
        $this->assertNull($testee->payee());
        $this->assertEquals('customId', $testee->customId());
        $this->assertEquals('invoiceId', $testee->invoiceId());
        $this->assertEquals('softDescriptor', $testee->softDescriptor());
        $this->assertEquals($shipping, $testee->shipping());
        $this->assertEquals([], $testee->items());

        $expected = [
            'reference_id' => 'referenceId',
            'amount' => ['amount'],
            'description' => 'description',
            'items' => [],
            'shipping' => ['shipping'],
            'custom_id' => 'customId',
            'invoice_id' => 'invoiceId',
            'soft_descriptor' => 'softDescriptor',
        ];

        $this->assertEquals($expected, $testee->toArray());
    }

    public function testDontDitchBecauseOfBreakdown() {

        $breakdown = \Mockery::mock(AmountBreakdown::class);
        $breakdown->expects('shipping')->andReturnNull();
        $breakdown->expects('itemTotal')->andReturnNull();
        $breakdown->expects('discount')->andReturnNull();
        $breakdown->expects('taxTotal')->andReturnNull();
        $breakdown->expects('shippingDiscount')->andReturnNull();
        $breakdown->expects('handling')->andReturnNull();
        $breakdown->expects('insurance')->andReturnNull();
        $amount = \Mockery::mock(Amount::class);
        $amount->expects('breakdown')->andReturn($breakdown);
        $amount->expects('value')->andReturn(0.0);
        $amount->expects('toArray')->andReturn(['amount']);
        $item1 = \Mockery::mock(Item::class);
        $item1->expects('toArray')->andReturn(['item1']);
        $item2 = \Mockery::mock(Item::class);
        $item2->expects('toArray')->andReturn(['item2']);
        $shipping = \Mockery::mock(Shipping::class);
        $shipping->expects('toArray')->andReturn(['shipping']);
        $testee = new PurchaseUnit(
            $amount,
            [],
            $shipping,
            'referenceId',
            'description',
            null,
            'customId',
            'invoiceId',
            'softDescriptor'
        );

        $expected = [
            'reference_id' => 'referenceId',
            'amount' => ['amount'],
            'description' => 'description',
            'shipping' => ['shipping'],
            'custom_id' => 'customId',
            'items' => [],
            'invoice_id' => 'invoiceId',
            'soft_descriptor' => 'softDescriptor',
        ];

        $this->assertEquals($expected, $testee->toArray());
    }

    public function testDitchBecauseOfBreakdown() {

        $breakdown = \Mockery::mock(AmountBreakdown::class);
        $breakdown->expects('shipping')->andReturnNull();
        $breakdown->expects('itemTotal')->andReturnNull();
        $breakdown->expects('discount')->andReturnNull();
        $breakdown->expects('taxTotal')->andReturnNull();
        $breakdown->expects('shippingDiscount')->andReturnNull();
        $breakdown->expects('handling')->andReturnNull();
        $breakdown->expects('insurance')->andReturnNull();
        $amount = \Mockery::mock(Amount::class);
        $amount->expects('breakdown')->andReturn($breakdown);
        $amount->expects('value')->andReturn(1.00);
        $amount->expects('toArray')->andReturn(['amount']);
        $item1 = \Mockery::mock(Item::class);
        $item1->expects('toArray')->andReturn(['item1']);
        $item2 = \Mockery::mock(Item::class);
        $item2->expects('toArray')->andReturn(['item2']);
        $shipping = \Mockery::mock(Shipping::class);
        $shipping->expects('toArray')->andReturn(['shipping']);
        $testee = new PurchaseUnit(
            $amount,
            [],
            $shipping,
            'referenceId',
            'description',
            null,
            'customId',
            'invoiceId',
            'softDescriptor'
        );

        $expected = [
            'reference_id' => 'referenceId',
            'amount' => ['amount'],
            'description' => 'description',
            'shipping' => ['shipping'],
            'custom_id' => 'customId',
            'invoice_id' => 'invoiceId',
            'soft_descriptor' => 'softDescriptor',
        ];

        $this->assertEquals($expected, $testee->toArray());
    }

    public function testPayee() {

        $amount = \Mockery::mock(Amount::class);
        $amount->expects('breakdown')->andReturnNull();
        $amount->expects('toArray')->andReturn(['amount']);
        $item1 = \Mockery::mock(Item::class);
        $item1->expects('toArray')->andReturn(['item1']);
        $item2 = \Mockery::mock(Item::class);
        $item2->expects('toArray')->andReturn(['item2']);
        $shipping = \Mockery::mock(Shipping::class);
        $shipping->expects('toArray')->andReturn(['shipping']);
        $payee = \Mockery::mock(Payee::class);
        $payee->expects('toArray')->andReturn(['payee']);
        $testee = new PurchaseUnit(
            $amount,
            [],
            $shipping,
            'referenceId',
            'description',
            $payee,
            'customId',
            'invoiceId',
            'softDescriptor'
        );

        $this->assertEquals($payee, $testee->payee());

        $expected = [
            'reference_id' => 'referenceId',
            'amount' => ['amount'],
            'description' => 'description',
            'items' => [],
            'shipping' => ['shipping'],
            'custom_id' => 'customId',
            'invoice_id' => 'invoiceId',
            'soft_descriptor' => 'softDescriptor',
            'payee' => ['payee'],
        ];

        $this->assertEquals($expected, $testee->toArray());
    }
}