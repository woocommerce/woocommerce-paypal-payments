<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use Mockery;
use ReflectionMethod;
use RuntimeException;
use WC_Order;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order as PayPalOrder;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Shipping;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingFactory;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\AppliedCouponsBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PaymentMethod;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Schema\ShippingOption;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class AgenticCheckoutProcessorTest extends TestCase {

	private function make_sut(
		ShippingFactory $shipping_factory,
		?WooCommerceOrderCreator $wc_order_creator = null
	): AgenticCheckoutProcessor {
		return new AgenticCheckoutProcessor(
			Mockery::mock( PayPalOrderManager::class ),
			$wc_order_creator ?? Mockery::mock( WooCommerceOrderCreator::class ),
			Mockery::mock( AgenticCartBuilder::class ),
			Mockery::mock( AppliedCouponsBuilder::class ),
			$shipping_factory
		);
	}

	/**
	 * GIVEN PayPalCart with one selected ShippingOption (id=flat_rate:4, name=Standard Shipping, price=5.99 USD)
	 * AND ShippingFactory::from_paypal_response() returns $expected_shipping
	 * WHEN build_shipping_from_paypal_cart() is called via reflection
	 * THEN return value === $expected_shipping
	 * AND the factory received a stdClass with options[0]->id === 'flat_rate:4'
	 */
	public function test_selected_option_produces_shipping_entity(): void {
		$money = Mockery::mock( Money::class );
		$money->allows( 'currency_code' )->andReturn( 'USD' );
		$money->allows( 'value' )->andReturn( 5.99 );

		$option = Mockery::mock( ShippingOption::class );
		$option->allows( 'is_selected' )->andReturn( true );
		$option->allows( 'id' )->andReturn( 'flat_rate:4' );
		$option->allows( 'name' )->andReturn( 'Standard Shipping' );
		$option->allows( 'price' )->andReturn( $money );

		$cart = Mockery::mock( PayPalCart::class );
		$cart->allows( 'available_shipping_options' )->andReturn( array( $option ) );
		$cart->allows( 'customer' )->andReturn( null );
		$cart->allows( 'shipping_address' )->andReturn( null );

		$expected_shipping = Mockery::mock( Shipping::class );
		$captured_data     = null;

		$shipping_factory = Mockery::mock( ShippingFactory::class );
		$shipping_factory->allows( 'from_paypal_response' )
			->andReturnUsing(
				function ( \stdClass $data ) use ( $expected_shipping, &$captured_data ): Shipping {
					$captured_data = $data;

					return $expected_shipping;
				}
			);

		when( 'wp_json_encode' )->alias( 'json_encode' );

		$method = new ReflectionMethod(
			AgenticCheckoutProcessor::class,
			'build_shipping_from_paypal_cart'
		);
		$method->setAccessible( true );

		$result = $method->invoke( $this->make_sut( $shipping_factory ), $cart );

		$this->assertSame( $expected_shipping, $result );
		$this->assertSame( 'flat_rate:4', $captured_data->options[0]->id ?? null );
		$this->assertTrue( $captured_data->options[0]->selected ?? false );
		$this->assertSame( 'Standard Shipping', $captured_data->options[0]->label ?? null );
	}

	/**
	 * GIVEN PayPalCart with one ShippingOption where is_selected() = false
	 * WHEN build_shipping_from_paypal_cart() is called via reflection
	 * THEN return value is null
	 * AND ShippingFactory::from_paypal_response() is never called
	 */
	public function test_no_selected_option_returns_null(): void {
		$option = Mockery::mock( ShippingOption::class );
		$option->allows( 'is_selected' )->andReturn( false );

		$cart = Mockery::mock( PayPalCart::class );
		$cart->allows( 'available_shipping_options' )->andReturn( array( $option ) );

		$shipping_factory = Mockery::mock( ShippingFactory::class );
		$shipping_factory->expects( 'from_paypal_response' )->never();

		$method = new ReflectionMethod(
			AgenticCheckoutProcessor::class,
			'build_shipping_from_paypal_cart'
		);
		$method->setAccessible( true );

		$result = $method->invoke( $this->make_sut( $shipping_factory ), $cart );

		$this->assertNull( $result );
	}

	/**
	 * GIVEN PayPalCart with no available_shipping_options
	 * WHEN build_shipping_from_paypal_cart() is called via reflection
	 * THEN return value is null
	 * AND ShippingFactory::from_paypal_response() is never called
	 */
	public function test_no_options_returns_null(): void {
		$cart = Mockery::mock( PayPalCart::class );
		$cart->allows( 'available_shipping_options' )->andReturn( array() );

		$shipping_factory = Mockery::mock( ShippingFactory::class );
		$shipping_factory->expects( 'from_paypal_response' )->never();

		$method = new ReflectionMethod(
			AgenticCheckoutProcessor::class,
			'build_shipping_from_paypal_cart'
		);
		$method->setAccessible( true );

		$result = $method->invoke( $this->make_sut( $shipping_factory ), $cart );

		$this->assertNull( $result );
	}

	/**
	 * GIVEN WooCommerceOrderCreator::create_from_paypal_order() throws RuntimeException
	 * WHEN create_order() is called via reflection
	 * THEN remove_filter() is called with the hook name (finally block runs even on exception)
	 */
	public function test_remove_filter_is_called_even_when_create_from_paypal_order_throws(): void {
		$remove_filter_hook = null;

		when( 'add_filter' )->justReturn( true );
		when( 'remove_filter' )->alias(
			static function ( string $hook ) use ( &$remove_filter_hook ): bool {
				$remove_filter_hook = $hook;

				return true;
			}
		);

		$wc_order_creator = Mockery::mock( WooCommerceOrderCreator::class );
		$wc_order_creator->allows( 'create_from_paypal_order' )
			->andThrow( new RuntimeException( 'boom' ) );

		$cart = Mockery::mock( PayPalCart::class );
		$cart->allows( 'customer' )->andReturn( null );
		$cart->allows( 'billing_address' )->andReturn( null );
		$cart->allows( 'shipping_address' )->andReturn( null );
		$cart->allows( 'available_shipping_options' )->andReturn( array() );

		$payment_method = Mockery::mock( PaymentMethod::class );
		$payment_method->allows( 'token' )->andReturn( 'tok' );
		$payment_method->allows( 'payer_id' )->andReturn( 'pid' );

		try {
			$method = new ReflectionMethod( AgenticCheckoutProcessor::class, 'create_order' );
			$method->setAccessible( true );
			$method->invoke(
				$this->make_sut( Mockery::mock( ShippingFactory::class ), $wc_order_creator ),
				Mockery::mock( PayPalOrder::class ),
				Mockery::mock( CartData::class ),
				$cart,
				$payment_method,
				'ORDER-123'
			);
		} catch ( RuntimeException $e ) {
			// Expected: exception from create_from_paypal_order propagates through the finally block.
		}

		$this->assertSame(
			'woocommerce_paypal_payments_order_creator_get_shipping',
			$remove_filter_hook,
			'remove_filter must be called with the correct hook even when create_from_paypal_order throws'
		);
	}
}
