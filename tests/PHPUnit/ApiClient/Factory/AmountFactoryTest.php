<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Amount;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Item;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Money;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\TestCase;
use Mockery;
use function Brain\Monkey\Functions\expect;

class AmountFactoryTest extends TestCase
{

    public function testFromWcCartDefault()
    {
        $itemFactory = Mockery::mock(ItemFactory::class);
        $testee = new AmountFactory($itemFactory);

        $expectedCurrency = 'EUR';
        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->shouldReceive('get_total')
            ->withAnyArgs()
            ->andReturn(1);
        $cart
            ->shouldReceive('get_cart_contents_total')
            ->andReturn(2);
        $cart
            ->shouldReceive('get_discount_total')
            ->andReturn(3);
        $cart
            ->shouldReceive('get_shipping_total')
            ->andReturn(4);
        $cart
            ->shouldReceive('get_shipping_tax')
            ->andReturn(5);
        $cart
            ->shouldReceive('get_cart_contents_tax')
            ->andReturn(6);
        $cart
            ->shouldReceive('get_discount_tax')
            ->andReturn(7);

        expect('get_woocommerce_currency')->andReturn($expectedCurrency);
        $result = $testee->fromWcCart($cart);
        $this->assertEquals($expectedCurrency, $result->currencyCode());
        $this->assertEquals((float) 1, $result->value());
        $this->assertEquals((float) 10, $result->breakdown()->discount()->value());
        $this->assertEquals($expectedCurrency, $result->breakdown()->discount()->currencyCode());
        $this->assertEquals((float) 9, $result->breakdown()->shipping()->value());
        $this->assertEquals($expectedCurrency, $result->breakdown()->shipping()->currencyCode());
        $this->assertEquals((float) 5, $result->breakdown()->itemTotal()->value());
        $this->assertEquals($expectedCurrency, $result->breakdown()->itemTotal()->currencyCode());
        $this->assertEquals((float) 13, $result->breakdown()->taxTotal()->value());
        $this->assertEquals($expectedCurrency, $result->breakdown()->taxTotal()->currencyCode());
    }

    public function testFromWcCartNoDiscount()
    {
        $itemFactory = Mockery::mock(ItemFactory::class);
        $testee = new AmountFactory($itemFactory);

        $expectedCurrency = 'EUR';
        $expectedTotal = 1;
        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->shouldReceive('get_total')
            ->withAnyArgs()
            ->andReturn($expectedTotal);
        $cart
            ->shouldReceive('get_cart_contents_total')
            ->andReturn(2);
        $cart
            ->shouldReceive('get_discount_total')
            ->andReturn(0);
        $cart
            ->shouldReceive('get_shipping_total')
            ->andReturn(4);
        $cart
            ->shouldReceive('get_shipping_tax')
            ->andReturn(5);
        $cart
            ->shouldReceive('get_cart_contents_tax')
            ->andReturn(6);
        $cart
            ->shouldReceive('get_discount_tax')
            ->andReturn(0);

        expect('get_woocommerce_currency')->andReturn($expectedCurrency);
        $result = $testee->fromWcCart($cart);
        $this->assertNull($result->breakdown()->discount());
    }

