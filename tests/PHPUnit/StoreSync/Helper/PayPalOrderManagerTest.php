<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use Mockery;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager
 */
class PayPalOrderManagerTest extends TestCase {

	private function make_sut( ?StoreCurrencyValue $store_currency = null ): PayPalOrderManager {
		$store_currency ??= Mockery::mock( StoreCurrencyValue::class );
		$store_currency->allows( 'value' )->andReturn( 'USD' );

		return new PayPalOrderManager(
			Mockery::mock( OrderEndpoint::class ),
			Mockery::mock( Orders::class ),
			Mockery::mock( AgenticCartBuilder::class ),
			Mockery::mock( LoggerInterface::class ),
			$store_currency
		);
	}

	/**
	 * GIVEN a PayPalCart with 2 items that have no price data
	 * WHEN build_items_for_patch() is called via ReflectionMethod
	 * THEN the result contains 2 items — one for each cart item
	 */
	public function test_items_without_price_are_included_in_patch_items(): void {
		$item1 = CartItem::from_array( array( 'item_id' => '1', 'quantity' => 1, 'name' => 'Widget A' ) );
		$item2 = CartItem::from_array( array( 'item_id' => '2', 'quantity' => 2, 'name' => 'Widget B' ) );

		$cart = Mockery::mock( PayPalCart::class );
		$cart->allows( 'items' )->andReturn( array( $item1, $item2 ) );

		$method = new ReflectionMethod( PayPalOrderManager::class, 'build_items_for_patch' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->make_sut(), $cart );

		$this->assertCount( 2, $result );
	}
}
