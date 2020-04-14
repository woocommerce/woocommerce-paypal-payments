<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\TestCase;
use Mockery;

class PurchaseUnitTest extends TestCase
{

    public function test() {

        $amount = Mockery::mock(Amount::class);
        $amount->shouldReceive('breakdown')->andReturnNull();
        $amount->shouldReceive('toArray')->andReturn(['amount']);
        $item1 = Mockery::mock(Item::class);
        $item1->shouldReceive('toArray')->andReturn(['item1']);
        $item2 = Mockery::mock(Item::class);
        $item2->shouldReceive('toArray')->andReturn(['item2']);
        $shipping = Mockery::mock(Shipping::class);
        $shipping->shouldReceive('toArray')->andReturn(['shipping']);

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

    /**
     * @dataProvider dataForDitchTests
     * @param array $items
     * @param Amount $amount
     * @param bool $doDitch
     */
    public function testDitchMethod(array $items, Amount $amount, bool $doDitch, string $message) {
        $testee = new PurchaseUnit(
            $amount,
            $items
        );

        $array = $testee->toArray();
        $resultItems = $doDitch === ! array_key_exists('items', $array);
        $resultBreakdown = $doDitch === ! array_key_exists('breakdown', $array['amount']);
        $this->assertTrue($resultItems, $message);
        $this->assertTrue($resultBreakdown, $message);

    }


    public function dataForDitchTests() : array {
        $data = [
            'default' => [
                'message' => 'Items should not be ditched.',
                'ditch' => false,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 26,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shippingDiscount' => null,
                    'handling' => null,
                    'insurance' => null,
                ],
            ],
            'dont_ditch_with_discount' => [
                'message' => 'Items should not be ditched.',
                'ditch' => false,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 23,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => null,
                    'discount' => 3,
                    'shippingDiscount' => null,
                    'handling' => null,
                    'insurance' => null,
                ],
            ],
            'ditch_with_discount' => [
                'message' => 'Items should be ditched because of discount.',
                'ditch' => true,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 25,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => null,
                    'discount' => 3,
                    'shippingDiscount' => null,
                    'handling' => null,
                    'insurance' => null,
                ],
            ],
            'dont_ditch_with_shipping_discount' => [
                'message' => 'Items should not be ditched.',
                'ditch' => false,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 23,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shippingDiscount' => 3,
                    'handling' => null,
                    'insurance' => null,
                ],
            ],
            'ditch_with_handling' => [
                'message' => 'Items should be ditched because of handling.',
                'ditch' => true,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 26,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shippingDiscount' => null,
                    'handling' => 3,
                    'insurance' => null,
                ],
            ],
            'dont_ditch_with_handling' => [
                'message' => 'Items should not be ditched.',
                'ditch' => false,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 29,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shippingDiscount' => null,
                    'handling' => 3,
                    'insurance' => null,
                ],
            ],
            'ditch_with_insurance' => [
                'message' => 'Items should be ditched because of insurance.',
                'ditch' => true,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 26,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shippingDiscount' => null,
                    'handling' => null,
                    'insurance' => 3,
                ],
            ],
            'dont_ditch_with_insurance' => [
                'message' => 'Items should not be ditched.',
                'ditch' => false,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 29,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shippingDiscount' => null,
                    'handling' => null,
                    'insurance' => 3,
                ],
            ],
            'ditch_with_shipping_discount' => [
                'message' => 'Items should be ditched because of shipping discount.',
                'ditch' => true,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 25,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shippingDiscount' => 3,
                    'handling' => null,
                    'insurance' => null,
                ],
            ],
            'dont_ditch_with_shipping' => [
                'message' => 'Items should not be ditched.',
                'ditch' => false,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 29,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => 3,
                    'discount' => null,
                    'shippingDiscount' => null,
                    'handling' => null,
                    'insurance' => null,
                ],
            ],
            'ditch_because_shipping' => [
                'message' => 'Items should be ditched because of shipping.',
                'ditch' => true,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 28,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => 3,
                    'discount' => null,
                    'shippingDiscount' => null,
                    'handling' => null,
                    'insurance' => null,
                ],
            ],
            'ditch_items_total' => [
                'message' => 'Items should be ditched because the item total does not add up.',
                'ditch' => true,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 26,
                'breakdown' => [
                    'itemTotal' => 11,
                    'taxTotal' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shippingDiscount' => null,
                    'handling' => null,
                    'insurance' => null,
                ],
            ],
            'ditch_tax_total' => [
                'message' => 'Items should be ditched because the tax total does not add up.',
                'ditch' => true,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 26,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 5,
                    'shipping' => null,
                    'discount' => null,
                    'shippingDiscount' => null,
                    'handling' => null,
                    'insurance' => null,
                ],
            ],
            'ditch_total_amount' => [
                'message' => 'Items should be ditched because the total amount is way out of order.',
                'ditch' => true,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                    ],
                ],
                'amount' => 260,
                'breakdown' => [
                    'itemTotal' => 20,
                    'taxTotal' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shippingDiscount' => null,
                    'handling' => null,
                    'insurance' => null,
                ],
            ],
        ];

        $values = [];
        foreach ($data as $testKey => $test) {
            $items = [];
            foreach ($test['items'] as $key => $item) {
                $unitAmount = Mockery::mock(Money::class);
                $unitAmount->shouldReceive('value')->andReturn($item['value']);
                $tax = Mockery::mock(Money::class);
                $tax->shouldReceive('value')->andReturn($item['tax']);
                $items[$key] = Mockery::mock(Item::class);
                $items[$key]->shouldReceive('unitAmount')->andReturn($unitAmount);
                $items[$key]->shouldReceive('tax')->andReturn($tax);
                $items[$key]->shouldReceive('quantity')->andReturn($item['quantity']);
                $items[$key]->shouldReceive('toArray')->andReturn([]);
            }
            $breakdown = null;
            if ($test['breakdown']) {
                $breakdown = Mockery::mock(AmountBreakdown::class);
                foreach ($test['breakdown'] as $method => $value) {
                    $breakdown->shouldReceive($method)->andReturnUsing(function() use ($value) {
                        if (! is_numeric($value)) {
                            return null;
                        }

                        $money = Mockery::mock(Money::class);
                        $money->shouldReceive('value')->andReturn($value);
                        return $money;
                    });
                }
            }
            $amount = Mockery::mock(Amount::class);
            $amount->shouldReceive('toArray')->andReturn(['value' => [], 'breakdown' => []]);
            $amount->shouldReceive('value')->andReturn($test['amount']);
            $amount->shouldReceive('breakdown')->andReturn($breakdown);

            $values[$testKey] = [
                $items,
                $amount,
                $test['ditch'],
                $test['message']
            ];
        }

        return $values;
    }

    public function testPayee() {

        $amount = Mockery::mock(Amount::class);
        $amount->shouldReceive('breakdown')->andReturnNull();
        $amount->shouldReceive('toArray')->andReturn(['amount']);
        $item1 = Mockery::mock(Item::class);
        $item1->shouldReceive('toArray')->andReturn(['item1']);
        $item2 = Mockery::mock(Item::class);
        $item2->shouldReceive('toArray')->andReturn(['item2']);
        $shipping = Mockery::mock(Shipping::class);
        $shipping->shouldReceive('toArray')->andReturn(['shipping']);
        $payee = Mockery::mock(Payee::class);
        $payee->shouldReceive('toArray')->andReturn(['payee']);
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