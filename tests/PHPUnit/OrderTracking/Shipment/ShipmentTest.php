<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking\Shipment;

use Mockery;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WooCommerce\PayPalCommerce\OrderTracking\OrderTrackingModule;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class ShipmentTest extends TestCase
{
	public function setUp(): void
	{
		parent::setUp();

		when('wp_get_attachment_image_src')->justReturn(false);
		when('strip_shortcodes')->returnArg();
		when('wp_strip_all_tags')->returnArg();
	}

	private function shipment( string $tracking_number = '1Z0Y19R40396776179' ): Shipment {
		return new Shipment(
			1,
			'capture-1',
			$tracking_number,
			'SHIPPED',
			'OTHER',
			'',
			array()
		);
	}

	public function test_line_items_does_not_warn_when_tracking_meta_is_not_an_array(): void
	{
		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->shouldReceive('get_items')->andReturn(array());
		$wc_order->shouldReceive('get_meta')
			->with(OrderTrackingModule::PPCP_TRACKING_INFO_META_NAME)
			->andReturn('');

		when('wc_get_order')->justReturn($wc_order);

		$this->assertSame(array(), $this->shipment()->line_items());
	}

	public function test_line_items_filters_by_saved_line_items_from_array_tracking_meta(): void
	{
		$tracking_number = '1Z0Y19R40396776179';

		$product = Mockery::mock(WC_Product::class);
		$product->shouldReceive('get_image_id')->andReturn(0);
		$product->shouldReceive('get_description')->andReturn('');
		$product->shouldReceive('get_sku')->andReturn('SKU-1');
		$product->shouldReceive('is_virtual')->andReturn(false);
		$product->shouldReceive('get_permalink')->andReturn('https://example.com/product-1');

		$matching_item = Mockery::mock(WC_Order_Item_Product::class);
		$matching_item->shouldReceive('get_id')->andReturn(10);
		$matching_item->shouldReceive('get_name')->andReturn('Matching product');
		$matching_item->shouldReceive('get_quantity')->andReturn(1);
		$matching_item->shouldReceive('get_product')->andReturn($product);

		$other_item = Mockery::mock(WC_Order_Item_Product::class);
		$other_item->shouldReceive('get_id')->andReturn(20);

		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->shouldReceive('get_items')->andReturn(array($matching_item, $other_item));
		$wc_order->shouldReceive('get_meta')
			->with(OrderTrackingModule::PPCP_TRACKING_INFO_META_NAME)
			->andReturn(array($tracking_number => array(10)));
		$wc_order->shouldReceive('get_currency')->andReturn('USD');
		$wc_order->shouldReceive('get_item_subtotal')->with($matching_item, false)->andReturn(12.5);

		when('wc_get_order')->justReturn($wc_order);

		$line_items = $this->shipment($tracking_number)->line_items();

		$this->assertSame(array(10), array_keys($line_items));
	}
}
