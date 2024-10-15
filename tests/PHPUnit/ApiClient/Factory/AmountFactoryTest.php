<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Helper\CurrencyGetterStub;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class AmountFactoryTest extends TestCase
{
	private $currency = 'EUR';
	private $currencyGetter;

	private $itemFactory;
	private $moneyFactory;
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		$this->itemFactory = Mockery::mock(ItemFactory::class);
		$this->moneyFactory = new MoneyFactory();
		$this->currencyGetter = new CurrencyGetterStub($this->currency);
		$this->testee = new AmountFactory($this->itemFactory, $this->moneyFactory, $this->currencyGetter);
	}

	public function testFromWcCartDefault()
    {
        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->shouldReceive('get_total')
            ->withAnyArgs()
            ->andReturn(13);
        $cart
            ->shouldReceive('get_subtotal')
            ->andReturn(5);
        $cart
            ->shouldReceive('get_fee_total')
            ->andReturn(3);
        $cart
            ->shouldReceive('get_discount_total')
            ->andReturn(1);
        $cart
            ->shouldReceive('get_shipping_total')
            ->andReturn(2);
        $cart
            ->shouldReceive('get_total_tax')
            ->andReturn(4);

        $woocommerce = Mockery::mock(\WooCommerce::class);
        $session = Mockery::mock(\WC_Session::class);
        when('WC')->justReturn($woocommerce);
        $woocommerce->session = $session;
        $session->shouldReceive('get')->andReturn([]);

        $result = $this->testee->from_wc_cart($cart);
        $this->assertEquals($this->currency, $result->currency_code());
        $this->assertEquals((float) 13, $result->value());
        $this->assertEquals((float) 1, $result->breakdown()->discount()->value());
        $this->assertEquals($this->currency, $result->breakdown()->discount()->currency_code());
        $this->assertEquals((float) 2, $result->breakdown()->shipping()->value());
        $this->assertEquals($this->currency, $result->breakdown()->shipping()->currency_code());
        $this->assertEquals((float) 8, $result->breakdown()->item_total()->value());
        $this->assertEquals($this->currency, $result->breakdown()->item_total()->currency_code());
        $this->assertEquals((float) 4, $result->breakdown()->tax_total()->value());
        $this->assertEquals($this->currency, $result->breakdown()->tax_total()->currency_code());
    }

    public function testFromWcCartNoDiscount()
    {
        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->shouldReceive('get_total')
            ->withAnyArgs()
            ->andReturn(11);
		$cart
			->shouldReceive('get_subtotal')
			->andReturn(5);
		$cart
			->shouldReceive('get_fee_total')
			->andReturn(0);
        $cart
            ->shouldReceive('get_discount_total')
            ->andReturn(0);
        $cart
            ->shouldReceive('get_shipping_total')
            ->andReturn(2);
		$cart
			->shouldReceive('get_total_tax')
			->andReturn(4);

        $woocommerce = Mockery::mock(\WooCommerce::class);
        $session = Mockery::mock(\WC_Session::class);
        when('WC')->justReturn($woocommerce);
        $woocommerce->session = $session;
        $session->shouldReceive('get')->andReturn([]);
        $result = $this->testee->from_wc_cart($cart);
        $this->assertNull($result->breakdown()->discount());
		$this->assertEquals((float) 11, $result->value());
		$this->assertEquals((float) 2, $result->breakdown()->shipping()->value());
		$this->assertEquals((float) 5, $result->breakdown()->item_total()->value());
		$this->assertEquals((float) 4, $result->breakdown()->tax_total()->value());
    }

    public function testFromWcOrderDefault()
    {
        $order = Mockery::mock(\WC_Order::class);
        $unitAmount = Mockery::mock(Money::class);
        $unitAmount
            ->shouldReceive('value')
            ->andReturn(3);

        $item = Mockery::mock(Item::class);
        $item
            ->shouldReceive('quantity')
            ->andReturn(2);
        $item
            ->shouldReceive('unit_amount')
            ->andReturn($unitAmount);
        $this->itemFactory
            ->expects('from_wc_order')
            ->with($order)
            ->andReturn([$item]);

        $order
            ->shouldReceive('get_payment_method')
            ->andReturn(PayPalGateway::ID);

        $order
            ->shouldReceive('get_total')
            ->andReturn(10);
        $order
            ->shouldReceive('get_subtotal')
            ->andReturn(6);
        $order
            ->shouldReceive('get_total_fees')
            ->andReturn(4);
        $order
            ->shouldReceive('get_currency')
            ->andReturn($this->currency);
        $order
            ->shouldReceive('get_shipping_total')
            ->andReturn(1);
        $order
            ->shouldReceive('get_total_tax')
            ->andReturn(2);
        $order
            ->shouldReceive('get_total_discount')
            ->andReturn(3);
        $order
            ->shouldReceive('get_meta')
            ->andReturn(null);

        $result = $this->testee->from_wc_order($order);
        $this->assertEquals((float) 3, $result->breakdown()->discount()->value());
        $this->assertEquals((float) 10, $result->breakdown()->item_total()->value());
        $this->assertEquals((float) 1, $result->breakdown()->shipping()->value());
        $this->assertEquals((float) 10, $result->value());
        $this->assertEquals((float) 2, $result->breakdown()->tax_total()->value());
        $this->assertEquals($this->currency, $result->breakdown()->discount()->currency_code());
        $this->assertEquals($this->currency, $result->breakdown()->item_total()->currency_code());
        $this->assertEquals($this->currency, $result->breakdown()->shipping()->currency_code());
        $this->assertEquals($this->currency, $result->breakdown()->tax_total()->currency_code());
        $this->assertEquals($this->currency, $result->currency_code());
    }

    public function testFromWcOrderDiscountIsNull()
    {
        $order = Mockery::mock(\WC_Order::class);
        $unitAmount = Mockery::mock(Money::class);
        $unitAmount
            ->shouldReceive('value')
            ->andReturn(3);
        $item = Mockery::mock(Item::class);
        $item
            ->shouldReceive('quantity')
            ->andReturn(2);
        $item
            ->shouldReceive('unit_amount')
            ->andReturn($unitAmount);
        $this->itemFactory
            ->expects('from_wc_order')
            ->with($order)
            ->andReturn([$item]);

		$order
			->shouldReceive('get_payment_method')
			->andReturn(PayPalGateway::ID);

        $order
            ->shouldReceive('get_total')
            ->andReturn(100);
        $order
            ->shouldReceive('get_subtotal')
            ->andReturn(6);
		$order
			->shouldReceive('get_total_fees')
			->andReturn(0);
        $order
            ->shouldReceive('get_currency')
            ->andReturn($this->currency);
        $order
            ->shouldReceive('get_shipping_total')
            ->andReturn(1);
		$order
			->shouldReceive('get_total_tax')
			->andReturn(2);
        $order
            ->shouldReceive('get_total_discount')
            ->andReturn(0);
		$order
			->shouldReceive('get_meta')
			->andReturn(null);

        $result = $this->testee->from_wc_order($order);
        $this->assertNull($result->breakdown()->discount());
    }

    /**
     * @dataProvider dataFromPayPalResponse
     * @param $response
     */
    public function testFromPayPalResponse($response, $expectsException)
    {
        if ($expectsException) {
            $this->expectException(RuntimeException::class);
        }
        $result = $this->testee->from_paypal_response($response);
        if ($expectsException) {
            return;
        }
        $this->assertEquals($response->value, $result->value());
        $this->assertEquals($response->currency_code, $result->currency_code());
        $breakdown = $result->breakdown();
        if (! isset($response->breakdown)) {
            $this->assertNull($breakdown);
            return;
        }
        if ($breakdown->shipping()) {
            $this->assertEquals($response->breakdown->shipping->value, $breakdown->shipping()->value());
            $this->assertEquals($response->breakdown->shipping->currency_code, $breakdown->shipping()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->shipping));
        }
        if ($breakdown->item_total()) {
            $this->assertEquals($response->breakdown->item_total->value, $breakdown->item_total()->value());
            $this->assertEquals($response->breakdown->item_total->currency_code, $breakdown->item_total()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->item_total));
        }
        if ($breakdown->tax_total()) {
            $this->assertEquals($response->breakdown->tax_total->value, $breakdown->tax_total()->value());
            $this->assertEquals($response->breakdown->tax_total->currency_code, $breakdown->tax_total()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->tax_total));
        }
        if ($breakdown->handling()) {
            $this->assertEquals($response->breakdown->handling->value, $breakdown->handling()->value());
            $this->assertEquals($response->breakdown->handling->currency_code, $breakdown->handling()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->handling));
        }
        if ($breakdown->insurance()) {
            $this->assertEquals($response->breakdown->insurance->value, $breakdown->insurance()->value());
            $this->assertEquals($response->breakdown->insurance->currency_code, $breakdown->insurance()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->insurance));
        }
        if ($breakdown->shipping_discount()) {
            $this->assertEquals($response->breakdown->shipping_discount->value, $breakdown->shipping_discount()->value());
            $this->assertEquals($response->breakdown->shipping_discount->currency_code, $breakdown->shipping_discount()->currency_code());
        } else {
            $this->assertTrue(! isset($response->breakdown->shipping_discount));
        }
        if ($breakdown->discount()) {
            $this->assertEquals($response->breakdown->discount->value, $breakdown->discount()->value());
            $this->assertEquals($response->breakdown->discount->currency_code, $breakdown->discount()->currency_code());
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