    public function testFromWcOrderDefault()
    {
        $itemFactory = Mockery::mock(ItemFactory::class);
        $order = Mockery::mock(\WC_Order::class);
        $unitAmount = Mockery::mock(Money::class);
        $unitAmount
            ->shouldReceive('value')
            ->andReturn(3);
        $tax = Mockery::mock(Money::class);
        $tax
            ->shouldReceive('value')
            ->andReturn(1);
        $item = Mockery::mock(Item::class);
        $item
            ->shouldReceive('quantity')
            ->andReturn(2);
        $item
            ->shouldReceive('unitAmount')
            ->andReturn($unitAmount);
        $item
            ->shouldReceive('tax')
            ->andReturn($tax);
        $itemFactory
            ->expects('fromWcOrder')
            ->with($order)
            ->andReturn([$item]);
        $testee = new AmountFactory($itemFactory);

        $expectedCurrency = 'EUR';
        $order
            ->shouldReceive('get_total')
            ->andReturn(100);
        $order
            ->shouldReceive('get_currency')
            ->andReturn($expectedCurrency);
        $order
            ->shouldReceive('get_shipping_total')
            ->andReturn(1);
        $order
            ->shouldReceive('get_shipping_tax')
            ->andReturn(.5);
        $order
            ->shouldReceive('get_total_discount')
            ->with(false)
            ->andReturn(3);

        $result = $testee->fromWcOrder($order);
        $this->assertEquals((float) 3, $result->breakdown()->discount()->value());
        $this->assertEquals((float) 6, $result->breakdown()->itemTotal()->value());
        $this->assertEquals((float) 1.5, $result->breakdown()->shipping()->value());
        $this->assertEquals((float) 100, $result->value());
        $this->assertEquals((float) 2, $result->breakdown()->taxTotal()->value());
        $this->assertEquals($expectedCurrency, $result->breakdown()->discount()->currencyCode());
        $this->assertEquals($expectedCurrency, $result->breakdown()->itemTotal()->currencyCode());
        $this->assertEquals($expectedCurrency, $result->breakdown()->shipping()->currencyCode());
        $this->assertEquals($expectedCurrency, $result->breakdown()->taxTotal()->currencyCode());
        $this->assertEquals($expectedCurrency, $result->currencyCode());
    }

    public function testFromWcOrderDiscountIsNull()
    {
        $itemFactory = Mockery::mock(ItemFactory::class);
        $order = Mockery::mock(\WC_Order::class);
        $unitAmount = Mockery::mock(Money::class);
        $unitAmount
            ->shouldReceive('value')
            ->andReturn(3);
        $tax = Mockery::mock(Money::class);
        $tax
            ->shouldReceive('value')
            ->andReturn(1);
        $item = Mockery::mock(Item::class);
        $item
            ->shouldReceive('quantity')
            ->andReturn(2);
        $item
            ->shouldReceive('unitAmount')
            ->andReturn($unitAmount);
        $item
            ->shouldReceive('tax')
            ->andReturn($tax);
        $itemFactory
            ->expects('fromWcOrder')
            ->with($order)
            ->andReturn([$item]);
        $testee = new AmountFactory($itemFactory);

        $expectedCurrency = 'EUR';
        $order
            ->shouldReceive('get_total')
            ->andReturn(100);
        $order
            ->shouldReceive('get_currency')
            ->andReturn($expectedCurrency);
        $order
            ->shouldReceive('get_shipping_total')
            ->andReturn(1);
        $order
            ->shouldReceive('get_shipping_tax')
            ->andReturn(.5);
        $order
            ->shouldReceive('get_total_discount')
            ->with(false)
            ->andReturn(0);

        $result = $testee->fromWcOrder($order);
        $this->assertNull($result->breakdown()->discount());
    }

