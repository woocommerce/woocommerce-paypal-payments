<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

class OrderMetaTraitTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	public function testStoresFlatCardDetails(): void
	{
		$order = $this->orderWithPaymentSource(
			new PaymentSource( 'card', (object) array( 'brand' => 'VISA', 'last_digits' => '1234' ) )
		);

		$wc_order = Mockery::mock( \WC_Order::class );
		$wc_order->shouldReceive( 'update_meta_data' )
			->once()
			->with( PayPalGateway::ORDER_CARD_BRAND_META_KEY, 'VISA' );
		$wc_order->shouldReceive( 'update_meta_data' )
			->once()
			->with( PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY, '1234' );

		$this->invoke( $wc_order, $order );
	}

	public function testStoresNestedWalletCardDetails(): void
	{
		$order = $this->orderWithPaymentSource(
			new PaymentSource(
				'apple_pay',
				(object) array( 'card' => (object) array( 'brand' => 'MASTERCARD', 'last_digits' => '5678' ) )
			)
		);

		$wc_order = Mockery::mock( \WC_Order::class );
		$wc_order->shouldReceive( 'update_meta_data' )
			->once()
			->with( PayPalGateway::ORDER_CARD_BRAND_META_KEY, 'MASTERCARD' );
		$wc_order->shouldReceive( 'update_meta_data' )
			->once()
			->with( PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY, '5678' );

		$this->invoke( $wc_order, $order );
	}

	public function testPayPalSourceStoresNoCardDetails(): void
	{
		$order = $this->orderWithPaymentSource(
			new PaymentSource( 'paypal', (object) array( 'email_address' => 'john@example.com' ) )
		);

		$wc_order = Mockery::mock( \WC_Order::class );
		$wc_order->shouldNotReceive( 'update_meta_data' );

		$this->invoke( $wc_order, $order );
	}

	public function testPartialCardDetailsStoresOnlyAvailableKey(): void
	{
		$order = $this->orderWithPaymentSource(
			new PaymentSource( 'card', (object) array( 'brand' => 'VISA' ) )
		);

		$wc_order = Mockery::mock( \WC_Order::class );
		$wc_order->shouldReceive( 'update_meta_data' )
			->once()
			->with( PayPalGateway::ORDER_CARD_BRAND_META_KEY, 'VISA' );

		$this->invoke( $wc_order, $order );
	}

	public function testNoPaymentSourceStoresNothing(): void
	{
		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'payment_source' )->andReturnNull();

		$wc_order = Mockery::mock( \WC_Order::class );
		$wc_order->shouldNotReceive( 'update_meta_data' );

		$this->invoke( $wc_order, $order );
	}

	/**
	 * @param PaymentSource $payment_source The payment source the order should return.
	 * @return Order&Mockery\MockInterface
	 */
	private function orderWithPaymentSource( PaymentSource $payment_source )
	{
		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'payment_source' )->andReturn( $payment_source );

		return $order;
	}

	private function invoke( $wc_order, $order ): void
	{
		$fixture = new class() {
			use OrderMetaTrait;
		};

		$method = new ReflectionMethod( $fixture, 'add_card_details_meta' );
		$method->setAccessible( true );
		$method->invoke( $fixture, $wc_order, $order );
	}
}
