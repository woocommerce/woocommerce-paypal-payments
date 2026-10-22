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

    /**
     * GIVEN a product page request whose first item carries no URL
     * AND the cart holds the viewed product
     * WHEN the return URL is resolved
     * THEN the permalink of the cart product is returned
     */
    public function testFromContextProductFallsBackToCartProductWhenItemHasNoUrl()
    {
        $this->given_cart_contents([$this->product_with_permalink('https://example.com/product/cart-item')]);

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

        $result = $this->testee->from_context('product', $request_data);

        $this->assertSame('https://example.com/product/cart-item', $result);
    }

    /**
     * GIVEN a product page request whose items were discarded (e.g. due to a negative fee)
     * AND the cart holds the viewed product
     * WHEN the return URL is resolved
     * THEN the permalink of the cart product is returned
     */
    public function testFromContextProductFallsBackToCartProductWhenItemsAreMissing()
    {
        $this->given_cart_contents([$this->product_with_permalink('https://example.com/product/cart-item')]);

        $request_data = [
            'purchase_units' => [
                [
                    'amount' => ['value' => '10.00']
                ]
            ]
        ];

        $result = $this->testee->from_context('product', $request_data);

        $this->assertSame('https://example.com/product/cart-item', $result);
    }

    /**
     * GIVEN a product page request without purchase units
     * AND an empty cart
     * WHEN the return URL is resolved
     * THEN the buyer is sent back to the cart page
     */
    public function testFromContextProductFallsBackToCartUrlWhenCartIsEmpty()
    {
        when('wc_get_cart_url')->justReturn('https://example.com/cart');
        $this->given_cart_contents([]);

        $result = $this->testee->from_context('product', []);

        $this->assertSame('https://example.com/cart', $result);
    }

    /**
     * GIVEN a product page request without purchase units
     * AND no cart initialised in WooCommerce
     * WHEN the return URL is resolved
     * THEN the buyer is sent back to the cart page
     */
    public function testFromContextProductFallsBackToCartUrlWhenCartIsNotInitialised()
    {
        when('wc_get_cart_url')->justReturn('https://example.com/cart');
        when('WC')->justReturn((object) ['cart' => null]);

        $result = $this->testee->from_context('product', []);

        $this->assertSame('https://example.com/cart', $result);
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

	private function product_with_permalink(string $permalink): \WC_Product
	{
		$product = Mockery::mock(\WC_Product::class);
		$product->shouldReceive('get_permalink')->andReturn($permalink);

		return $product;
	}

	/**
	 * @param \WC_Product[] $products
	 */
	private function given_cart_contents(array $products): void
	{
		$items = [];
		foreach ($products as $index => $product) {
			$items['key' . $index] = ['data' => $product];
		}

		$cart = $this->createStub(\WC_Cart::class);
		$cart->method('get_cart')->willReturn($items);

		when('WC')->justReturn((object) ['cart' => $cart]);
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
