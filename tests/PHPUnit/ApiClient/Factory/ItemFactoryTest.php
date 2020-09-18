<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\TestCase;
use function Brain\Monkey\Functions\expect;
use Mockery;

class ItemFactoryTest extends TestCase
{

    public function testFromCartDefault()
    {
        $testee = new ItemFactory();

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
                'quantity' => 1,
            ],
        ];
        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->expects('get_cart_contents')
            ->andReturn($items);

        expect('get_woocommerce_currency')
            ->andReturn('EUR');
        expect('wc_get_price_including_tax')
            ->with($product)
            ->andReturn(2.995);
        expect('wc_get_price_excluding_tax')
            ->with($product)
            ->andReturn(1);

        $result = $testee->from_wc_cart($cart);

        $this->assertCount(1, $result);
        $item = current($result);
        $this->assertInstanceOf(Item::class, $item);
        /**
         * @var Item $item
         */
        $this->assertEquals(Item::PHYSICAL_GOODS, $item->category());
        $this->assertEquals('description', $item->description());
        $this->assertEquals(1, $item->quantity());
        $this->assertEquals('name', $item->name());
        $this->assertEquals('sku', $item->sku());
        $this->assertEquals(1, $item->unit_amount()->value());
        $this->assertEquals(2, $item->tax()->value());
    }

    public function testFromCartDigitalGood()
    {
        $testee = new ItemFactory();

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
            ],
        ];
        $cart = Mockery::mock(\WC_Cart::class);
        $cart
            ->expects('get_cart_contents')
            ->andReturn($items);

        expect('get_woocommerce_currency')
            ->andReturn('EUR');
        expect('wc_get_price_including_tax')
            ->with($product)
            ->andReturn(2.995);
        expect('wc_get_price_excluding_tax')
            ->with($product)
            ->andReturn(1);

        $result = $testee->from_wc_cart($cart);

        $item = current($result);
        $this->assertEquals(Item::DIGITAL_GOODS, $item->category());
    }

    public function testFromWcOrderDefault()
    {
        $testee = new ItemFactory();

        $product = Mockery::mock(\WC_Product::class);
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

        $item = Mockery::mock(\WC_Order_Item_Product::class);
        $item
            ->expects('get_product')
            ->andReturn($product);
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
            ->with($item, true)
            ->andReturn(3);
        $order
            ->expects('get_item_subtotal')
            ->with($item, false)
            ->andReturn(1);

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
        $this->assertEquals(2, $item->tax()->value());
    }

    public function testFromWcOrderDigitalGood()
    {
        $testee = new ItemFactory();

        $product = Mockery::mock(\WC_Product::class);
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

        $item = Mockery::mock(\WC_Order_Item_Product::class);
        $item
            ->expects('get_product')
            ->andReturn($product);
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
            ->with($item, true)
            ->andReturn(3);
        $order
            ->expects('get_item_subtotal')
            ->with($item, false)
            ->andReturn(1);

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
        $testee = new ItemFactory();

        $product = Mockery::mock(\WC_Product::class);
        $product
            ->expects('get_name')
            ->andReturn($name);
        $product
            ->expects('get_description')
            ->andReturn($description);
        $product
            ->expects('get_sku')
            ->andReturn('sku');
        $product
            ->expects('is_virtual')
            ->andReturn(true);

        $item = Mockery::mock(\WC_Order_Item_Product::class);
        $item
            ->expects('get_product')
            ->andReturn($product);
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
            ->with($item, true)
            ->andReturn(3);
        $order
            ->expects('get_item_subtotal')
            ->with($item, false)
            ->andReturn(1);

        $result = $testee->from_wc_order($order);
        $item = current($result);
        /**
         * @var Item $item
         */
        $this->assertEquals(mb_substr($name, 0, 127), $item->name());
        $this->assertEquals(mb_substr($description, 0, 127), $item->description());
    }

    public function testFromPayPalResponse()
    {
        $testee = new ItemFactory();

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
        $testee = new ItemFactory();

        $response = (object) [
            'name' => 'name',
            'description' => 'description',
            'quantity' => 1,
            'unit_amount' => (object) [
                'value' => 1,
                'currency_code' => 'EUR',
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
        $testee = new ItemFactory();

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
        $testee = new ItemFactory();

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
        $testee = new ItemFactory();

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
        $testee = new ItemFactory();

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
        $testee = new ItemFactory();

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
