<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Helper\CurrencyGetterStub;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use Mockery;

class ItemFactoryTest extends TestCase
{
	private $currency;

	public function setUp(): void
	{
		parent::setUp();
		
		$this->currency = new CurrencyGetterStub();
	}

	public function testFromCartDefault()
    {
        $testee = new ItemFactory($this->currency);

        $product = Mockery::mock(\WC_Product_Simple::class);
        $product
            ->expects('get_name')
            ->andReturn('name');
        $product
            ->expects('get_description')
            ->andReturn('description');
        $product
            ->expects('get_sku')
            ->andReturn('sku');
        $product
            ->expects('is_virtual')
            ->andReturn(false);
        $items = [
            [
                'data' => $product,
                'quantity' => 2,
				'line_subtotal' => 84,
            ],
        ];
        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->expects('get_cart_contents')
            ->andReturn($items);

	    expect('wp_strip_all_tags')->andReturnFirstArg();
	    expect('strip_shortcodes')->andReturnFirstArg();

        $woocommerce = Mockery::mock(\WooCommerce::class);
        $session = Mockery::mock(\WC_Session::class);
        when('WC')->justReturn($woocommerce);
        $woocommerce->session = $session;
        $session->shouldReceive('get')->andReturn([]);

		when('wp_get_attachment_image_src')->justReturn('image_url');
		$product
			->expects('get_image_id')
			->andReturn(1);
		$product
			->expects('get_permalink')
			->andReturn('url');

        $result = $testee->from_wc_cart($cart);

        $this->assertCount(1, $result);
        $item = current($result);
        $this->assertInstanceOf(Item::class, $item);
        /**
         * @var Item $item
         */
        $this->assertEquals(Item::PHYSICAL_GOODS, $item->category());
        $this->assertEquals('description', $item->description());
        $this->assertEquals(2, $item->quantity());
        $this->assertEquals('name', $item->name());
        $this->assertEquals('sku', $item->sku());
        $this->assertEquals(42, $item->unit_amount()->value());
    }

    public function testFromCartDigitalGood()
    {
        $testee = new ItemFactory($this->currency);

        $product = Mockery::mock(\WC_Product_Simple::class);
        $product
            ->expects('get_name')
            ->andReturn('name');
        $product
            ->expects('get_description')
            ->andReturn('description');
        $product
            ->expects('get_sku')
            ->andReturn('sku');
        $product
            ->expects('is_virtual')
            ->andReturn(true);

        $items = [
            [
                'data' => $product,
                'quantity' => 1,
				'line_subtotal' => 42,
            ],
        ];
        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->expects('get_cart_contents')
            ->andReturn($items);

		expect('wp_strip_all_tags')->andReturnFirstArg();
		expect('strip_shortcodes')->andReturnFirstArg();

        $woocommerce = Mockery::mock(\WooCommerce::class);
        $session = Mockery::mock(\WC_Session::class);
        when('WC')->justReturn($woocommerce);
        $woocommerce->session = $session;
        $session->shouldReceive('get')->andReturn([]);

		when('wp_get_attachment_image_src')->justReturn('image_url');
		$product
			->expects('get_image_id')
			->andReturn(1);
		$product
			->expects('get_permalink')
			->andReturn('url');

        $result = $testee->from_wc_cart($cart);

        $item = current($result);
        $this->assertEquals(Item::DIGITAL_GOODS, $item->category());
    }

    public function testFromWcOrderDefault()
    {
        $testee = new ItemFactory($this->currency);

        $product = Mockery::mock(\WC_Product::class);
        $product
            ->expects('get_description')
            ->andReturn('description');
        $product
            ->expects('get_sku')
            ->andReturn('sku');
        $product
            ->expects('is_virtual')
            ->andReturn(false);

		expect('wp_strip_all_tags')->andReturnFirstArg();
		expect('strip_shortcodes')->andReturnFirstArg();

		when('wp_get_attachment_image_src')->justReturn('image_url');
		$product
			->expects('get_image_id')
			->andReturn(1);
		$product
			->expects('get_permalink')
			->andReturn('url');

        $item = Mockery::mock(\WC_Order_Item_Product::class);
        $item
            ->expects('get_product')
            ->andReturn($product);
		$item
			->expects('get_name')
			->andReturn('name');
        $item
            ->expects('get_quantity')
            ->andReturn(1);

        $order = Mockery::mock(\WC_Order::class);
        $order
            ->expects('get_currency')
            ->andReturn($this->currency->get());
        $order
            ->expects('get_items')
            ->andReturn([$item]);
        $order
            ->expects('get_item_subtotal')
            ->with($item, false)
            ->andReturn(1);
        $order
            ->expects('get_fees')
            ->andReturn([]);


        $result = $testee->from_wc_order($order);
        $this->assertCount(1, $result);
        $item = current($result);
        /**
         * @var Item $item
         */
        $this->assertInstanceOf(Item::class, $item);
        $this->assertEquals('name', $item->name());
        $this->assertEquals('description', $item->description());
        $this->assertEquals(1, $item->quantity());
        $this->assertEquals(Item::PHYSICAL_GOODS, $item->category());
        $this->assertEquals(1, $item->unit_amount()->value());
    }

