<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use Mockery;
use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Address;
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
     * GIVEN a purchase unit, cart/order state, context and funding source
     * WHEN the shipping preference for the PayPal order is derived
     * THEN the matching ExperienceContext shipping preference is returned
     *
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
			$this->createPurchaseUnit(true, $this->createShippingWithAddress()),
			'checkout',
			$this->createCart(true),
			'',
			null,
			ExperienceContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS,
		];
		yield [
			$this->createPurchaseUnit(false, $this->createShippingWithAddress()),
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
			$this->createPurchaseUnit(true, $this->createShippingWithAddress()),
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
			$this->createPurchaseUnit(true, $this->createShippingWithAddress()),
			'pay-now',
			null,
			'venmo',
			$this->createWcOrder(true),
			ExperienceContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS
		];
		yield [
			$this->createPurchaseUnit(true, $this->createShippingWithAddress()),
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

		yield 'checkout with options-only shipping node falls back to no shipping' => [
			$this->createPurchaseUnit(true, $this->createShippingWithoutAddress()),
			'checkout',
			$this->createCart(true),
			'',
			null,
			ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING,
		];
		yield 'pay-now with options-only shipping node falls back to no shipping' => [
			$this->createPurchaseUnit(true, $this->createShippingWithoutAddress()),
			'pay-now',
			null,
			'venmo',
			$this->createWcOrder(true),
			ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING,
		];
		yield 'card funding source with options-only shipping node falls back to no shipping' => [
			$this->createPurchaseUnit(true, $this->createShippingWithoutAddress()),
			'product',
			$this->createCart(true),
			'card',
			null,
			ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING,
		];
		yield 'product context returns get-from-file regardless of shipping address presence' => [
			$this->createPurchaseUnit(true, $this->createShippingWithoutAddress()),
			'product',
			$this->createCart(true),
			'',
			null,
			ExperienceContext::SHIPPING_PREFERENCE_GET_FROM_FILE,
		];
    }

	private function createPurchaseUnit(bool $containsPhysicalGoods, ?Shipping $shipping): PurchaseUnit {
		$pu = Mockery::mock(PurchaseUnit::class);
		$pu->shouldReceive('contains_physical_goods')->andReturn($containsPhysicalGoods);
		$pu->shouldReceive('shipping')->andReturn($shipping);
		return $pu;
	}

	private function createShippingWithAddress(): Shipping {
		$shipping = Mockery::mock(Shipping::class);
		$shipping->shouldReceive('address')->andReturn(Mockery::mock(Address::class));
		return $shipping;
	}

	private function createShippingWithoutAddress(): Shipping {
		$shipping = Mockery::mock(Shipping::class);
		$shipping->shouldReceive('address')->andReturn(null);
		return $shipping;
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
