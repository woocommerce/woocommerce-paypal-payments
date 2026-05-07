<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use Mockery;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PatchCollection;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Schema\CartItem;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreCartItem;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreData;
use WooCommerce\PayPalCommerce\StoreSync\StoreSyncTestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager
 */
class PayPalOrderManagerTest extends StoreSyncTestCase {

	private function make_sut(
		?StoreCurrencyValue $store_currency = null,
		?StoreData $store_data = null
	): PayPalOrderManager {
		$store_currency ??= Mockery::mock( StoreCurrencyValue::class );
		$store_currency->allows( 'value' )->andReturn( 'USD' );

		$store_data ??= Mockery::mock( StoreData::class );

		return new PayPalOrderManager(
			Mockery::mock( OrderEndpoint::class ),
			Mockery::mock( Orders::class ),
			Mockery::mock( AgenticCartBuilder::class ),
			Mockery::mock( LoggerInterface::class ),
			$store_currency,
			$store_data
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

		$wc_product = Mockery::mock( 'WC_Product' );
		$wc_product->allows( 'get_price' )->andReturn( '10.00' );

		$store_currency = Mockery::mock( StoreCurrencyValue::class );
		$store_currency->allows( 'value' )->andReturn( 'USD' );

		$store_item = new StoreCartItem( $item1, $wc_product, $store_currency );

		$store_data = Mockery::mock( StoreData::class );
		$store_data->allows( 'cart_item' )->andReturn( $store_item );

		$method = new ReflectionMethod( PayPalOrderManager::class, 'build_items_for_patch' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->make_sut( $store_currency, $store_data ), $cart );

		$this->assertCount( 2, $result );
	}

	/**
	 * GIVEN a cart with no items (to isolate from the items-patch behaviour)
	 * AND paypal_cart_to_wc_cart() returns a WC_Cart with subtotal=22, shipping=10, tax=2.56, total=34.56
	 * WHEN update_order() is called
	 * THEN the amount PATCH received by order_endpoint has currency_code='USD', value='34.56',
	 *      and breakdown with item_total='22.00', shipping='10.00', tax_total='2.56'
	 */
	public function test_update_order_sends_correct_amount_patch(): void {
		$captured = null;
		$order_endpoint = Mockery::mock( OrderEndpoint::class );
		$order_endpoint->allows( 'patch' )
			->andReturnUsing(
				static function ( string $id, PatchCollection $patches ) use ( &$captured ): void {
					$captured = $patches;
				}
			);

		$wc_cart = Mockery::mock( 'WC_Cart' );
		$wc_cart->allows( 'get_cart_contents_total' )->andReturn( 22.0 );
		$wc_cart->allows( 'get_discount_total' )->andReturn( 0.0 );
		$wc_cart->allows( 'get_shipping_total' )->andReturn( 10.0 );
		$wc_cart->allows( 'get_total_tax' )->andReturn( 2.56 );
		$wc_cart->allows( 'get_total' )->andReturn( 34.56 );

		$cart_builder = Mockery::mock( AgenticCartBuilder::class );
		$cart_builder->allows( 'paypal_cart_to_wc_cart' )->andReturn( $wc_cart );

		$cart = Mockery::mock( PayPalCart::class );
		$cart->allows( 'items' )->andReturn( array() );

		$store_currency = Mockery::mock( StoreCurrencyValue::class );
		$store_currency->allows( 'value' )->andReturn( 'USD' );

		$logger = Mockery::mock( LoggerInterface::class );
		$logger->allows( 'info' );
		$logger->allows( 'warning' );

		$store_data = Mockery::mock( StoreData::class );

		$sut = new PayPalOrderManager(
			$order_endpoint,
			Mockery::mock( Orders::class ),
			$cart_builder,
			$logger,
			$store_currency,
			$store_data
		);

		when( 'is_wp_error' )->justReturn( false );

		$sut->update_order( 'ORDER-123', $cart );

		$this->assertNotNull( $captured, 'order_endpoint::patch() was never called' );

		$amount_patch = null;
		foreach ( $captured->patches() as $patch ) {
			if ( strpos( $patch->path(), '/amount' ) !== false ) {
				$amount_patch = $patch;
				break;
			}
		}

		$this->assertNotNull( $amount_patch, 'No amount patch found in PatchCollection' );
		$value = $amount_patch->value();
		$this->assertMoneyValue( $value, 34.56, 'USD' );
		$this->assertMoneyValue( $value['breakdown']['item_total'], 22.0 );
		$this->assertMoneyValue( $value['breakdown']['shipping'], 10.0 );
		$this->assertMoneyValue( $value['breakdown']['tax_total'], 2.56 );
	}
}
