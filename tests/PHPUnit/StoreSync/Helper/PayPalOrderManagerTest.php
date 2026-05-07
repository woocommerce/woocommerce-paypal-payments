<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use Mockery;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use WC_Cart;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AmountBreakdown;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PatchCollection;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\StoreSyncTestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager
 */
class PayPalOrderManagerTest extends StoreSyncTestCase {

	private function make_sut(
		?StoreCurrencyValue $store_currency = null,
		?AmountFactory $amount_factory = null
	): PayPalOrderManager {
		$store_currency ??= Mockery::mock( StoreCurrencyValue::class );
		$store_currency->allows( 'value' )->andReturn( 'USD' );

		$amount_factory ??= Mockery::mock( AmountFactory::class );

		return new PayPalOrderManager(
			Mockery::mock( OrderEndpoint::class ),
			Mockery::mock( Orders::class ),
			Mockery::mock( AgenticCartBuilder::class ),
			Mockery::mock( LoggerInterface::class ),
			$store_currency,
			$amount_factory
		);
	}

	/**
	 * GIVEN a WC_Cart with 2 line items
	 * WHEN build_items_for_patch() is called via ReflectionMethod
	 * THEN the result contains 2 items — one for each cart line item
	 */
	public function test_items_are_built_from_wc_cart(): void {
		$product1 = Mockery::mock( 'WC_Product' );
		$product1->allows( 'get_name' )->andReturn( 'Widget A' );

		$product2 = Mockery::mock( 'WC_Product' );
		$product2->allows( 'get_name' )->andReturn( 'Widget B' );

		$cart_item1 = array( 'data' => $product1, 'quantity' => 1, 'line_subtotal' => 10.0 );
		$cart_item2 = array( 'data' => $product2, 'quantity' => 2, 'line_subtotal' => 20.0 );

		$wc_cart = Mockery::mock( WC_Cart::class );
		$wc_cart->allows( 'get_cart' )->andReturn( array( $cart_item1, $cart_item2 ) );

		$method = new ReflectionMethod( PayPalOrderManager::class, 'build_items_for_patch' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->make_sut(), $wc_cart );

		$this->assertCount( 2, $result );
	}

	/**
	 * GIVEN a cart with no items (to isolate from the items-patch behaviour)
	 * AND AmountFactory returns an Amount with total=34.56, item_total=22.00, shipping=10.00, tax=2.56
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

		$wc_cart = Mockery::mock( WC_Cart::class );
		$wc_cart->allows( 'get_cart' )->andReturn( array() );

		$cart_builder = Mockery::mock( AgenticCartBuilder::class );
		$cart_builder->allows( 'paypal_cart_to_wc_cart' )->andReturn( $wc_cart );

		$amount = new Amount(
			new Money( 34.56, 'USD' ),
			new AmountBreakdown(
				new Money( 22.0, 'USD' ),
				new Money( 10.0, 'USD' ),
				new Money( 2.56, 'USD' )
			)
		);

		$amount_factory = Mockery::mock( AmountFactory::class );
		$amount_factory->allows( 'from_wc_cart' )->andReturn( $amount );

		$cart = Mockery::mock( PayPalCart::class );
		$cart->allows( 'items' )->andReturn( array() );

		$store_currency = Mockery::mock( StoreCurrencyValue::class );
		$store_currency->allows( 'value' )->andReturn( 'USD' );

		$logger = Mockery::mock( LoggerInterface::class );
		$logger->allows( 'info' );
		$logger->allows( 'warning' );

		$sut = new PayPalOrderManager(
			$order_endpoint,
			Mockery::mock( Orders::class ),
			$cart_builder,
			$logger,
			$store_currency,
			$amount_factory
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
