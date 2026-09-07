<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\OrderEndpoints\Helper;

use Mockery;
use ReflectionMethod;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingFactory;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\Button\Session\CartDataFactory;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\expect;

class WooCommerceOrderCreatorTest extends TestCase {
	/**
	 * GIVEN a PayPal order with no purchase units (shipping = null)
	 * AND Brain\Monkey expects apply_filters called once with the filter name, null, $order, $paypal_data
	 * WHEN get_shipping() is invoked via reflection
	 * THEN the return value equals $filter_shipping (from the filter)
	 */
	public function test_filter_can_override_return_value_of_get_shipping(): void {
		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'purchase_units' )->andReturn( [] );

		$filter_shipping = Mockery::mock( Shipping::class );

		$paypal_data = [ 'key' => 'value' ];

		expect( 'apply_filters' )
			->once()
			->with(
				'woocommerce_paypal_payments_order_creator_get_shipping',
				null,
				$order,
				$paypal_data
			)
			->andReturn( $filter_shipping );

		$sut = new WooCommerceOrderCreator(
			Mockery::mock( FundingSourceRenderer::class ),
			Mockery::mock( SessionHandler::class ),
			Mockery::mock( SubscriptionHelper::class ),
			Mockery::mock( CartDataFactory::class ),
			Mockery::mock( ShippingFactory::class ),
			Mockery::mock( PayerFactory::class )
		);

		// Testing a private method, as we want to confirm the presence and ability of a WP filter.
		$method = new ReflectionMethod( WooCommerceOrderCreator::class, 'get_shipping' );
		$method->setAccessible( true );

		$result = $method->invoke( $sut, $order, $paypal_data );

