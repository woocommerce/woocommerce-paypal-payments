<?php

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use Mockery;
use WC_Order;
use WC_Session_Handler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\WcGateway\Endpoint\CompletedReturnSession
 */
class CompletedReturnSessionTest extends TestCase {

	private const SESSION_KEY   = 'ppcp_completed_return';
	private const PAYPAL_ID     = 'PAYPAL-ORDER-1';
	private const WC_ORDER_ID   = 42;
	private const NOW           = 1700000000;
	private const FIVE_MINUTES  = 300;

	private $session;

	/** @var array<int, WC_Order> */
	private array $orders = array();

	private int $now = self::NOW;

	private CompletedReturnSession $sut;

	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		$this->session = $this->create_session( true );
		$this->sut     = new CompletedReturnSession();

		when( 'time' )->alias(
			function (): int {
				return $this->now;
			}
		);
		when( 'wc_get_is_paid_statuses' )->justReturn( array( 'processing', 'completed' ) );
		when( 'wc_get_order' )->alias(
			function ( $id ) {
				return $this->orders[ $id ] ?? false;
			}
		);
		when( 'WC' )->alias(
			function (): object {
				return (object) array( 'session' => $this->session );
			}
		);
	}

	private function create_session( bool $has_session ): WC_Session_Handler {
		return new class( $has_session ) extends WC_Session_Handler {
			public $data       = array();
			public $cookie_set = false;
			private $has;

			public function __construct( bool $has ) {
				$this->has = $has;
			}

			public function get( $key, $default = null ) {
				return $this->data[ $key ] ?? $default;
			}

			public function set( $key, $value ): void {
				$this->data[ $key ] = $value;
			}

			public function has_session(): bool {
				return $this->has;
			}

			public function set_customer_session_cookie( $set ): void {
				$this->cookie_set = (bool) $set;
				$this->has        = true;
			}
		};
	}

	private function create_wc_order( string $status, string $paypal_id = self::PAYPAL_ID ): WC_Order {
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'has_status' )->andReturnUsing(
			static function ( $statuses ) use ( $status ): bool {
				return in_array( $status, (array) $statuses, true );
			}
		);
		$wc_order->allows( 'get_meta' )->with( PayPalGateway::ORDER_ID_META_KEY )->andReturn( $paypal_id );

		$this->orders[ self::WC_ORDER_ID ] = $wc_order;

		return $wc_order;
	}

	/**
	 * GIVEN a WC order that is paid and carries the returned PayPal order ID
	 * WHEN the successful return is remembered
	 * THEN the WC session holds the PayPal order ID, the WC order ID and an expiry 5 minutes ahead
	 */
	public function test_remembers_settled_return_in_session_for_five_minutes(): void {
		$this->create_wc_order( 'processing' );

		$this->sut->remember( self::PAYPAL_ID, self::WC_ORDER_ID );

		$this->assertSame(
			array(
				'paypal_order_id' => self::PAYPAL_ID,
				'wc_order_id'     => self::WC_ORDER_ID,
				'expires'         => self::NOW + self::FIVE_MINUTES,
			),
			$this->session->data[ self::SESSION_KEY ]
		);
	}

	/**
	 * GIVEN a WC order in a given status after the return
	 * WHEN the successful return is remembered
	 * THEN the session holds a marker only for paid and on-hold orders
	 *
	 * @dataProvider order_status_provider
	 */
	public function test_remembers_return_only_for_paid_or_on_hold_orders( string $status, bool $is_remembered ): void {
		$this->create_wc_order( $status );

		$this->sut->remember( self::PAYPAL_ID, self::WC_ORDER_ID );

		$this->assertSame( $is_remembered, isset( $this->session->data[ self::SESSION_KEY ] ) );
	}

	public function order_status_provider(): array {
		return array(
			'processing order is remembered' => array( 'processing', true ),
			'completed order is remembered'  => array( 'completed', true ),
			'on-hold order is remembered'    => array( 'on-hold', true ),
			'pending order is not remembered'   => array( 'pending', false ),
			'failed order is not remembered'    => array( 'failed', false ),
			'canceled order is not remembered'  => array( 'cancelled', false ),
		);
	}

	/**
	 * GIVEN a paid WC order whose PayPal order ID differs from the returned one
	 * WHEN the successful return is remembered
	 * THEN the session holds no marker
	 */
	public function test_does_not_remember_return_when_order_has_other_paypal_order_id(): void {
		$this->create_wc_order( 'processing', 'PAYPAL-ORDER-OTHER' );

		$this->sut->remember( self::PAYPAL_ID, self::WC_ORDER_ID );

		$this->assertArrayNotHasKey( self::SESSION_KEY, $this->session->data );
	}

	/**
	 * GIVEN a successful return in a browser with no WC session cookie
	 * WHEN the return is remembered
	 * THEN the session cookie is requested
	 * AND the session holds the marker
	 */
	public function test_remembers_return_and_sets_session_cookie_when_browser_has_none(): void {
		$this->session = $this->create_session( false );
		$this->create_wc_order( 'processing' );

		$this->sut->remember( self::PAYPAL_ID, self::WC_ORDER_ID );

		$this->assertTrue( $this->session->cookie_set );
		$this->assertSame( self::WC_ORDER_ID, $this->session->data[ self::SESSION_KEY ]['wc_order_id'] );
	}

	/**
	 * GIVEN a successful return in a browser that already has a WC session cookie
	 * WHEN the return is remembered
	 * THEN no new session cookie is requested
	 */
	public function test_keeps_existing_session_cookie_when_remembering_return(): void {
		$this->create_wc_order( 'processing' );

		$this->sut->remember( self::PAYPAL_ID, self::WC_ORDER_ID );

		$this->assertFalse( $this->session->cookie_set );
	}

	/**
	 * GIVEN a remembered return
	 * WHEN the same PayPal order ID arrives within 5 minutes, including the last second
	 * THEN the WC order of that return is given back
	 *
	 * @dataProvider unexpired_offset_provider
	 */
	public function test_gives_back_order_for_same_token_before_marker_expires( int $seconds_later ): void {
		$wc_order = $this->create_wc_order( 'processing' );
		$this->sut->remember( self::PAYPAL_ID, self::WC_ORDER_ID );
		$this->now = self::NOW + $seconds_later;

		$this->assertSame( $wc_order, $this->sut->remembered_order_for( self::PAYPAL_ID ) );
	}

	public function unexpired_offset_provider(): array {
		return array(
			'right after the return'   => array( 0 ),
			'one minute later'         => array( 60 ),
			'on the last second'       => array( self::FIVE_MINUTES ),
		);
	}

	/**
	 * GIVEN a remembered return
	 * WHEN the same PayPal order ID arrives after 5 minutes
	 * THEN no order is given back
	 */
	public function test_gives_back_no_order_after_marker_expired(): void {
		$this->create_wc_order( 'processing' );
		$this->sut->remember( self::PAYPAL_ID, self::WC_ORDER_ID );
		$this->now = self::NOW + self::FIVE_MINUTES + 1;

		$this->assertNull( $this->sut->remembered_order_for( self::PAYPAL_ID ) );
	}

	/**
	 * GIVEN a remembered return
	 * WHEN a different PayPal order ID arrives
	 * THEN no order is given back
	 */
	public function test_gives_back_no_order_for_different_token(): void {
		$this->create_wc_order( 'processing' );
		$this->sut->remember( self::PAYPAL_ID, self::WC_ORDER_ID );

		$this->assertNull( $this->sut->remembered_order_for( 'PAYPAL-ORDER-OTHER' ) );
	}

	/**
	 * GIVEN a session that holds no remembered return (another session)
	 * WHEN a PayPal order ID arrives
	 * THEN no order is given back
	 */
	public function test_gives_back_no_order_when_session_holds_no_marker(): void {
		$this->create_wc_order( 'processing' );

		$this->assertNull( $this->sut->remembered_order_for( self::PAYPAL_ID ) );
	}
}
