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

	/**
	 * GIVEN a card payment source with status CREATED
	 * WHEN is_valid is called for a given liability_shift value
	 * THEN the validator allows capture only for a POSSIBLE or YES liability_shift
	 *
	 * @dataProvider card_liability_shift_provider
	 */
	public function test_is_valid_card_payment_source_with_liability_shift(
		string $liability_shift,
		bool $expected
	): void {
		$validator     = new CardCaptureValidator();
		$order         = Mockery::mock( Order::class );
		$orderStatus   = Mockery::mock( OrderStatus::class );
		$paymentSource = Mockery::mock( PaymentSource::class );

		$order->shouldReceive( 'status' )->andReturn( $orderStatus );
		$orderStatus->shouldReceive( 'name' )->andReturn( $orderStatus::CREATED );
		$order->shouldReceive( 'payment_source' )->andReturn( $paymentSource );
		$paymentSource->shouldReceive( 'name' )->andReturn( 'card' );
		$paymentSource->shouldReceive( 'properties' )->andReturn( (object) [
			'authentication_result' => (object) [
				'liability_shift' => $liability_shift,
			],
		] );

		$this->assertSame( $expected, $validator->is_valid( $order ) );
	}

	public function card_liability_shift_provider(): array {
		return [
			'empty liability_shift blocks capture'    => [ '', false ],
			'NO liability_shift blocks capture'       => [ 'NO', false ],
			'YES liability_shift allows capture'      => [ 'YES', true ],
			'POSSIBLE liability_shift allows capture' => [ 'POSSIBLE', true ],
		];
	}

	/**
	 * GIVEN an order with status CREATED and no payment source (null)
	 * WHEN is_valid is called
	 * THEN the validator returns false
	 */
	public function test_is_valid_returns_false_when_payment_source_is_null(): void {
		$validator   = new CardCaptureValidator();
		$order       = Mockery::mock( Order::class );
		$orderStatus = Mockery::mock( OrderStatus::class );

		$order->shouldReceive( 'status' )->andReturn( $orderStatus );
		$orderStatus->shouldReceive( 'name' )->andReturn( $orderStatus::CREATED );
		$order->shouldReceive( 'payment_source' )->andReturn( null );

		$this->assertFalse( $validator->is_valid( $order ) );
	}
}
