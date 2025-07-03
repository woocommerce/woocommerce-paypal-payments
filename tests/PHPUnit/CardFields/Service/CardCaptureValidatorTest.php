<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\CardFields\Service;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\TestCase;

class CardCaptureValidatorTest extends TestCase {

	public function test_is_valid_when_order_status_is_approved() {
		$validator = new CardCaptureValidator();

		$order       = Mockery::mock( Order::class );
		$orderStatus = Mockery::mock( OrderStatus::class );

		$order->shouldReceive( 'status' )->andReturn( $orderStatus );
		$orderStatus->shouldReceive( 'name' )->andReturn( $orderStatus::APPROVED );

		$this->assertTrue( $validator->is_valid( $order ) );
	}

	public function test_is_valid_when_payment_source_is_not_card() {
		$validator = new CardCaptureValidator();

		$order         = Mockery::mock( Order::class );
		$orderStatus   = Mockery::mock( OrderStatus::class );
		$paymentSource = Mockery::mock( PaymentSource::class );

		$order->shouldReceive( 'status' )->andReturn( $orderStatus );
		$orderStatus->shouldReceive( 'name' )->andReturn( $orderStatus::CREATED );

		$order->shouldReceive( 'payment_source' )->andReturn( $paymentSource );
		$paymentSource->shouldReceive( 'name' )->andReturn( 'foo' );

		$this->assertTrue( $validator->is_valid( $order ) );
	}

	public function test_is_valid_when_authentication_result_passes_3ds_approval() {
		$validator = new CardCaptureValidator();

		$order         = Mockery::mock( Order::class );
		$orderStatus   = Mockery::mock( OrderStatus::class );
		$paymentSource = Mockery::mock( PaymentSource::class );

		$order->shouldReceive( 'status' )->andReturn( $orderStatus );
		$orderStatus->shouldReceive( 'name' )->andReturn( $orderStatus::CREATED );
		$order->shouldReceive( 'payment_source' )->andReturn( $paymentSource );
		$paymentSource->shouldReceive( 'name' )->andReturn( 'card' );

		$paymentSource->shouldReceive( 'properties' )->andReturn( (object) [
			'authentication_result' => (object) [
				'liability_shift' => 'POSSIBLE',
			]
		] );

		$this->assertTrue( $validator->is_valid( $order ) );
	}
}
