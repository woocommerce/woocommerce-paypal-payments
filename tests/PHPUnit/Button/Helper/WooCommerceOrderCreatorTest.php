<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Button\Helper;

use Mockery;
use ReflectionMethod;
use RuntimeException;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ShippingOption;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingOptionFactory;
use WooCommerce\PayPalCommerce\Button\Session\CartDataFactory;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class WooCommerceOrderCreatorTest extends TestCase {

	/**
	 * Builds the SUT with mocked collaborators.
	 *
	 * @param ShippingOptionFactory|null $shipping_option_factory Optional factory mock, so callers
	 *                                                              can set expectations on it.
	 */
	private function sut( ?ShippingOptionFactory $shipping_option_factory = null ): WooCommerceOrderCreator {
		return new WooCommerceOrderCreator(
			Mockery::mock( FundingSourceRenderer::class ),
			Mockery::mock( SessionHandler::class ),
			Mockery::mock( SubscriptionHelper::class ),
			Mockery::mock( CartDataFactory::class ),
			Mockery::mock( ShippingFactory::class ),
			Mockery::mock( PayerFactory::class ),
			$shipping_option_factory ?? Mockery::mock( ShippingOptionFactory::class )
		);
	}

	/**
	 * Builds a ShippingOption. It is a plain value object, so a real instance is used.
	 */
	private function option( string $id, bool $selected ): ShippingOption {
		return new ShippingOption( $id, ucfirst( $id ) . ' label', $selected, new Money( 10.0, 'USD' ), ShippingOption::TYPE_SHIPPING );
	}

	/**
	 * Builds a Shipping mock whose options() returns the given list.
	 *
	 * @param ShippingOption[] $options
	 */
	private function shipping_with_options( array $options ) {
		$shipping = Mockery::mock( Shipping::class );
		$shipping->shouldReceive( 'options' )->andReturn( $options );

		return $shipping;
	}

	/**
	 * Stubs WC() so that WC()->cart and WC()->session resolve as given.
	 *
	 * @param mixed $cart    Value for WC()->cart.
	 * @param mixed $session Value for WC()->session.
	 */
	private function stub_wc( $cart, $session ): void {
		when( 'WC' )->justReturn(
			(object) array(
				'cart'    => $cart,
				'session' => $session,
			)
		);
	}

	private function invoke_resolve_shipping_option( WooCommerceOrderCreator $sut, ?Shipping $shipping, bool $needs_shipping, bool $is_current_wc_cart ): ?ShippingOption {
		$method = new ReflectionMethod( WooCommerceOrderCreator::class, 'resolve_shipping_option' );
		$method->setAccessible( true );

		return $method->invoke( $sut, $shipping, $needs_shipping, $is_current_wc_cart );
	}

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

		$sut = $this->sut();

		// Testing a private method, as we want to confirm the presence and ability of a WP filter.
		$method = new ReflectionMethod( WooCommerceOrderCreator::class, 'get_shipping' );
		$method->setAccessible( true );

		$result = $method->invoke( $sut, $order, $paypal_data );

		$this->assertSame( $filter_shipping, $result );
	}

	/**
	 * GIVEN a PayPal order that reports two shipping options, only one flagged as selected
	 * WHEN resolve_shipping_option() is invoked
	 * THEN the option flagged as selected is returned
	 * AND the WooCommerce cart fallback is never consulted
	 */
	public function test_resolve_shipping_option_prefers_paypal_selected_option_over_others(): void {
		$option_a = $this->option( 'a', false );
		$option_b = $this->option( 'b', true );

		$shipping_option_factory = Mockery::mock( ShippingOptionFactory::class );
		$shipping_option_factory->shouldNotReceive( 'from_wc_cart' );

		$sut = $this->sut( $shipping_option_factory );

		$result = $this->invoke_resolve_shipping_option(
			$sut,
			$this->shipping_with_options( [ $option_a, $option_b ] ),
			true,
			true
		);

		$this->assertSame( $option_b, $result );
	}

	/**
	 * GIVEN a PayPal order that reports shipping options but none is flagged as selected
	 * WHEN resolve_shipping_option() is invoked
	 * THEN the first option in the list is returned
	 */
	public function test_resolve_shipping_option_falls_back_to_first_paypal_option_when_none_selected(): void {
		$option_a = $this->option( 'a', false );
		$option_b = $this->option( 'b', false );

		$sut = $this->sut();

		$result = $this->invoke_resolve_shipping_option(
			$sut,
			$this->shipping_with_options( [ $option_a, $option_b ] ),
			true,
			true
		);

		$this->assertSame( $option_a, $result );
	}

	/**
	 * GIVEN a PayPal order (e.g. Google Pay express checkout) that carries no shipping options
	 * AND the order is being created from the current, live WC cart
	 * WHEN resolve_shipping_option() is invoked
	 * THEN the option WooCommerce flags as selected is used
	 *
	 * This is the regression test for the bug where Google Pay express checkout failed with
	 * "No shipping method has been selected." when the Pay Now setting was disabled.
	 */
	public function test_resolve_shipping_option_falls_back_to_wc_cart_selected_option_when_paypal_has_none(): void {
		$wc_option_selected   = $this->option( 'flat_rate', true );
		$wc_option_unselected = $this->option( 'free_shipping', false );

		$shipping_option_factory = Mockery::mock( ShippingOptionFactory::class );
		$shipping_option_factory->shouldReceive( 'from_wc_cart' )
			->andReturn( [ $wc_option_unselected, $wc_option_selected ] );

		$sut = $this->sut( $shipping_option_factory );

		$this->stub_wc( Mockery::mock()->shouldIgnoreMissing(), Mockery::mock()->shouldIgnoreMissing() );

		$result = $this->invoke_resolve_shipping_option(
			$sut,
			$this->shipping_with_options( [] ),
			true,
			true
		);

		$this->assertSame( $wc_option_selected, $result );
	}

	/**
	 * GIVEN a PayPal order that carries no shipping options
	 * AND the WC cart shipping options carry none flagged as selected
	 * AND the order is being created from the current, live WC cart
	 * WHEN resolve_shipping_option() is invoked
	 * THEN the first WC cart option is used
	 */
	public function test_resolve_shipping_option_falls_back_to_first_wc_cart_option_when_none_selected(): void {
		$wc_option_a = $this->option( 'flat_rate', false );
		$wc_option_b = $this->option( 'free_shipping', false );

		$shipping_option_factory = Mockery::mock( ShippingOptionFactory::class );
		$shipping_option_factory->shouldReceive( 'from_wc_cart' )
			->andReturn( [ $wc_option_a, $wc_option_b ] );

		$sut = $this->sut( $shipping_option_factory );

		$this->stub_wc( Mockery::mock()->shouldIgnoreMissing(), Mockery::mock()->shouldIgnoreMissing() );

		$result = $this->invoke_resolve_shipping_option(
			$sut,
			$this->shipping_with_options( [] ),
			true,
			true
		);

		$this->assertSame( $wc_option_a, $result );
	}

	/**
	 * GIVEN no PayPal shipping information at all (legacy code path, e.g. a Payer-only response)
	 * WHEN resolve_shipping_option() is invoked
	 * THEN the WC cart fallback is used, preserving the pre-existing behaviour
	 */
	public function test_resolve_shipping_option_uses_wc_cart_fallback_when_shipping_is_null(): void {
		$wc_option = $this->option( 'flat_rate', true );

		$shipping_option_factory = Mockery::mock( ShippingOptionFactory::class );
		$shipping_option_factory->shouldReceive( 'from_wc_cart' )->andReturn( [ $wc_option ] );

		$sut = $this->sut( $shipping_option_factory );

		$this->stub_wc( Mockery::mock()->shouldIgnoreMissing(), Mockery::mock()->shouldIgnoreMissing() );

		$result = $this->invoke_resolve_shipping_option( $sut, null, true, true );

		$this->assertSame( $wc_option, $result );
	}

	/**
	 * GIVEN a cart that does not need shipping (e.g. virtual/downloadable products only)
	 * WHEN resolve_shipping_option() is invoked
	 * THEN null is returned
	 * AND the WC cart fallback is never consulted
	 */
	public function test_resolve_shipping_option_returns_null_when_cart_does_not_need_shipping(): void {
		$shipping_option_factory = Mockery::mock( ShippingOptionFactory::class );
		$shipping_option_factory->shouldNotReceive( 'from_wc_cart' );

		$sut = $this->sut( $shipping_option_factory );

		$result = $this->invoke_resolve_shipping_option(
			$sut,
			$this->shipping_with_options( [ $this->option( 'a', true ) ] ),
			false,
			true
		);

		$this->assertNull( $result );
	}

	/**
	 * GIVEN a PayPal order that carries no shipping options
	 * AND the order is NOT created from the current, live WC cart (app switch / agentic checkout
	 *     snapshot cart)
	 * WHEN resolve_shipping_option() is invoked
	 * THEN null is returned
	 * AND the WC cart fallback is never consulted, since the live cart cannot be trusted to match
	 */
	public function test_resolve_shipping_option_returns_null_for_foreign_cart_when_paypal_has_no_options(): void {
		$shipping_option_factory = Mockery::mock( ShippingOptionFactory::class );
		$shipping_option_factory->shouldNotReceive( 'from_wc_cart' );

		$sut = $this->sut( $shipping_option_factory );

		$result = $this->invoke_resolve_shipping_option(
			$sut,
			$this->shipping_with_options( [] ),
			true,
			false
		);

		$this->assertNull( $result );
	}

	/**
	 * GIVEN the WC cart needs shipping and must fall back to WooCommerce
	 * AND WC()->session is unavailable
	 * WHEN resolve_shipping_option() is invoked
	 * THEN null is returned without raising an error
	 * AND the WC cart fallback factory is never called
	 */
	public function test_resolve_shipping_option_returns_null_when_wc_session_unavailable(): void {
		$shipping_option_factory = Mockery::mock( ShippingOptionFactory::class );
		$shipping_option_factory->shouldNotReceive( 'from_wc_cart' );

		$sut = $this->sut( $shipping_option_factory );

		$this->stub_wc( Mockery::mock()->shouldIgnoreMissing(), null );

		$result = $this->invoke_resolve_shipping_option( $sut, null, true, true );

		$this->assertNull( $result );
	}

	/**
	 * GIVEN the WC cart needs shipping and must fall back to WooCommerce
	 * AND WC()->cart is unavailable
	 * WHEN resolve_shipping_option() is invoked
	 * THEN null is returned without raising an error
	 * AND the WC cart fallback factory is never called
	 */
	public function test_resolve_shipping_option_returns_null_when_wc_cart_unavailable(): void {
		$shipping_option_factory = Mockery::mock( ShippingOptionFactory::class );
		$shipping_option_factory->shouldNotReceive( 'from_wc_cart' );

		$sut = $this->sut( $shipping_option_factory );

		$this->stub_wc( null, Mockery::mock()->shouldIgnoreMissing() );

		$result = $this->invoke_resolve_shipping_option( $sut, null, true, true );

		$this->assertNull( $result );
	}

	/**
	 * GIVEN WooCommerce reports no shipping options at all (from_wc_cart returns an empty list)
	 * WHEN resolve_shipping_option() is invoked
	 * THEN null is returned
	 */
	public function test_resolve_shipping_option_returns_null_when_wc_cart_has_no_options(): void {
		$shipping_option_factory = Mockery::mock( ShippingOptionFactory::class );
		$shipping_option_factory->shouldReceive( 'from_wc_cart' )->andReturn( [] );

		$sut = $this->sut( $shipping_option_factory );

		$this->stub_wc( Mockery::mock()->shouldIgnoreMissing(), Mockery::mock()->shouldIgnoreMissing() );

		$result = $this->invoke_resolve_shipping_option( $sut, null, true, true );

		$this->assertNull( $result );
	}

	/**
	 * GIVEN an order that needs shipping but no shipping option could be resolved
	 * WHEN configure_addresses() is invoked
	 * THEN a RuntimeException is thrown reporting that no shipping method has been selected
	 */
	public function test_configure_addresses_throws_when_shipping_needed_but_no_option_resolved(): void {
		$wc_order = Mockery::mock( WC_Order::class );

		when( 'WC' )->justReturn( (object) array( 'customer' => null ) );

		$sut = $this->sut();

		$method = new ReflectionMethod( WooCommerceOrderCreator::class, 'configure_addresses' );
		$method->setAccessible( true );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'No shipping method has been selected.' );

		$method->invoke( $sut, $wc_order, null, null, true, null );
	}
}
