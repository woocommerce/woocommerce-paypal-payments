<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderEndpoints\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\OrderEndpoints\Helper\EarlyOrderHandler
 */
class EarlyOrderHandlerTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	private const PAYPAL_ORDER_ID = 'PAYPAL-ORDER-1';

	private const ORDER_RECEIVED_URL = 'https://example.com/checkout/order-received/55/';

	public function tearDown(): void
	{
		unset( $_REQUEST['ppcp-resume-order'] );

		parent::tearDown();
	}

	/**
	 * GIVEN a checkout request that resumes the WC order of the PayPal order in the session
	 * WHEN the WC order can still be paid
	 * THEN it is resumed
	 *
	 * @dataProvider payable_status_provider
	 */
	public function test_determine_wc_order_id_resumes_payable_order( string $status ): void
	{
		$_REQUEST['ppcp-resume-order'] = '55';

		$sut = $this->create_sut( '55', 55, $this->wc_order( $status ) );
		expect( 'wp_send_json' )->never();

		$this->assertSame( 55, $sut->determine_wc_order_id( null ) );
	}

	public function payable_status_provider(): array
	{
		return array(
			'pending' => array( 'pending' ),
			'failed'  => array( 'failed' ),
		);
	}

	/**
	 * GIVEN a checkout request that resumes the WC order of the PayPal order in the session
	 * WHEN the approved webhook already settled the WC order
	 * THEN the request ends with a redirect to its order-received page
	 *
	 * @dataProvider settled_status_provider
	 */
	public function test_determine_wc_order_id_redirects_settled_order( string $status ): void
	{
		$_REQUEST['ppcp-resume-order'] = '55';

		$session_handler = $this->session_handler( '55' );
		$session_handler->shouldReceive( 'destroy_session_data' )->once();

		$sut = $this->create_sut( '55', 55, $this->wc_order( $status ), $session_handler );
		expect( 'wp_send_json' )->once()->with(
			array(
				'result'   => 'success',
				'redirect' => self::ORDER_RECEIVED_URL,
			)
		);

		$this->assertSame( 7, $sut->determine_wc_order_id( 7 ) );
	}

	public function settled_status_provider(): array
	{
		return array(
			'processing' => array( 'processing' ),
			'completed'  => array( 'completed' ),
			'on-hold'    => array( 'on-hold' ),
		);
	}

	/**
	 * GIVEN a checkout request that resumes the WC order of the PayPal order in the session
	 * WHEN the WC order was not settled by the approved webhook
	 * THEN it is not resumed, so WC_Checkout creates a new order
	 *
	 * @dataProvider unsettled_order_provider
	 */
	public function test_determine_wc_order_id_does_not_resume_unsettled_order( string $status, string $paypal_order_id ): void
	{
		$_REQUEST['ppcp-resume-order'] = '55';

		$sut = $this->create_sut( '55', 55, $this->wc_order( $status, $paypal_order_id ) );
		expect( 'wp_send_json' )->never();

		$this->assertSame( 7, $sut->determine_wc_order_id( 7 ) );
	}

	public function unsettled_order_provider(): array
	{
		return array(
			'cancelled'                         => array( 'cancelled', self::PAYPAL_ORDER_ID ),
			'refunded'                          => array( 'refunded', self::PAYPAL_ORDER_ID ),
			'paid through another PayPal order' => array( 'processing', 'PAYPAL-ORDER-2' ),
		);
	}

	/**
	 * GIVEN a checkout request with ppcp-resume-order
	 * WHEN the value does not belong to the order awaiting payment
	 * THEN the initial value is returned
	 *
	 * @dataProvider mismatch_provider
	 */
	public function test_determine_wc_order_id_keeps_value_without_matching_order(
		string $requested,
		string $custom_id,
		int $awaiting_payment
	): void {
		$_REQUEST['ppcp-resume-order'] = $requested;

		$sut = $this->create_sut( $custom_id, $awaiting_payment, $this->wc_order( 'pending' ) );

		$this->assertSame( 7, $sut->determine_wc_order_id( 7 ) );
	}

	public function mismatch_provider(): array
	{
		return array(
			'request does not match the PayPal order' => array( '56', '55', 55 ),
			'PayPal order does not match the session' => array( '55', '55', 54 ),
		);
	}

	/**
	 * GIVEN a checkout request that resumes the WC order of the PayPal order in the session
	 * WHEN the WC order does not exist
	 * THEN the initial value is returned
	 */
	public function test_determine_wc_order_id_keeps_value_without_wc_order(): void
	{
		$_REQUEST['ppcp-resume-order'] = '55';

		$sut = $this->create_sut( '55', 55, false );

		$this->assertSame( 7, $sut->determine_wc_order_id( 7 ) );
	}

	/**
	 * GIVEN a checkout request without ppcp-resume-order
	 * WHEN the WC order id is determined
	 * THEN the initial value is returned
	 */
	public function test_determine_wc_order_id_keeps_value_without_resume_request(): void
	{
		$sut = $this->create_sut( '55', 55, $this->wc_order( 'pending' ) );

		$this->assertSame( 7, $sut->determine_wc_order_id( 7 ) );
	}

	/**
	 * GIVEN a checkout request with ppcp-resume-order
	 * WHEN there is no PayPal order in the session
	 * THEN the initial value is returned
	 */
	public function test_determine_wc_order_id_keeps_value_without_paypal_order_in_session(): void
	{
		$_REQUEST['ppcp-resume-order'] = '55';

		$session_handler = Mockery::mock( SessionHandler::class );
		$session_handler->shouldReceive( 'order' )->andReturn( null );

		$sut = $this->create_sut( '55', 55, $this->wc_order( 'pending' ), $session_handler );

		$this->assertSame( 7, $sut->determine_wc_order_id( 7 ) );
	}

	/**
	 * @param string $status          The WC order status.
	 * @param string $paypal_order_id The PayPal order ID stored in the WC order meta.
	 */
	private function wc_order( string $status, string $paypal_order_id = self::PAYPAL_ORDER_ID ): WC_Order
	{
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'needs_payment' )->andReturn( in_array( $status, array( 'pending', 'failed' ), true ) );
		$wc_order->shouldReceive( 'is_paid' )->andReturn( in_array( $status, array( 'processing', 'completed' ), true ) );
		$wc_order->shouldReceive( 'has_status' )->andReturnUsing(
			static function ( $statuses ) use ( $status ): bool {
				return in_array( $status, (array) $statuses, true );
			}
		);
		$wc_order->shouldReceive( 'get_meta' )->with( PayPalGateway::ORDER_ID_META_KEY )->andReturn( $paypal_order_id );
		$wc_order->shouldReceive( 'get_checkout_order_received_url' )->andReturn( self::ORDER_RECEIVED_URL );

		return $wc_order;
	}

	/**
	 * @param string $custom_id The custom ID of the PayPal order in the session.
	 * @return SessionHandler&Mockery\MockInterface
	 */
	private function session_handler( string $custom_id )
	{
		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'custom_id' )->andReturn( $custom_id );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'id' )->andReturn( self::PAYPAL_ORDER_ID );
		$order->shouldReceive( 'purchase_units' )->andReturn( array( $purchase_unit ) );

		$session_handler = Mockery::mock( SessionHandler::class );
		$session_handler->shouldReceive( 'order' )->andReturn( $order );

		return $session_handler;
	}

	/**
	 * @param string              $custom_id        The custom ID of the PayPal order in the session.
	 * @param int                 $awaiting_payment The WC session's order_awaiting_payment.
	 * @param WC_Order|false      $wc_order         What wc_get_order() returns.
	 * @param SessionHandler|null $session_handler  The session handler, or null for a default one.
	 */
	private function create_sut(
		string $custom_id,
		int $awaiting_payment,
		$wc_order,
		?SessionHandler $session_handler = null
	): EarlyOrderHandler {
		$wc_session = Mockery::mock();
		$wc_session->shouldReceive( 'get' )->with( 'order_awaiting_payment' )->andReturn( $awaiting_payment );
		when( 'WC' )->justReturn( (object) array( 'session' => $wc_session ) );
		when( 'wc_get_order' )->justReturn( $wc_order );

		return new EarlyOrderHandler(
			true,
			Mockery::mock( OrderProcessor::class ),
			$session_handler ?? $this->session_handler( $custom_id )
		);
	}
}
