<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Button\Helper;

use Mockery;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingFactory;
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
}