    /**
     * @dataProvider dataFromPayPalResponse
     * @param $response
     */
    public function testFromPayPalResponse($response, $expectsException)
    {
        $itemFactory = Mockery::mock(ItemFactory::class);
        $testee = new AmountFactory($itemFactory);
        if ($expectsException) {
            $this->expectException(RuntimeException::class);
        }
        $result = $testee->fromPayPalResponse($response);
        if ($expectsException) {
            return;
        }
        $this->assertEquals($response->value, $result->value());
        $this->assertEquals($response->currency_code, $result->currencyCode());
        $breakdown = $result->breakdown();
        if (! isset($response->breakdown)) {
            $this->assertNull($breakdown);
            return;
        }
        if ($breakdown->shipping()) {
            $this->assertEquals($response->breakdown->shipping->value, $breakdown->shipping()->value());
            $this->assertEquals($response->breakdown->shipping->currency_code, $breakdown->shipping()->currencyCode());
        } else {
            $this->assertTrue(! isset($response->breakdown->shipping));
        }
        if ($breakdown->itemTotal()) {
            $this->assertEquals($response->breakdown->item_total->value, $breakdown->itemTotal()->value());
            $this->assertEquals($response->breakdown->item_total->currency_code, $breakdown->itemTotal()->currencyCode());
        } else {
            $this->assertTrue(! isset($response->breakdown->item_total));
        }
        if ($breakdown->taxTotal()) {
            $this->assertEquals($response->breakdown->tax_total->value, $breakdown->taxTotal()->value());
            $this->assertEquals($response->breakdown->tax_total->currency_code, $breakdown->taxTotal()->currencyCode());
        } else {
            $this->assertTrue(! isset($response->breakdown->tax_total));
        }
        if ($breakdown->handling()) {
            $this->assertEquals($response->breakdown->handling->value, $breakdown->handling()->value());
            $this->assertEquals($response->breakdown->handling->currency_code, $breakdown->handling()->currencyCode());
        } else {
            $this->assertTrue(! isset($response->breakdown->handling));
        }
        if ($breakdown->insurance()) {
            $this->assertEquals($response->breakdown->insurance->value, $breakdown->insurance()->value());
            $this->assertEquals($response->breakdown->insurance->currency_code, $breakdown->insurance()->currencyCode());
        } else {
            $this->assertTrue(! isset($response->breakdown->insurance));
        }
        if ($breakdown->shippingDiscount()) {
            $this->assertEquals($response->breakdown->shipping_discount->value, $breakdown->shippingDiscount()->value());
            $this->assertEquals($response->breakdown->shipping_discount->currency_code, $breakdown->shippingDiscount()->currencyCode());
        } else {
            $this->assertTrue(! isset($response->breakdown->shipping_discount));
        }
        if ($breakdown->discount()) {
            $this->assertEquals($response->breakdown->discount->value, $breakdown->discount()->value());
            $this->assertEquals($response->breakdown->discount->currency_code, $breakdown->discount()->currencyCode());
        } else {
            $this->assertTrue(! isset($response->breakdown->discount));
        }
    }

    public function dataFromPayPalResponse() : array
    {
        return [
            'no_value' => [
                (object) [
                    "currency_code" => "A",
                ],
                true,
            ],
            'no_currency_code' => [
                (object) [
                    "value" => (float) 1,
                ],
                true,
            ],
            'no_value_in_breakdown' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "currency_code" => "B",
                        ],
                    ],
                ],
                true,
            ],
            'no_currency_code_in_breakdown' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                        ],
                    ],
                ],
                true,
            ],
            'default' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_item_total' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                    ],
                ],
                false,
            ],
            'no_tax_total' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_handling' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_insurance' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_shipping_discount' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "discount" => (object) [
                            "value" => (float) 2,
                            "currency_code" => "B",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_discount' => [
                (object) [
                    "value" => (float) 1,
                    "currency_code" => "A",
                    "breakdown" => (object) [
                        "shipping_discount" => (object) [
                            "value" => (float) 3,
                            "currency_code" => "C",
                        ],
                        "insurance" => (object) [
                            "value" => (float) 4,
                            "currency_code" => "D",
                        ],
                        "handling" => (object) [
                            "value" => (float) 5,
                            "currency_code" => "E",
                        ],
                        "tax_total" => (object) [
                            "value" => (float) 6,
                            "currency_code" => "F",
                        ],
                        "shipping" => (object) [
                            "value" => (float) 7,
                            "currency_code" => "G",
                        ],
                        "item_total" => (object) [
                            "value" => (float) 8,
                            "currency_code" => "H",
                        ],
                    ],
                ],
                false,
            ],
            'no_shipping' => [
            (object) [
                "value" => (float) 1,
                "currency_code" => "A",
                "breakdown" => (object) [
                    "discount" => (object) [
                        "value" => (float) 2,
                        "currency_code" => "B",
                    ],
                    "shipping_discount" => (object) [
                        "value" => (float) 3,
                        "currency_code" => "C",
                    ],
                    "insurance" => (object) [
                        "value" => (float) 4,
                        "currency_code" => "D",
                    ],
                    "handling" => (object) [
                        "value" => (float) 5,
                        "currency_code" => "E",
                    ],
                    "tax_total" => (object) [
                        "value" => (float) 6,
                        "currency_code" => "F",
                    ],
                    "item_total" => (object) [
                        "value" => (float) 8,
                        "currency_code" => "H",
                    ],
                ],
            ],
            false,
            ],
        ];
    }
}