		$this->assertSame( $filter_shipping, $result );
	}

	/**
	 * GIVEN a cart with one non-subscription line item
	 * WHEN configure_line_items() builds the WC order line items via reflection
	 * THEN the order line item is created through the WooCommerce Core
	 *      "woocommerce_checkout_create_order_line_item_object" filter
	 * AND the resulting item is attached to the order via set_order()
	 * AND the "woocommerce_checkout_create_order_line_item" action fires with the
	 *      item, cart item key, cart item data and the order
	 * AND the filtered item is the one added to the order
	 */
	public function test_configure_line_items_uses_core_checkout_filter_and_action_for_each_cart_item(): void {
		$cart_item_key = 'abc123';
		$cart_item     = array(
			'product_id'    => 10,
			'variation_id'  => 0,
			'quantity'      => 2,
			'variation'     => array(),
			'line_subtotal' => '20',
			'line_total'    => '20',
		);

		$cart_data = new CartData( array( $cart_item_key => $cart_item ), array(), false, 0, 'cart-hash' );

		$wc_order = Mockery::mock( WC_Order::class );

		$item = Mockery::mock( WC_Order_Item_Product::class );
		$item->shouldReceive( 'set_product_id' )->once()->with( 10 );
		$item->shouldReceive( 'set_quantity' )->once()->with( 2 );
		$item->shouldReceive( 'set_order' )->once()->with( $wc_order );
		$item->shouldReceive( 'set_name' )->once()->with( 'Test product' );
		$item->shouldReceive( 'set_subtotal' )->once()->with( '20' );
		$item->shouldReceive( 'set_total' )->once()->with( '20' );
		$item->shouldReceive( 'get_total' )->once()->andReturn( '20' );
		$item->shouldReceive( 'set_tax_class' )->once()->with( '' );
		$item->shouldReceive( 'set_total_tax' )->once()->with( '0' );

		$product = Mockery::mock( WC_Product::class );
		$product->shouldReceive( 'get_name' )->andReturn( 'Test product' );
		$product->shouldReceive( 'get_id' )->andReturn( 10 );
		$product->shouldReceive( 'get_tax_class' )->andReturn( '' );

		expect( 'wc_get_product' )->once()->with( 10 )->andReturn( $product );

		$wc_tax = Mockery::mock( 'alias:WC_Tax' );
		$wc_tax->shouldReceive( 'get_rates' )->once()->with( '' )->andReturn( array() );
		$wc_tax->shouldReceive( 'calc_tax' )->once()->with( '20', array(), true )->andReturn( array() );

		expect( 'apply_filters' )
			->once()
			->with(
				'woocommerce_checkout_create_order_line_item_object',
				Mockery::type( WC_Order_Item_Product::class ),
				$cart_item_key,
				$cart_item,
				$wc_order
			)
			->andReturn( $item );

		expect( 'apply_filters' )
			->once()
			->with(
				'woocommerce_paypal_payments_shipping_callback_cart_line_item_total',
				$cart_item['line_subtotal'],
				$cart_item
			)
			->andReturn( $cart_item['line_subtotal'] );

		expect( 'do_action' )
			->once()
			->with(
				'woocommerce_checkout_create_order_line_item',
				$item,
				$cart_item_key,
				$cart_item,
				$wc_order
			);

		$wc_order->shouldReceive( 'add_item' )->once()->with( $item );

		$subscription_helper = Mockery::mock( SubscriptionHelper::class );
		$subscription_helper->shouldReceive( 'plugin_is_active' )->once()->andReturn( false );

		$sut = new WooCommerceOrderCreator(
			Mockery::mock( FundingSourceRenderer::class ),
			Mockery::mock( SessionHandler::class ),
			$subscription_helper,
			Mockery::mock( CartDataFactory::class ),
			Mockery::mock( ShippingFactory::class ),
			Mockery::mock( PayerFactory::class )
		);

		$method = new ReflectionMethod( WooCommerceOrderCreator::class, 'configure_line_items' );
		$method->setAccessible( true );

		$method->invoke( $sut, $wc_order, $cart_data, null, null );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a PayPal order with no payer or shipping information
	 * AND an empty cart carrying a known cart hash
	 * WHEN create_from_paypal_order() builds the WC order
	 * THEN the resulting WC order's cart hash is set to the cart's hash
	 *      so the PayPal subscription replay guard can compare it later
	 */
	public function test_create_from_paypal_order_sets_cart_hash_from_cart_data(): void {
		$cart_data = new CartData( array(), array(), false, 0, 'expected-cart-hash' );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'payer' )->andReturn( null );
		$order->shouldReceive( 'purchase_units' )->andReturn( array() );

		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'set_payment_method' )->once()->with( PayPalGateway::ID );
		$wc_order->shouldReceive( 'calculate_totals' )->twice();
		$wc_order->shouldReceive( 'set_cart_hash' )->once()->with( 'expected-cart-hash' );
		$wc_order->shouldReceive( 'save' )->once();

		expect( 'wc_create_order' )->once()->andReturn( $wc_order );

		$current_user     = new \stdClass();
		$current_user->ID = 0;
		expect( 'wp_get_current_user' )->once()->andReturn( $current_user );

		$wc_customer_holder           = new \stdClass();
		$wc_customer_holder->customer = null;
		expect( 'WC' )->once()->andReturn( $wc_customer_holder );

		expect( 'apply_filters' )
			->once()
			->with(
				'woocommerce_paypal_payments_order_creator_get_shipping',
				null,
				$order,
				null
			)
			->andReturn( null );

		expect( 'do_action' )
			->once()
			->with(
				'woocommerce_paypal_payments_woocommerce_order_created_from_cart',
				$wc_order,
				$cart_data
			);

		$session_handler = Mockery::mock( SessionHandler::class );
		$session_handler->shouldReceive( 'funding_source' )->once()->andReturn( null );

		$sut = new WooCommerceOrderCreator(
			Mockery::mock( FundingSourceRenderer::class ),
			$session_handler,
			Mockery::mock( SubscriptionHelper::class ),
			Mockery::mock( CartDataFactory::class ),
			Mockery::mock( ShippingFactory::class ),
			Mockery::mock( PayerFactory::class )
		);

		$result = $sut->create_from_paypal_order( $order, $cart_data );

		$this->assertSame( $wc_order, $result );
	}
}
