<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use Mockery;
use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class ShippingPreferenceFactoryTest extends TestCase
{
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		when('wc_shipping_enabled')->justReturn(true);
		when('wc_get_shipping_method_count')->justReturn(2);

		$this->testee = new ShippingPreferenceFactory();
	}

    /**
     * @dataProvider forStateData
     */
    public function testFromState(
		PurchaseUnit $purchase_unit,
		string $context,
		?WC_Cart $cart,
		string $funding_source,
		?WC_Order $wc_order,
		string $expected_result
	) {
		$result = $this->testee->from_state($purchase_unit, $context, $cart, $funding_source, $wc_order);

		self::assertEquals($expected_result, $result);
    }

    public function forStateData()
    {
		yield [
			$this->createPurchaseUnit(true, Mockery::mock(Shipping::class)),
			'checkout',
			$this->createCart(true),
			'',
			null,
			ExperienceContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS,
		];
		yield [
			$this->createPurchaseUnit(false, Mockery::mock(Shipping::class)),
			'checkout',
			$this->createCart(false),
			'',
			null,
			ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING,
		];
		yield [
			$this->createPurchaseUnit(true, null),
			'checkout',
			$this->createCart(true),
			'',
			null,
			ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING,
		];
		yield [
			$this->createPurchaseUnit(true, Mockery::mock(Shipping::class)),
			'checkout',
			$this->createCart(true),
			'card',
			null,
			ExperienceContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS,
		];
		yield [
			$this->createPurchaseUnit(true, null),
			'product',
			null,
			'',
			null,
			ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING
		];
		yield [
			$this->createPurchaseUnit(true, null),
			'pay-now',
			null,
			'venmo',
			$this->createWcOrder(false),
			ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING
		];
		yield [
			$this->createPurchaseUnit(true, Mockery::mock(Shipping::class)),
			'pay-now',
			null,
			'venmo',
			$this->createWcOrder(true),
			ExperienceContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS
		];
		yield [
			$this->createPurchaseUnit(true, Mockery::mock(Shipping::class)),
			'pay-now',
			null,
			'card',
			$this->createWcOrder(true),
			ExperienceContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS
		];
		yield [
			$this->createPurchaseUnit(true, null),
			'pay-now',
			null,
			'card',
			$this->createWcOrder(false),
			ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING,
		];
    }

	private function createPurchaseUnit(bool $containsPhysicalGoods, ?Shipping $shipping): PurchaseUnit {
		$pu = Mockery::mock(PurchaseUnit::class);
		$pu->shouldReceive('contains_physical_goods')->andReturn($containsPhysicalGoods);
		$pu->shouldReceive('shipping')->andReturn($shipping);
		return $pu;
	}

	private function createCart(bool $needsShipping): WC_Cart {
		$cart = Mockery::mock(WC_Cart::class);
		$cart->shouldReceive('needs_shipping')->andReturn($needsShipping);
		return $cart;
	}

	private function createWcOrder(bool $needsShipping): WC_Order {
		$product = Mockery::mock(WC_Product::class);
		$product->shouldReceive('needs_shipping')->andReturn($needsShipping);

		$item = Mockery::mock(WC_Order_Item_Product::class);
		$item->shouldReceive('get_product')->andReturn($product);

		$wcOrder = Mockery::mock(WC_Order::class);
		$wcOrder->shouldReceive('get_items')->andReturn([$item]);
		return $wcOrder;
	}
}
