<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Helper\PurchaseUnitSanitizer;
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
            'customId',
            'invoiceId',
            'softDescriptor'
        );

        $this->assertEquals($amount, $testee->amount());
        $this->assertEquals('referenceId', $testee->reference_id());
        $this->assertEquals('description', $testee->description());
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
	 * @param bool|array $doDitch
	 * @param string $message
	 */
    public function testDitchMethod(array $items, Amount $amount, $doDitch, string $message)
    {
		if (is_array($doDitch)) {
			$doDitchItems = $doDitch['items'];
			$doDitchBreakdown = $doDitch['breakdown'];
			$doDitchTax = $doDitch['tax'];
		} else {
			$doDitchItems = $doDitch;
			$doDitchBreakdown = $doDitch;
			$doDitchTax = $doDitch;
		}

        $testee = new PurchaseUnit(
            $amount,
            $items
        );

		$testee->set_sanitizer(new PurchaseUnitSanitizer(PurchaseUnitSanitizer::MODE_DITCH));

        $array = $testee->to_array();
        $resultItems = $doDitchItems === ! array_key_exists('items', $array);

        $resultBreakdown = $doDitchBreakdown === ! array_key_exists('breakdown', $array['amount']);
        $this->assertTrue($resultItems, $message);
        $this->assertTrue($resultBreakdown, $message);

		foreach ($array['items'] ?? [] as $item) {
			$resultTax = $doDitchTax === ! array_key_exists('tax', $item);
			$this->assertTrue($resultTax, $message);
		}
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
			'ditch_items_total_but_not_breakdown' => [
				'message' => 'Items should be ditched because the item total does not add up. But not breakdown because it adds up.',
				'ditch' => [
					'items' => true,
					'breakdown' => false,
					'tax' => true,
				],
				'items' => [
					[
						'value' => 11,
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
			'ditch_items_tax_with_incorrect_tax_total' => [
				'message' => 'Ditch tax from items. Items should not be ditched because the mismatch is on the tax.',
				'ditch' => [
					'items' => false,
					'breakdown' => false,
					'tax' => true,
				],
				'items' => [
					[
						'value' => 10,
						'quantity' => 2,
						'tax' => 4,
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
                        'to_array' => [
							'unit_amount' => $unitAmount->to_array(),
							'tax' => $tax->to_array(),
							'quantity'=> $item['quantity'],
							'category' => $item['category'],
						],
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

				$breakdown
					->shouldReceive('to_array')
					->andReturn(
						array_map(
							function ($value) {
								return $value ? (new Money($value, 'EUR'))->to_array() : null;
							},
							$test['breakdown']
						)
					);
            }

			$amountMoney = new Money($test['amount'], 'EUR');
            $amount = Mockery::mock(Amount::class);
            $amount
				->shouldReceive('to_array')
				->andReturn([
					'value' => $amountMoney->value_str(),
					'currency_code' => $amountMoney->currency_code(),
					'breakdown' => $breakdown ? $breakdown->to_array() : [],
				]);
            $amount->shouldReceive('value_str')->andReturn($amountMoney->value_str());
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

	/**
	 * @dataProvider dataForExtraLineTests
	 * @param array $items
	 * @param Amount $amount
	 * @param array $expected
	 * @param string $message
	 */
	public function testExtraLineMethod(array $items, Amount $amount, array $expected, string $message)
	{
		$testee = new PurchaseUnit(
			$amount,
			$items
		);

		$testee->set_sanitizer(new PurchaseUnitSanitizer(PurchaseUnitSanitizer::MODE_EXTRA_LINE, $expected['extra_line_name'] ?? null));

		$countItemsBefore = count($items);
		$array = $testee->to_array();
		$countItemsAfter = count($array['items']);
		$extraItem = array_pop($array['items']);

		$this->assertEquals($countItemsBefore + 1, $countItemsAfter, $message);
		$this->assertEquals($expected['extra_line_value'], $extraItem['unit_amount']['value'], $message);
		$this->assertEquals($expected['extra_line_name'] ?? PurchaseUnitSanitizer::EXTRA_LINE_NAME, $extraItem['name'], $message);

		foreach ($array['items'] as $i => $item) {
			$this->assertEquals($expected['item_value'][$i], $item['unit_amount']['value'], $message);
		}
	}

	public function dataForExtraLineTests() : array
	{
		$data = [
			'default' => [
				'message' => 'Extra line should be added with price 0.01 and line amount 10.',
				'expected' => [
					'item_value' => [10],
					'extra_line_value' => 0.01,
				],
				'items' => [
					[
						'value' => 10,
						'quantity' => 2,
						'tax' => 3,
						'category' => Item::PHYSICAL_GOODS,
					],
				],
				'amount' => 26.01,
				'breakdown' => [
					'item_total' => 20.01,
					'tax_total' => 6,
					'shipping' => null,
					'discount' => null,
					'shipping_discount' => null,
					'handling' => null,
					'insurance' => null,
				],
			],
			'with_custom_name' => [
				'message' => 'Extra line should be added with price 0.01 and line amount 10.',
				'expected' => [
					'item_value' => [10],
					'extra_line_value' => 0.01,
					'extra_line_name' => 'My custom line name',
				],
				'items' => [
					[
						'value' => 10,
						'quantity' => 2,
						'tax' => 3,
						'category' => Item::PHYSICAL_GOODS,
					],
				],
				'amount' => 26.01,
				'breakdown' => [
					'item_total' => 20.01,
					'tax_total' => 6,
					'shipping' => null,
					'discount' => null,
					'shipping_discount' => null,
					'handling' => null,
					'insurance' => null,
				],
			],
			'with_rounding_down' => [
				'message' => 'Extra line should be added with price 0.01 and line amount 10.00.',
				'expected' => [
					'item_value' => [10.00],
					'extra_line_value' => 0.01
				],
				'items' => [
					[
						'value' => 10.005,
						'quantity' => 2,
						'tax' => 3,
						'category' => Item::PHYSICAL_GOODS,
					],
				],
				'amount' => 26.01,
				'breakdown' => [
					'item_total' => 20.01,
					'tax_total' => 6,
					'shipping' => null,
					'discount' => null,
					'shipping_discount' => null,
					'handling' => null,
					'insurance' => null,
				],
			],
			'with_two_rounding_down' => [
				'message' => 'Extra line should be added with price 0.03 and lines amount 10.00 and 4.99.',
				'expected' => [
					'item_value' => [10.00, 4.99],
					'extra_line_value' => 0.03
				],
				'items' => [
					[
						'value' => 10.005,
						'quantity' => 2,
						'tax' => 3,
						'category' => Item::PHYSICAL_GOODS,
					],
					[
						'value' => 5,
						'quantity' => 2,
						'tax' => 3,
						'category' => Item::PHYSICAL_GOODS,
					],
				],
				'amount' => 36.01,
				'breakdown' => [
					'item_total' => 30.01,
					'tax_total' => 6,
					'shipping' => null,
					'discount' => null,
					'shipping_discount' => null,
					'handling' => null,
					'insurance' => null,
				],
			],
			'with_many_roundings_down' => [
				'message' => 'Extra line should be added with price 0.01 and lines amount 10.00, 5.00 and 6.66.',
				'expected' => [
					'item_value' => [10.00, 4.99, 6.66],
					'extra_line_value' => 0.02
				],
				'items' => [
					[
						'value' => 10.005,
						'quantity' => 1,
						'tax' => 3,
						'category' => Item::PHYSICAL_GOODS,
					],
					[
						'value' => 5.001,
						'quantity' => 1,
						'tax' => 3,
						'category' => Item::PHYSICAL_GOODS,
					],
					[
						'value' => 6.666,
						'quantity' => 1,
						'tax' => 3,
						'category' => Item::PHYSICAL_GOODS,
					],
				],
				'amount' => 27.67,
				'breakdown' => [
					'item_total' => 21.67,
					'tax_total' => 6,
					'shipping' => null,
					'discount' => null,
					'shipping_discount' => null,
					'handling' => null,
					'insurance' => null,
				],
			]
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
					]
				);

				$items[$key]->shouldReceive('to_array')->andReturnUsing(function (bool $roundToFloor = false) use ($unitAmount, $tax, $item) {
					return [
						'unit_amount' => $unitAmount->to_array($roundToFloor),
						'tax' => $tax->to_array(),
						'quantity'=> $item['quantity'],
						'category' => $item['category'],
					];
				});

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

				$breakdown
					->shouldReceive('to_array')
					->andReturn(
						array_map(
							function ($value) {
								return $value ? (new Money($value, 'EUR'))->to_array() : null;
							},
							$test['breakdown']
						)
					);
			}

			$amountMoney = new Money($test['amount'], 'EUR');
			$amount = Mockery::mock(Amount::class);
			$amount
				->shouldReceive('to_array')
				->andReturn([
					'value' => $amountMoney->value_str(),
					'currency_code' => $amountMoney->currency_code(),
					'breakdown' => $breakdown ? $breakdown->to_array() : [],
				]);
			$amount->shouldReceive('value_str')->andReturn($amountMoney->value_str());
			$amount->shouldReceive('currency_code')->andReturn('EUR');
			$amount->shouldReceive('breakdown')->andReturn($breakdown);

			$values[$testKey] = [
				$items,
				$amount,
				$test['expected'],
				$test['message'],
			];
		}

		return $values;
	}
}
