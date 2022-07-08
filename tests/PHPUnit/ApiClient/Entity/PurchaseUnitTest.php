<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\TestCase;
use Mockery;

class PurchaseUnitTest extends TestCase
{

    public function test()
    {

        $amount = Mockery::mock(
            Amount::class,
            [
                'breakdown' => null,
                'to_array' => ['amount'],
            ]
        );

        $item1 = Mockery::mock(
            Item::class,
            [
                'to_array' => ['item1'],
                'category' => Item::DIGITAL_GOODS,
            ]
        );

        $item2 = Mockery::mock(
            Item::class,
            [
                'to_array' => ['item2'],
                'category' => Item::PHYSICAL_GOODS,
            ]
        );

        $shipping = Mockery::mock(Shipping::class, ['to_array' => ['shipping']]);

        $testee = new PurchaseUnit(
            $amount,
            [$item1, $item2],
            $shipping,
            'referenceId',
            'description',
            null,
            'customId',
            'invoiceId',
            'softDescriptor'
        );

        $this->assertEquals($amount, $testee->amount());
        $this->assertEquals('referenceId', $testee->reference_id());
        $this->assertEquals('description', $testee->description());
        $this->assertNull($testee->payee());
        $this->assertEquals('customId', $testee->custom_id());
        $this->assertEquals('invoiceId', $testee->invoice_id());
        $this->assertEquals('softDescriptor', $testee->soft_descriptor());
        $this->assertEquals($shipping, $testee->shipping());
        $this->assertEquals([$item1, $item2], $testee->items());
        self::assertTrue($testee->contains_physical_goods());

        $expected = [
            'reference_id' => 'referenceId',
            'amount' => ['amount'],
            'description' => 'description',
            'items' => [['item1'], ['item2']],
            'shipping' => ['shipping'],
            'custom_id' => 'customId',
            'invoice_id' => 'invoiceId',
            'soft_descriptor' => 'softDescriptor',
        ];

        $this->assertEquals($expected, $testee->to_array());
    }

    /**
     * @dataProvider dataForDitchTests
     * @param array $items
     * @param Amount $amount
     * @param bool $doDitch
     */
    public function testDitchMethod(array $items, Amount $amount, bool $doDitch, string $message)
    {
        $testee = new PurchaseUnit(
            $amount,
            $items
        );

        $array = $testee->to_array();
        $resultItems = $doDitch === ! array_key_exists('items', $array);
        $resultBreakdown = $doDitch === ! array_key_exists('breakdown', $array['amount']);
        $this->assertTrue($resultItems, $message);
        $this->assertTrue($resultBreakdown, $message);
    }

    public function dataForDitchTests() : array
    {
        $data = [
            'default' => [
                'message' => 'Items should not be ditched.',
                'ditch' => false,
                'items' => [
                    [
                        'value' => 10,
                        'quantity' => 2,
                        'tax' => 3,
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 26,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shipping_discount' => null,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 23,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => null,
                    'discount' => 3,
                    'shipping_discount' => null,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 25,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => null,
                    'discount' => 3,
                    'shipping_discount' => null,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 23,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shipping_discount' => 3,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 26,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shipping_discount' => null,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 29,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shipping_discount' => null,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 26,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shipping_discount' => null,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 29,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shipping_discount' => null,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 25,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shipping_discount' => 3,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 29,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => 3,
                    'discount' => null,
                    'shipping_discount' => null,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 28,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => 3,
                    'discount' => null,
                    'shipping_discount' => null,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 26,
                'breakdown' => [
                    'item_total' => 11,
                    'tax_total' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shipping_discount' => null,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 26,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 5,
                    'shipping' => null,
                    'discount' => null,
                    'shipping_discount' => null,
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
                        'category' => Item::PHYSICAL_GOODS,
                    ],
                ],
                'amount' => 260,
                'breakdown' => [
                    'item_total' => 20,
                    'tax_total' => 6,
                    'shipping' => null,
                    'discount' => null,
                    'shipping_discount' => null,
                    'handling' => null,
                    'insurance' => null,
                ],
            ],
        ];

        $values = [];
        foreach ($data as $testKey => $test) {
            $items = [];
            foreach ($test['items'] as $key => $item) {
                $unitAmount = new Money($item['value'], 'EUR');
                $tax = new Money($item['tax'], 'EUR');
                $items[$key] = Mockery::mock(
                    Item::class,
                    [
                        'unit_amount' => $unitAmount,
                        'tax' => $tax,
                        'quantity'=> $item['quantity'],
                        'category' => $item['category'],
                        'to_array' => [],
                    ]
                );
            }
            $breakdown = null;
            if ($test['breakdown']) {
                $breakdown = Mockery::mock(AmountBreakdown::class);
                foreach ($test['breakdown'] as $method => $value) {
                    $breakdown->shouldReceive($method)->andReturnUsing(function () use ($value) {
                        if (! is_numeric($value)) {
                            return null;
                        }

                        $money = new Money($value, 'EUR');
                        return $money;
                    });
                }
            }
            $amount = Mockery::mock(Amount::class);
            $amount->shouldReceive('to_array')->andReturn(['value' => number_format( $test['amount'], 2, '.', '' ), 'breakdown' => []]);
            $amount->shouldReceive('value_str')->andReturn(number_format( $test['amount'], 2, '.', '' ));
            $amount->shouldReceive('currency_code')->andReturn('EUR');
            $amount->shouldReceive('breakdown')->andReturn($breakdown);

            $values[$testKey] = [
                $items,
                $amount,
                $test['ditch'],
                $test['message'],
            ];
        }

        return $values;
    }

    public function testPayee()
    {
        $amount = Mockery::mock(Amount::class);
        $amount->shouldReceive('breakdown')->andReturnNull();
        $amount->shouldReceive('to_array')->andReturn(['amount']);
        $item1 = Mockery::mock(Item::class);
        $item1->shouldReceive('to_array')->andReturn(['item1']);
        $item2 = Mockery::mock(Item::class);
        $item2->shouldReceive('to_array')->andReturn(['item2']);
        $shipping = Mockery::mock(Shipping::class);
        $shipping->shouldReceive('to_array')->andReturn(['shipping']);
        $payee = Mockery::mock(Payee::class);
        $payee->shouldReceive('to_array')->andReturn(['payee']);
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

        $this->assertEquals($expected, $testee->to_array());
    }
}