    public function testFromWcOrderDigitalGood()
    {
        $testee = new ItemFactory($this->currency);

        $product = Mockery::mock(\WC_Product::class);
        $product
            ->expects('get_description')
            ->andReturn('description');
        $product
            ->expects('get_sku')
            ->andReturn('sku');
        $product
            ->expects('is_virtual')
            ->andReturn(true);

		expect('wp_strip_all_tags')->andReturnFirstArg();
		expect('strip_shortcodes')->andReturnFirstArg();

        $item = Mockery::mock(\WC_Order_Item_Product::class);
        $item
            ->expects('get_product')
            ->andReturn($product);
		$item
			->expects('get_name')
			->andReturn('name');
        $item
            ->expects('get_quantity')
            ->andReturn(1);

        $order = Mockery::mock(\WC_Order::class);
        $order
            ->expects('get_currency')
            ->andReturn($this->currency->get());
        $order
            ->expects('get_items')
            ->andReturn([$item]);
        $order
            ->expects('get_item_subtotal')
            ->with($item, false)
            ->andReturn(1);
        $order
            ->expects('get_fees')
            ->andReturn([]);

		when('wp_get_attachment_image_src')->justReturn('image_url');
		$product
			->expects('get_image_id')
			->andReturn(1);
		$product
			->expects('get_permalink')
			->andReturn('url');

        $result = $testee->from_wc_order($order);
        $item = current($result);
        /**
         * @var Item $item
         */
        $this->assertEquals(Item::DIGITAL_GOODS, $item->category());
    }

    public function testFromWcOrderMaxStringLength()
    {
        $name = 'öawjetöagrjjaglörjötairgjaflkögjöalfdgjöalfdjblköajtlkfjdbljslkgjfklösdgjalkerjtlrajglkfdajblköajflköbjsdgjadfgjaöfgjaölkgjkladjgfköajgjaflgöjafdlgjafdögjdsflkgjö4jwegjfsdbvxj öskögjtaeröjtrgt';
        $description = 'öawjetöagrjjaglörjötairgjaflkögjöalfdgjöalfdjblköajtlkfjdbljslkgjfklösdgjalkerjtlrajglkfdajblköajflköbjsdgjadfgjaöfgjaölkgjkladjgfköajgjaflgöjafdlgjafdögjdsflkgjö4jwegjfsdbvxj öskögjtaeröjtrgt';
        $testee = new ItemFactory($this->currency);

        $product = Mockery::mock(\WC_Product::class);
        $product
            ->expects('get_description')
            ->andReturn($description);
        $product
            ->expects('get_sku')
            ->andReturn('sku');
        $product
            ->expects('is_virtual')
            ->andReturn(true);

		expect('wp_strip_all_tags')->andReturnFirstArg();
		expect('strip_shortcodes')->andReturnFirstArg();

        $item = Mockery::mock(\WC_Order_Item_Product::class);
        $item
            ->expects('get_product')
            ->andReturn($product);
		$item
			->expects('get_name')
			->andReturn($name);
        $item
            ->expects('get_quantity')
            ->andReturn(1);

        $order = Mockery::mock(\WC_Order::class);
        $order
            ->expects('get_currency')
            ->andReturn('EUR');
        $order
            ->expects('get_items')
            ->andReturn([$item]);
        $order
            ->expects('get_item_subtotal')
            ->with($item, false)
            ->andReturn(1);
        $order
            ->expects('get_fees')
            ->andReturn([]);

		when('wp_get_attachment_image_src')->justReturn('image_url');
		$product
			->expects('get_image_id')
			->andReturn(1);
		$product
			->expects('get_permalink')
			->andReturn('url');

        $result = $testee->from_wc_order($order);
        $item = current($result);

        /**
         * @var Item $item
         */
        $this->assertEquals(substr( strip_shortcodes( wp_strip_all_tags( $name ) ), 0, 127 ), $item->name());
        $this->assertEquals(substr( strip_shortcodes( wp_strip_all_tags( $description ) ), 0, 127 ), $item->description());
    }

