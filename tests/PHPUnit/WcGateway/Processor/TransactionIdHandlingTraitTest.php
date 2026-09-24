<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\TestCase;

class TransactionIdHandlingTraitTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	/**
	 * @var object
	 */
	private $fixture;

	public function setUp(): void
	{
		parent::setUp();

		$this->fixture = new class() {
			use TransactionIdHandlingTrait;
		};
	}

	/**
	 * GIVEN a PayPal order whose purchase unit has a single completed capture
	 * WHEN the transaction id is looked up
	 * THEN the completed capture's id is returned, the ordinary path
	 */
	public function testCompletedCaptureReturnsItsId(): void
	{
		$order = $this->orderWithCaptures(
			array( $this->capture( 'capture-1', CaptureStatus::COMPLETED ) )
		);

		$this->assertSame( 'capture-1', $this->fixture->get_paypal_order_transaction_id( $order ) );
	}

	/**
	 * GIVEN a renewal on a vaulted card whose only capture was declined
	 * WHEN the transaction id is looked up
	 * THEN null is returned instead of the declined capture's id, so the order is not
	 *      wrongly treated as already paid
	 */
	public function testDeclinedCaptureReturnsNull(): void
	{
		$order = $this->orderWithCaptures(
			array( $this->capture( 'capture-declined', CaptureStatus::DECLINED ) )
		);

		$this->assertNull( $this->fixture->get_paypal_order_transaction_id( $order ) );
	}

	/**
	 * GIVEN a capture that failed to process
	 * WHEN the transaction id is looked up
	 * THEN null is returned instead of the failed capture's id
	 */
	public function testFailedCaptureReturnsNull(): void
	{
		$order = $this->orderWithCaptures(
			array( $this->capture( 'capture-failed', CaptureStatus::FAILED ) )
		);

		$this->assertNull( $this->fixture->get_paypal_order_transaction_id( $order ) );
	}

	/**
	 * GIVEN a capture whose status still describes money that moved or may yet settle
	 * WHEN the transaction id is looked up
	 * THEN its id is returned, since it is deliberately not treated as dead
	 *
	 * @dataProvider live_capture_status_provider
	 */
	public function testLiveCaptureStatusReturnsItsId( string $status ): void
	{
		$order = $this->orderWithCaptures(
			array( $this->capture( 'capture-live', $status ) )
		);

		$this->assertSame( 'capture-live', $this->fixture->get_paypal_order_transaction_id( $order ) );
	}

	public function live_capture_status_provider(): array
	{
		return array(
			'completed'          => array( CaptureStatus::COMPLETED ),
			'pending may settle' => array( CaptureStatus::PENDING ),
			'refunded'           => array( CaptureStatus::REFUNDED ),
			'partially refunded' => array( CaptureStatus::PARTIALLY_REFUNDED ),
		);
	}

	/**
	 * GIVEN a purchase unit with a declined capture followed by a completed one
	 * WHEN the transaction id is looked up
	 * THEN the completed capture's id is returned, proving the whole list is scanned
	 *      rather than only the first element
	 */
	public function testDeclinedCaptureFollowedByCompletedReturnsCompletedId(): void
	{
		$order = $this->orderWithCaptures(
			array(
				$this->capture( 'capture-declined', CaptureStatus::DECLINED ),
				$this->capture( 'capture-completed', CaptureStatus::COMPLETED ),
			)
		);

		$this->assertSame( 'capture-completed', $this->fixture->get_paypal_order_transaction_id( $order ) );
	}

	/**
	 * GIVEN no usable capture but a still-open authorization
	 * WHEN the transaction id is looked up
	 * THEN the authorization's id is returned as a fallback
	 */
	public function testDeclinedCaptureFallsBackToUsableAuthorization(): void
	{
		$order = $this->orderWithCapturesAndAuthorizations(
			array( $this->capture( 'capture-declined', CaptureStatus::DECLINED ) ),
			array( $this->authorization( 'auth-created', AuthorizationStatus::CREATED ) )
		);

		$this->assertSame( 'auth-created', $this->fixture->get_paypal_order_transaction_id( $order ) );
	}

	/**
	 * GIVEN neither a usable capture nor a usable authorization is available
	 * WHEN the transaction id is looked up
	 * THEN null is returned, so the fall-through to authorizations does not reintroduce
	 *      the same bug
	 */
	public function testDeclinedCaptureAndDeniedAuthorizationReturnsNull(): void
	{
		$order = $this->orderWithCapturesAndAuthorizations(
			array( $this->capture( 'capture-declined', CaptureStatus::DECLINED ) ),
			array( $this->authorization( 'auth-denied', AuthorizationStatus::DENIED ) )
		);

		$this->assertNull( $this->fixture->get_paypal_order_transaction_id( $order ) );
	}

	/**
	 * GIVEN a PayPal order with no purchase units at all
	 * WHEN the transaction id is looked up
	 * THEN null is returned
	 */
	public function testNoPurchaseUnitsReturnsNull(): void
	{
		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'purchase_units' )->andReturn( array() );

		$this->assertNull( $this->fixture->get_paypal_order_transaction_id( $order ) );
	}

	/**
	 * GIVEN a purchase unit that has no payments information at all
	 * WHEN the transaction id is looked up
	 * THEN null is returned
	 */
	public function testNoPaymentsOnPurchaseUnitReturnsNull(): void
	{
		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'payments' )->andReturnNull();

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'purchase_units' )->andReturn( array( $purchase_unit ) );

		$this->assertNull( $this->fixture->get_paypal_order_transaction_id( $order ) );
	}

	/**
	 * @param Capture[] $captures The captures the order's purchase unit should contain.
	 * @return Order&Mockery\MockInterface
	 */
	private function orderWithCaptures( array $captures )
	{
		return $this->orderWithCapturesAndAuthorizations( $captures, array() );
	}

	/**
	 * @param Capture[]       $captures       The captures the order's purchase unit should contain.
	 * @param Authorization[] $authorizations The authorizations the order's purchase unit should contain.
	 * @return Order&Mockery\MockInterface
	 */
	private function orderWithCapturesAndAuthorizations( array $captures, array $authorizations )
	{
		$payments = new Payments( $authorizations, $captures );

		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'payments' )->andReturn( $payments );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'purchase_units' )->andReturn( array( $purchase_unit ) );

		return $order;
	}

	private function capture( string $id, string $status ): Capture
	{
		return new Capture(
			$id,
			new CaptureStatus( $status ),
			new Amount( new Money( 10.00, 'USD' ) ),
			true,
			'',
			'',
			'',
			null,
			null
		);
	}

	private function authorization( string $id, string $status ): Authorization
	{
		return new Authorization(
			$id,
			new AuthorizationStatus( $status ),
			null
		);
	}
}
