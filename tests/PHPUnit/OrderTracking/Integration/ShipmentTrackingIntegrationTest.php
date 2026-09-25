<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking\Integration;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ModularTestCase;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentFactoryInterface;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface;
use function Brain\Monkey\Actions\expectAdded as expectActionAdded;
use function Brain\Monkey\Filters\expectAdded as expectFilterAdded;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\OrderTracking\Integration\ShipmentTrackingIntegration
 */
class ShipmentTrackingIntegrationTest extends ModularTestCase {

	/** @var Mockery\MockInterface&OrderEndpoint */
	private $order_endpoint;

	/** @var Mockery\MockInterface&OrderTrackingEndpoint */
	private $order_tracking_endpoint;

	/** @var ShipmentFactoryInterface */
	private $shipment_factory;

	/** @var Mockery\MockInterface&LoggerInterface */
	private $logger;

	private ShipmentTrackingIntegration $testee;

	public function setUp(): void {
		parent::setUp();

		when( 'check_ajax_referer' )->justReturn( true );

		$this->order_endpoint = Mockery::mock( OrderEndpoint::class );
		$this->bootstrapModule(
			array(
				'api.endpoint.order' => function () {
					return $this->order_endpoint;
				},
			)
		);

		$this->order_tracking_endpoint = Mockery::mock( OrderTrackingEndpoint::class );

		$this->shipment_factory = $this->createStub( ShipmentFactoryInterface::class );
		$this->shipment_factory->method( 'create_shipment' )->willReturn( $this->createStub( ShipmentInterface::class ) );

		$this->logger = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		$this->testee = new ShipmentTrackingIntegration( $this->shipment_factory, $this->logger, $this->order_tracking_endpoint );

		$_POST = array();
	}

	public function tearDown(): void {
		$_POST = array();

		parent::tearDown();
	}

	/**
	 * GIVEN the WooCommerce Shipment Tracking plugin registers its own handler on the shared
	 * `wp_ajax_wc_shipment_tracking_save_form` action at the default priority, and that
	 * handler ends the request with die()
	 * WHEN integrate() registers this plugin's own handler on the same action
	 * THEN it is registered at priority 5, ahead of the default priority 10, so it runs and
	 * completes before the other plugin's handler can terminate the request
	 */
	public function test_ajax_callback_is_registered_ahead_of_the_default_priority(): void {
		expectActionAdded( 'wp_ajax_wc_shipment_tracking_save_form' )
			->once()
			->with( Mockery::type( 'callable' ), 5, 1 );

		$this->testee->integrate();
	}

	/**
	 * GIVEN a merchant that adds tracking through the REST API rather than the classic
	 * admin-ajax form
	 * WHEN integrate() runs
	 * THEN the REST response filter is still registered, unaffected by the ajax priority fix
	 */
	public function test_rest_filter_is_still_registered(): void {
		expectFilterAdded( 'woocommerce_rest_prepare_order_shipment_tracking' )
			->once()
			->with( Mockery::type( 'callable' ), 10, 3 );

		$this->testee->integrate();
	}

	/**
	 * GIVEN a valid nonce, a resolvable order, a tracking number, a carrier and a capture id
	 * AND the order carries no tracking yet
	 * WHEN the ajax handler runs
	 * THEN the tracking is sent to PayPal as a new tracker
	 */
	public function test_valid_submission_without_existing_tracking_adds_tracking(): void {
		$wc_order = Mockery::mock( WC_Order::class );
		when( 'wc_get_order' )->justReturn( $wc_order );

		$this->order_endpoint->shouldReceive( 'order' )
			->once()
			->with( $wc_order )
			->andReturn( $this->paypal_order_with_capture( 'CAPTURE-1' ) );

		$this->order_tracking_endpoint->shouldReceive( 'get_tracking_information' )
			->once()
			->with( 42, '1Z999' )
			->andReturn( null );

		$this->order_tracking_endpoint->shouldReceive( 'add_tracking_information' )->once();
		$this->order_tracking_endpoint->shouldNotReceive( 'update_tracking_information' );

		$_POST = array(
			'order_id'          => '42',
			'tracking_number'   => '1Z999',
			'tracking_provider' => 'UPS',
		);

		$callback = $this->captured_ajax_callback();
		$callback();
	}

