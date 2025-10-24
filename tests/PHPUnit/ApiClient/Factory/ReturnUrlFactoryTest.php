<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use function Brain\Monkey\Functions\when;

class ReturnUrlFactoryTest extends TestCase
{
    private $testee;

    public function setUp(): void
    {
        parent::setUp();
        $this->testee = new ReturnUrlFactory();

		when('add_query_arg')->alias(function (array $args, string $url): string {
			$query_parts = [];
			foreach ($args as $key => $value) {
				$query_parts[] = $key . '=' . $value;
			}
			return $url .
				(strpos($url, '?') === false ? '?' : '&') .
				implode('&', $query_parts);
		});
    }

	/**
	 * @dataProvider cartContextProvider
	 */
	public function testFromContextReturnsCartUrl(string $context)
	{
		when('wc_get_cart_url')->justReturn('https://example.com/cart');

		$result = $this->testee->from_context($context);

		$this->assertEquals('https://example.com/cart', $result);
	}

    public function testFromContextProductReturnsProductUrl()
    {
        $request_data = [
            'purchase_units' => [
                [
                    'items' => [
                        [
                            'url' => 'https://example.com/product/123'
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->testee->from_context('product', $request_data);

        $this->assertEquals('https://example.com/product/123', $result);
    }

    public function testFromContextProductThrowsExceptionWhenNoUrl()
    {
        $request_data = [
            'purchase_units' => [
                [
                    'items' => [
                        [
                            'name' => 'Product without URL'
                        ]
                    ]
                ]
            ]
        ];

        $this->expectException(RuntimeException::class);

        $this->testee->from_context('product', $request_data);
    }

    public function testFromContextPayNowReturnsOrderPaymentUrl()
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->expects('get_checkout_payment_url')
            ->andReturn('https://example.com/checkout/pay/123?key=abc');

        when('wc_get_order')->justReturn($order);

        $request_data = ['order_id' => 123];

        $result = $this->testee->from_context('pay-now', $request_data);

        $this->assertEquals('https://example.com/checkout/pay/123?key=abc', $result);
    }

    public function testFromContextPayNowThrowsExceptionWhenOrderNotFound()
    {
        when('wc_get_order')->justReturn(false);

        $request_data = ['order_id' => 999];

        $this->expectException(RuntimeException::class);

        $this->testee->from_context('pay-now', $request_data);
    }

    public function testFromContextCheckoutReturnsCheckoutUrl()
    {
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $result = $this->testee->from_context('checkout');

        $this->assertEquals('https://example.com/checkout', $result);
    }

    public function testFromContextDefaultReturnsCheckoutUrl()
    {
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $result = $this->testee->from_context('unknown-context');

        $this->assertEquals('https://example.com/checkout', $result);
    }

	public function cartContextProvider(): array
	{
		return [
			'cart context' => ['cart'],
			'cart-block context' => ['cart-block'],
			'mini-cart context' => ['mini-cart'],
		];
	}
}