    public function testFromPayPalResponse()
    {
        $testee = new ItemFactory($this->currency);

        $response = (object) [
            'name' => 'name',
            'description' => 'description',
            'quantity' => 1,
            'unit_amount' => (object) [
                'value' => 1,
                'currency_code' => 'EUR',
            ],
        ];
        $item = $testee->from_paypal_response($response);
        $this->assertInstanceOf(Item::class, $item);
        /**
         * @var Item $item
         */
        $this->assertInstanceOf(Item::class, $item);
        $this->assertEquals('name', $item->name());
        $this->assertEquals('description', $item->description());
        $this->assertEquals(1, $item->quantity());
        $this->assertEquals(Item::PHYSICAL_GOODS, $item->category());
        $this->assertEquals(1, $item->unit_amount()->value());
        $this->assertNull($item->tax());
    }

    public function testFromPayPalResponseDigitalGood()
    {
        $testee = new ItemFactory($this->currency);

        $response = (object) [
            'name' => 'name',
            'description' => 'description',
            'quantity' => 1,
            'unit_amount' => (object) [
                'value' => 1,
                'currency_code' => $this->currency->get(),
            ],
            'category' => Item::DIGITAL_GOODS,
        ];
        $item = $testee->from_paypal_response($response);
        /**
         * @var Item $item
         */
        $this->assertEquals(Item::DIGITAL_GOODS, $item->category());
    }

    public function testFromPayPalResponseHasTax()
    {
        $testee = new ItemFactory($this->currency);

        $response = (object) [
            'name' => 'name',
            'description' => 'description',
            'quantity' => 1,
            'unit_amount' => (object) [
                'value' => 1,
                'currency_code' => 'EUR',
            ],
            'tax' => (object) [
                'value' => 100,
                'currency_code' => 'EUR',
            ],
        ];
        $item = $testee->from_paypal_response($response);
        $this->assertEquals(100, $item->tax()->value());
    }

    public function testFromPayPalResponseThrowsWithoutName()
    {
        $testee = new ItemFactory($this->currency);

        $response = (object) [
            'description' => 'description',
            'quantity' => 1,
            'unit_amount' => (object) [
                'value' => 1,
                'currency_code' => 'EUR',
            ],
            'tax' => (object) [
                'value' => 100,
                'currency_code' => 'EUR',
            ],
        ];
        $this->expectException(RuntimeException::class);
        $testee->from_paypal_response($response);
    }

    public function testFromPayPalResponseThrowsWithoutQuantity()
    {
        $testee = new ItemFactory($this->currency);

        $response = (object) [
            'name' => 'name',
            'description' => 'description',
            'unit_amount' => (object) [
                'value' => 1,
                'currency_code' => 'EUR',
            ],
            'tax' => (object) [
                'value' => 100,
                'currency_code' => 'EUR',
            ],
        ];
        $this->expectException(RuntimeException::class);
        $testee->from_paypal_response($response);
    }

    public function testFromPayPalResponseThrowsWithStringInQuantity()
    {
        $testee = new ItemFactory($this->currency);

        $response = (object) [
            'name' => 'name',
            'description' => 'description',
            'quantity' => 'should-not-be-a-string',
            'unit_amount' => (object) [
                'value' => 1,
                'currency_code' => 'EUR',
            ],
            'tax' => (object) [
                'value' => 100,
                'currency_code' => 'EUR',
            ],
        ];
        $this->expectException(RuntimeException::class);
        $testee->from_paypal_response($response);
    }

    public function testFromPayPalResponseThrowsWithWrongUnitAmount()
    {
        $testee = new ItemFactory($this->currency);

        $response = (object) [
            'name' => 'name',
            'description' => 'description',
            'quantity' => 1,
            'unit_amount' => (object) [
            ],
            'tax' => (object) [
                'value' => 100,
                'currency_code' => 'EUR',
            ],
        ];
        $this->expectException(RuntimeException::class);
        $testee->from_paypal_response($response);
    }
}