	/**
	 * GIVEN a valid nonce, a resolvable order, a tracking number, a carrier and a capture id
	 * AND the order already carries tracking with that number
	 * WHEN the ajax handler runs
	 * THEN the existing tracker is updated rather than a new one created
	 */
	public function test_valid_submission_with_existing_tracking_updates_tracking(): void {
		$wc_order = Mockery::mock( WC_Order::class );
		when( 'wc_get_order' )->justReturn( $wc_order );

		$this->order_endpoint->shouldReceive( 'order' )
			->once()
			->with( $wc_order )
			->andReturn( $this->paypal_order_with_capture( 'CAPTURE-1' ) );

		$this->order_tracking_endpoint->shouldReceive( 'get_tracking_information' )
			->once()
			->with( 42, '1Z999' )
			->andReturn( $this->createStub( ShipmentInterface::class ) );

		$this->order_tracking_endpoint->shouldReceive( 'update_tracking_information' )->once();
		$this->order_tracking_endpoint->shouldNotReceive( 'add_tracking_information' );

		$_POST = array(
			'order_id'          => '42',
			'tracking_number'   => '1Z999',
			'tracking_provider' => 'UPS',
		);

		$callback = $this->captured_ajax_callback();
		$callback();
	}

	/**
	 * GIVEN a submission missing the tracking number, the carrier, or a capture id on the
	 * PayPal order
	 * WHEN the ajax handler runs
	 * THEN nothing is sent to PayPal
	 *
	 * @dataProvider incomplete_submission_provider
	 */
	public function test_incomplete_submission_sends_nothing_to_paypal( array $post, bool $order_has_capture ): void {
		$wc_order = Mockery::mock( WC_Order::class );
		when( 'wc_get_order' )->justReturn( $wc_order );

		$this->order_endpoint->shouldReceive( 'order' )
			->andReturn(
				$order_has_capture
					? $this->paypal_order_with_capture( 'CAPTURE-1' )
					: $this->paypal_order_without_capture()
			);

		$this->order_tracking_endpoint->shouldNotReceive( 'get_tracking_information' );
		$this->order_tracking_endpoint->shouldNotReceive( 'add_tracking_information' );
		$this->order_tracking_endpoint->shouldNotReceive( 'update_tracking_information' );

		$_POST = $post;

		$callback = $this->captured_ajax_callback();
		$callback();
	}

	/** @return array<string, array{array<string, string>, bool}> */
	public function incomplete_submission_provider(): array {
		return array(
			'tracking number missing'                    => array(
				array(
					'order_id'          => '42',
					'tracking_provider' => 'UPS',
				),
				true,
			),
			'carrier missing (provider and custom empty)' => array(
				array(
					'order_id'        => '42',
					'tracking_number' => '1Z999',
				),
				true,
			),
			'capture id missing on the PayPal order'      => array(
				array(
					'order_id'          => '42',
					'tracking_number'   => '1Z999',
					'tracking_provider' => 'UPS',
				),
				false,
			),
		);
	}

	/**
	 * Runs integrate(), captures the ajax callback it registers, and returns it so it can be
	 * invoked directly in assertions.
	 */
	private function captured_ajax_callback(): callable {
		$captured = null;

		expectActionAdded( 'wp_ajax_wc_shipment_tracking_save_form' )
			->once()
			->whenHappen(
				static function ( callable $callback ) use ( &$captured ): void {
					$captured = $callback;
				}
			);

		$this->testee->integrate();

		$this->assertIsCallable( $captured );

		return $captured;
	}

	/**
	 * Builds a PayPal order whose only purchase unit has a live capture with the given id.
	 */
	private function paypal_order_with_capture( string $capture_id ): Order {
		$amount = new Amount( new Money( 10.0, 'USD' ) );

		$capture = new Capture(
			$capture_id,
			new CaptureStatus( CaptureStatus::COMPLETED ),
			$amount,
			true,
			'ELIGIBLE',
			'',
			'',
			null,
			null
		);

		$purchase_unit = new PurchaseUnit(
			$amount,
			array(),
			null,
			'default',
			'',
			'',
			'',
			'',
			new Payments( array(), array( $capture ) )
		);

		return new Order( 'WP-ORDER-1', array( $purchase_unit ), new OrderStatus( OrderStatus::COMPLETED ) );
	}

	/**
	 * Builds a PayPal order whose only purchase unit carries no capture or authorization at
	 * all, so no transaction id can be derived from it.
	 */
	private function paypal_order_without_capture(): Order {
		$amount = new Amount( new Money( 10.0, 'USD' ) );

		$purchase_unit = new PurchaseUnit(
			$amount,
			array(),
			null,
			'default',
			'',
			'',
			'',
			'',
			new Payments( array(), array() )
		);

		return new Order( 'WP-ORDER-1', array( $purchase_unit ), new OrderStatus( OrderStatus::COMPLETED ) );
	}
}
