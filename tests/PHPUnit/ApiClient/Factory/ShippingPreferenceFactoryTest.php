<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use Mockery;
use WC_Cart;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\TestCase;

class ShippingPreferenceFactoryTest extends TestCase
{
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

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
		string $expected_result
	) {
		$result = $this->testee->from_state($purchase_unit, $context, $cart, $funding_source);

		self::assertEquals($expected_result, $result);
    }

    public function forStateData()
    {
		yield [
			$this->createPurchaseUnit(true, Mockery::mock(Shipping::class)),
			'checkout',
			$this->createCart(true),
			'',
			ApplicationContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS,
		];
		yield [
			$this->createPurchaseUnit(false, Mockery::mock(Shipping::class)),
			'checkout',
			$this->createCart(false),
			'',
			ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING,
		];
		yield [
			$this->createPurchaseUnit(true, null),
			'checkout',
			$this->createCart(true),
			'',
			ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING,
		];
		yield [
			$this->createPurchaseUnit(true, Mockery::mock(Shipping::class)),
			'checkout',
			$this->createCart(true),
			'card',
			ApplicationContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS,
		];
		yield [
			$this->createPurchaseUnit(true, null),
			'product',
			null,
			'',
			ApplicationContext::SHIPPING_PREFERENCE_GET_FROM_FILE,
		];
		yield [
			$this->createPurchaseUnit(true, null),
			'pay-now',
			null,
			'venmo',
			ApplicationContext::SHIPPING_PREFERENCE_GET_FROM_FILE,
		];
		yield [
			$this->createPurchaseUnit(true, Mockery::mock(Shipping::class)),
			'pay-now',
			null,
			'venmo',
			ApplicationContext::SHIPPING_PREFERENCE_GET_FROM_FILE,
		];
		yield [
			$this->createPurchaseUnit(true, Mockery::mock(Shipping::class)),
			'pay-now',
			null,
			'card',
			ApplicationContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS,
		];
		yield [
			$this->createPurchaseUnit(true, null),
			'pay-now',
			null,
			'card',
			ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING,
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
}
