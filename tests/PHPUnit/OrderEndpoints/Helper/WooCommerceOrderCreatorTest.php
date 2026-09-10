<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\OrderEndpoints\Helper;

use Mockery;
use ReflectionMethod;
use WC_Order;
use WC_Order_Item_Fee;
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

		$sut = $this->create_order_creator();

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

		$sut = $this->create_order_creator( $subscription_helper );

		$method = new ReflectionMethod( WooCommerceOrderCreator::class, 'configure_line_items' );
		$method->setAccessible( true );

		$method->invoke( $sut, $wc_order, $cart_data, null, null );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a cart with a single fee, e.g. a negative "discount" fee some plugins add
	 *       via WC()->cart->add_fee()
	 * WHEN configure_fees() copies the cart fees onto the WC order via reflection
	 * THEN a WC_Order_Item_Fee carrying the fee's name, amount and total is added to the order
	 * AND the "woocommerce_checkout_create_order_fee_item" action fires with the item,
	 *      the fee key, the fee as an object (not the array), and the order
	 *
	 * This is the regression case for the reported bug: before the fix, cart fees were
	 * dropped entirely and the order total silently diverged from what the buyer approved
	 * in the wallet sheet.
	 */
	public function test_configure_fees_adds_order_item_for_a_cart_fee(): void {
		$fee_key = 'negative12Fee';
		$fee     = array(
			'id'        => $fee_key,
			'name'      => 'Loyalty discount',
			'taxable'   => false,
			'tax_class' => '',
			'amount'    => '-12',
			'total'     => '-12',
			'tax'       => 0,
			'tax_data'  => array(),
		);

		$cart_data = new CartData( array(), array(), false, 0, 'cart-hash', array( $fee_key => $fee ) );

		$wc_order = Mockery::mock( WC_Order::class );

		$added_item = null;
		$wc_order->shouldReceive( 'add_item' )
			->once()
			->with( Mockery::type( WC_Order_Item_Fee::class ) )
			->andReturnUsing(
				function ( $item ) use ( &$added_item ): void {
					$added_item = $item;
				}
			);

		expect( 'do_action' )
			->once()
			->with(
				'woocommerce_checkout_create_order_fee_item',
				Mockery::type( WC_Order_Item_Fee::class ),
				$fee_key,
				Mockery::on(
					function ( $actual ) use ( $fee ) {
						return is_object( $actual )
							&& $actual->name === $fee['name']
							&& $actual->amount === $fee['amount'];
					}
				),
				$wc_order
			);

		$this->invoke_configure_fees( $this->create_order_creator(), $wc_order, $cart_data );

		$this->assertNotNull( $added_item );
		$this->assertSame( 'Loyalty discount', $added_item->get_name() );
		$this->assertSame( '-12', $added_item->get_amount() );
		$this->assertSame( '-12', $added_item->get_total() );
	}

	/**
	 * GIVEN a cart fee that is or is not taxable
	 * WHEN configure_fees() builds the order fee item via reflection
	 * THEN the item's tax status and tax class mirror the cart fee
	 *
	 * Untaxed fees must say so explicitly: totals are recalculated once the order is
	 * assembled, so a fee item would otherwise default to taxable and pick up tax the
	 * cart never charged.
	 *
	 * @dataProvider fee_taxability_provider
	 */
	public function test_configure_fees_sets_tax_status_and_class_from_cart_fee(
		bool $taxable,
		string $tax_class,
		string $expected_tax_status,
		string $expected_tax_class
	): void {
		$fee_key = 'surchargeFee';
		$fee     = array(
			'id'        => $fee_key,
			'name'      => 'Card surcharge',
			'taxable'   => $taxable,
			'tax_class' => $tax_class,
			'amount'    => '5',
			'total'     => '5',
			'tax'       => 0,
			'tax_data'  => array(),
		);

		$cart_data = new CartData( array(), array(), false, 0, 'cart-hash', array( $fee_key => $fee ) );

		$wc_order = Mockery::mock( WC_Order::class );

		$added_item = null;
		$wc_order->shouldReceive( 'add_item' )
			->once()
			->andReturnUsing(
				function ( $item ) use ( &$added_item ): void {
					$added_item = $item;
				}
			);

		expect( 'do_action' )->once();

		$this->invoke_configure_fees( $this->create_order_creator(), $wc_order, $cart_data );

		$this->assertSame( $expected_tax_status, $added_item->get_tax_status() );
		$this->assertSame( $expected_tax_class, $added_item->get_tax_class() );
	}

	public function fee_taxability_provider(): array {
		return array(
			'non-taxable fee is marked tax-exempt and loses its tax class' => array( false, 'reduced-rate', 'none', '' ),
			'taxable fee keeps its taxable status and tax class'           => array( true, 'reduced-rate', 'taxable', 'reduced-rate' ),
		);
	}

	/**
	 * GIVEN a cart with no fees
	 * WHEN configure_fees() runs via reflection
	 * THEN no order item is added
	 */
	public function test_configure_fees_adds_no_items_when_cart_has_no_fees(): void {
		$cart_data = new CartData( array(), array(), false, 0, 'cart-hash', array() );

		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'add_item' )->never();

		$this->invoke_configure_fees( $this->create_order_creator(), $wc_order, $cart_data );

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a cart with several fees, a surcharge and a discount
	 * WHEN configure_fees() runs via reflection
	 * THEN one order item is added per fee, each carrying its own total
	 */
	public function test_configure_fees_adds_an_item_for_every_cart_fee(): void {
		$fees = array(
			'cardSurcharge'   => array(
				'id'        => 'cardSurcharge',
				'name'      => 'Card surcharge',
				'taxable'   => false,
				'tax_class' => '',
				'amount'    => '3',
				'total'     => '3',
				'tax'       => 0,
				'tax_data'  => array(),
			),
			'loyaltyDiscount' => array(
				'id'        => 'loyaltyDiscount',
				'name'      => 'Loyalty discount',
				'taxable'   => false,
				'tax_class' => '',
				'amount'    => '-12',
				'total'     => '-12',
				'tax'       => 0,
				'tax_data'  => array(),
			),
		);

		$cart_data = new CartData( array(), array(), false, 0, 'cart-hash', $fees );

		$wc_order = Mockery::mock( WC_Order::class );

		$added_totals = array();
		$wc_order->shouldReceive( 'add_item' )
			->twice()
			->with( Mockery::type( WC_Order_Item_Fee::class ) )
			->andReturnUsing(
				function ( $item ) use ( &$added_totals ): void {
					$added_totals[] = $item->get_total();
				}
			);

		expect( 'do_action' )->twice();

		$this->invoke_configure_fees( $this->create_order_creator(), $wc_order, $cart_data );

		$this->assertSame( array( '3', '-12' ), $added_totals );
	}

	/**
	 * Builds a WooCommerceOrderCreator with all collaborators stubbed, so tests only
	 * need to override the one dependency that matters for the case at hand.
	 */
	private function create_order_creator( ?SubscriptionHelper $subscription_helper = null ): WooCommerceOrderCreator {
		return new WooCommerceOrderCreator(
			Mockery::mock( FundingSourceRenderer::class ),
			Mockery::mock( SessionHandler::class ),
			$subscription_helper ?? Mockery::mock( SubscriptionHelper::class ),
			Mockery::mock( CartDataFactory::class ),
			Mockery::mock( ShippingFactory::class ),
			Mockery::mock( PayerFactory::class )
		);
	}

	/**
	 * Invokes the protected configure_fees() method via reflection.
	 */
	private function invoke_configure_fees( WooCommerceOrderCreator $sut, WC_Order $wc_order, CartData $cart_data ): void {
		$method = new ReflectionMethod( WooCommerceOrderCreator::class, 'configure_fees' );
		$method->setAccessible( true );

		$method->invoke( $sut, $wc_order, $cart_data );
	}
}
