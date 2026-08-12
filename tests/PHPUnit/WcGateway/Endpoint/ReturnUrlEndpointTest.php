<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ReturnUrlSecret;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Webhooks\CustomIds;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Sentinel thrown by the wp_safe_redirect() stub so a test can observe the
 * redirect target without letting handle_request() reach the real exit().
 */
class ReturnUrlRedirected extends RuntimeException {
	public string $url;

	public function __construct( string $url ) {
		parent::__construct( 'redirected to: ' . $url );
		$this->url = $url;
	}
}

/**
 * @covers \WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint
 */
class ReturnUrlEndpointTest extends TestCase {

	private $gateway;
	private $order_endpoint;
	private $session_handler;
	private $return_url_secret;
	private LoggerInterface $logger;
	private ReturnUrlEndpoint $sut;

	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 60 * 60 * 24 );
		}

		$this->gateway = Mockery::mock( PayPalGateway::class );
		$this->gateway->id = 'ppcp-gateway';

		$this->order_endpoint   = Mockery::mock( OrderEndpoint::class );
		$this->session_handler  = Mockery::mock( SessionHandler::class );
		$this->return_url_secret = Mockery::mock( ReturnUrlSecret::class );
		$this->logger            = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		$this->sut = new ReturnUrlEndpoint(
			$this->gateway,
			$this->order_endpoint,
			$this->session_handler,
			$this->logger,
			$this->return_url_secret
		);

		when( 'apply_filters' )->alias( function ( string $hook, ...$args ) {
			return $args[0] ?? null;
		} );
		when( 'wc_get_checkout_url' )->justReturn( 'https://example.com/checkout' );
		when( 'add_filter' )->justReturn( true );
		when( 'wp_safe_redirect' )->alias( function ( string $url ): void {
			throw new ReturnUrlRedirected( $url );
		} );
	}

	public function tearDown(): void {
		unset( $_GET['token'], $_GET['ppcp_return_nonce'] );
		parent::tearDown();
	}

	private function make_order( string $id, string $status, string $custom_id ): Order {
		$order_status = Mockery::mock( OrderStatus::class );
		$order_status->shouldReceive( 'is' )->andReturnUsing( static function ( string $candidate ) use ( $status ): bool {
			return $candidate === $status;
		} );

		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'custom_id' )->andReturn( $custom_id );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'id' )->andReturn( $id );
		$order->shouldReceive( 'status' )->andReturn( $order_status );
		$order->shouldReceive( 'purchase_units' )->andReturn( array( $purchase_unit ) );

		return $order;
	}

	private function make_wc_order( int $customer_id, string $payment_method = 'ppcp-gateway' ): WC_Order {
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_payment_method' )->andReturn( $payment_method );
		$wc_order->shouldReceive( 'get_customer_id' )->andReturn( $customer_id );

		return $wc_order;
	}

	/**
	 * GIVEN a PayPal order that is still CREATED (needs 3DS completion) and a request
	 *       that carries no valid proof of origin (no nonce, no session, no login)
	 * WHEN handle_request() processes the return
	 * THEN order_endpoint->capture() is never called
	 * AND session_handler->replace_order() is never called
	 * AND the gateway never processes the payment
	 */
	public function test_authorization_runs_before_capture_and_process_payment(): void {
		// Arrange
		$_GET['token'] = 'TOKEN-ORDERING';
		unset( $_GET['ppcp_return_nonce'] );

		$order = $this->make_order( 'TOKEN-ORDERING', OrderStatus::CREATED, '55' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-ORDERING' )->andReturn( $order );
		$this->order_endpoint->shouldReceive( 'capture' )->never();

		$wc_order = $this->make_wc_order( 0 );
		expect( 'wc_get_order' )->with( 55 )->andReturn( $wc_order );

		$this->return_url_secret->shouldReceive( 'verify' )->with( 'TOKEN-ORDERING', '' )->andReturn( false );
		$this->return_url_secret->shouldReceive( 'has_secret' )->with( 'TOKEN-ORDERING' )->andReturn( true );
		$this->return_url_secret->shouldReceive( 'consume' )->never();

		$this->session_handler->shouldReceive( 'order' )->andReturn( null );
		$this->session_handler->shouldReceive( 'replace_order' )->never();

		when( 'get_current_user_id' )->justReturn( 0 );
		expect( 'wc_add_notice' )->once();

		$this->gateway->shouldReceive( 'process_payment' )->never();

		// When
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			// Then
			$this->assertSame( 'https://example.com/checkout', $redirected->url );
		}
	}

	/**
	 * GIVEN a request that carries a ppcp_return_nonce equal to the secret bound to the token
	 *       with no WC session and no logged-in user
	 * WHEN handle_request() processes the return
	 * THEN the secret is verified against the token
	 * AND process_payment() is called once for the resolved WC order
	 * AND the request is redirected to the success URL
	 */
	public function test_accepts_request_with_valid_nonce_without_session_or_login(): void {
		// Arrange
		$_GET['token']              = 'TOKEN-NONCE-OK';
		$_GET['ppcp_return_nonce']  = 'NONCE-VALID';

		$order = $this->make_order( 'TOKEN-NONCE-OK', OrderStatus::APPROVED, '77' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-NONCE-OK' )->andReturn( $order );

		$wc_order = $this->make_wc_order( 0 );
		expect( 'wc_get_order' )->with( 77 )->andReturn( $wc_order );

		$this->return_url_secret->shouldReceive( 'verify' )
			->once()
			->with( 'TOKEN-NONCE-OK', 'NONCE-VALID' )
			->andReturn( true );
		$this->return_url_secret->shouldReceive( 'consume' )->with( 'TOKEN-NONCE-OK' );

		$this->session_handler->shouldReceive( 'replace_order' );

		$this->gateway->shouldReceive( 'process_payment' )
			->once()
			->with( 77 )
			->andReturn( array( 'result' => 'success', 'redirect' => 'https://www.paypal.com/success' ) );

		// When
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			// Then
			$this->assertSame( 'https://www.paypal.com/success', $redirected->url );
		}
	}

	/**
	 * GIVEN a token that has a secret bound to it, and a request whose ppcp_return_nonce
	 *       does not match that secret
	 * WHEN handle_request() processes the return
	 * THEN capture(), replace_order() and process_payment() are never called
	 * AND the logger receives a warning
	 * AND the notice shown to the requester names neither the WC order id, the token,
	 *     nor any ownership wording
	 */
	public function test_refuses_request_with_wrong_nonce_for_bound_token(): void {
		// Arrange
		$_GET['token']             = 'TOKEN-WRONG-NONCE';
		$_GET['ppcp_return_nonce'] = 'WRONG-NONCE';

		$order = $this->make_order( 'TOKEN-WRONG-NONCE', OrderStatus::APPROVED, '88' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-WRONG-NONCE' )->andReturn( $order );
		$this->order_endpoint->shouldReceive( 'capture' )->never();

		$wc_order = $this->make_wc_order( 0 );
		expect( 'wc_get_order' )->with( 88 )->andReturn( $wc_order );

		$this->return_url_secret->shouldReceive( 'verify' )
			->once()
			->with( 'TOKEN-WRONG-NONCE', 'WRONG-NONCE' )
			->andReturn( false );
		$this->return_url_secret->shouldReceive( 'has_secret' )->with( 'TOKEN-WRONG-NONCE' )->andReturn( true );

		$this->session_handler->shouldReceive( 'order' )->andReturn( null );
		$this->session_handler->shouldReceive( 'replace_order' )->never();

		when( 'get_current_user_id' )->justReturn( 0 );

		$this->gateway->shouldReceive( 'process_payment' )->never();

		$this->logger->shouldReceive( 'warning' )->once();

		expect( 'wc_add_notice' )
			->once()
			->with(
				Mockery::on( function ( string $message ): bool {
					$lowered = strtolower( $message );
					return strpos( $message, '88' ) === false
						&& strpos( $message, 'TOKEN-WRONG-NONCE' ) === false
						&& strpos( $lowered, 'owner' ) === false
						&& strpos( $lowered, 'belongs' ) === false
						&& strpos( $lowered, 'your order' ) === false;
				} ),
				'error'
			);

		// When / Then
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			$this->assertSame( 'https://example.com/checkout', $redirected->url );
		}
	}

	/**
	 * GIVEN a token that has a secret bound to it, and a request that carries no
	 *       ppcp_return_nonce at all
	 * WHEN handle_request() processes the return
	 * THEN capture(), replace_order() and process_payment() are never called
	 * AND the logger receives a warning
	 * AND the notice shown to the requester names neither the WC order id, the token,
	 *     nor any ownership wording
	 */
	public function test_refuses_request_with_absent_nonce_for_bound_token(): void {
		// Arrange
		$_GET['token'] = 'TOKEN-ABSENT-NONCE';
		unset( $_GET['ppcp_return_nonce'] );

		$order = $this->make_order( 'TOKEN-ABSENT-NONCE', OrderStatus::APPROVED, '89' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-ABSENT-NONCE' )->andReturn( $order );
		$this->order_endpoint->shouldReceive( 'capture' )->never();

		$wc_order = $this->make_wc_order( 0 );
		expect( 'wc_get_order' )->with( 89 )->andReturn( $wc_order );

		$this->return_url_secret->shouldReceive( 'verify' )
			->once()
			->with( 'TOKEN-ABSENT-NONCE', '' )
			->andReturn( false );
		$this->return_url_secret->shouldReceive( 'has_secret' )->with( 'TOKEN-ABSENT-NONCE' )->andReturn( true );

		$this->session_handler->shouldReceive( 'order' )->andReturn( null );
		$this->session_handler->shouldReceive( 'replace_order' )->never();

		when( 'get_current_user_id' )->justReturn( 0 );

		$this->gateway->shouldReceive( 'process_payment' )->never();

		$this->logger->shouldReceive( 'warning' )->once();

		expect( 'wc_add_notice' )
			->once()
			->with(
				Mockery::on( function ( string $message ): bool {
					$lowered = strtolower( $message );
					return strpos( $message, '89' ) === false
						&& strpos( $message, 'TOKEN-ABSENT-NONCE' ) === false
						&& strpos( $lowered, 'owner' ) === false
						&& strpos( $lowered, 'belongs' ) === false
						&& strpos( $lowered, 'your order' ) === false;
				} ),
				'error'
			);

		// When / Then
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			$this->assertSame( 'https://example.com/checkout', $redirected->url );
		}
	}

	/**
	 * GIVEN a request that is accepted through a valid nonce and process_payment() succeeds
	 * WHEN handle_request() finishes processing the return
	 * THEN ReturnUrlSecret::consume() deletes the secret bound to the token
	 */
	public function test_consumes_secret_after_successful_process_payment(): void {
		// Arrange
		$_GET['token']             = 'TOKEN-CONSUME';
		$_GET['ppcp_return_nonce'] = 'NONCE-CONSUME';

		$order = $this->make_order( 'TOKEN-CONSUME', OrderStatus::APPROVED, '99' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-CONSUME' )->andReturn( $order );

		$wc_order = $this->make_wc_order( 0 );
		expect( 'wc_get_order' )->with( 99 )->andReturn( $wc_order );

		$this->return_url_secret->shouldReceive( 'verify' )
			->with( 'TOKEN-CONSUME', 'NONCE-CONSUME' )
			->andReturn( true );
		$this->return_url_secret->shouldReceive( 'consume' )
			->once()
			->with( 'TOKEN-CONSUME' );

		$this->session_handler->shouldReceive( 'replace_order' );

		$this->gateway->shouldReceive( 'process_payment' )
			->once()
			->with( 99 )
			->andReturn( array( 'result' => 'success', 'redirect' => 'https://www.paypal.com/ok' ) );

		// When
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			// Then
			$this->assertSame( 'https://www.paypal.com/ok', $redirected->url );
		}
	}

	/**
	 * GIVEN a secret that was already consumed by an earlier successful return (verify()
	 *       now false, has_secret() now false) and the update grace window closed
	 * WHEN a second request replays the same nonce for the same token
	 * THEN capture(), replace_order() and process_payment() are never called
	 * AND the notice shown to the requester names neither the WC order id, the token,
	 *     nor any ownership wording
	 */
	public function test_refuses_replay_of_consumed_nonce(): void {
		// Arrange
		$_GET['token']             = 'TOKEN-REPLAY';
		$_GET['ppcp_return_nonce'] = 'NONCE-ALREADY-USED';

		$order = $this->make_order( 'TOKEN-REPLAY', OrderStatus::APPROVED, '100' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-REPLAY' )->andReturn( $order );
		$this->order_endpoint->shouldReceive( 'capture' )->never();

		$wc_order = $this->make_wc_order( 0 );
		expect( 'wc_get_order' )->with( 100 )->andReturn( $wc_order );

		$this->return_url_secret->shouldReceive( 'verify' )
			->with( 'TOKEN-REPLAY', 'NONCE-ALREADY-USED' )
			->andReturn( false );
		$this->return_url_secret->shouldReceive( 'has_secret' )->with( 'TOKEN-REPLAY' )->andReturn( false );
		$this->return_url_secret->shouldReceive( 'consume' )->never();

		$this->session_handler->shouldReceive( 'order' )->andReturn( null );
		$this->session_handler->shouldReceive( 'replace_order' )->never();

		when( 'get_current_user_id' )->justReturn( 0 );
		when( 'get_option' )->justReturn( time() - ( DAY_IN_SECONDS * 2 ) );

		$this->gateway->shouldReceive( 'process_payment' )->never();

		expect( 'wc_add_notice' )
			->once()
			->with(
				Mockery::on( function ( string $message ): bool {
					$lowered = strtolower( $message );
					return strpos( $message, '100' ) === false
						&& strpos( $message, 'TOKEN-REPLAY' ) === false
						&& strpos( $lowered, 'owner' ) === false
						&& strpos( $lowered, 'belongs' ) === false
						&& strpos( $lowered, 'your order' ) === false;
				} ),
				'error'
			);

		// When / Then
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			$this->assertSame( 'https://example.com/checkout', $redirected->url );
		}
	}

	/**
	 * GIVEN a token that has no bound secret (the order was made before the fix shipped)
	 *       while the option ppcp_return_url_binding_since places "now" inside the
	 *       one-day grace window
	 * WHEN handle_request() processes the return, with no nonce, no session and no login
	 * THEN processing proceeds
	 * AND the logger receives a warning naming the token
	 */
	public function test_accepts_unbound_token_inside_grace_window_and_logs_warning(): void {
		// Arrange
		$_GET['token'] = 'TOKEN-GRACE-OPEN';
		unset( $_GET['ppcp_return_nonce'] );

		$order = $this->make_order( 'TOKEN-GRACE-OPEN', OrderStatus::APPROVED, '111' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-GRACE-OPEN' )->andReturn( $order );

		$wc_order = $this->make_wc_order( 0 );
		expect( 'wc_get_order' )->with( 111 )->andReturn( $wc_order );

		$this->return_url_secret->shouldReceive( 'verify' )->with( 'TOKEN-GRACE-OPEN', '' )->andReturn( false );
		$this->return_url_secret->shouldReceive( 'has_secret' )
			->once()
			->with( 'TOKEN-GRACE-OPEN' )
			->andReturn( false );
		$this->return_url_secret->shouldReceive( 'consume' );

		$this->session_handler->shouldReceive( 'order' )->andReturn( null );
		$this->session_handler->shouldReceive( 'replace_order' );

		when( 'get_current_user_id' )->justReturn( 0 );
		expect( 'get_option' )
			->once()
			->with( 'ppcp_return_url_binding_since', Mockery::any() )
			->andReturn( time() - HOUR_IN_SECONDS );

		$this->gateway->shouldReceive( 'process_payment' )
			->once()
			->with( 111 )
			->andReturn( array( 'result' => 'success', 'redirect' => 'https://www.paypal.com/grace' ) );

		$this->logger->shouldReceive( 'warning' )
			->once()
			->with( Mockery::on( function ( string $message ): bool {
				return strpos( $message, 'TOKEN-GRACE-OPEN' ) !== false;
			} ) );

		// When
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			// Then
			$this->assertSame( 'https://www.paypal.com/grace', $redirected->url );
		}
	}

	/**
	 * GIVEN a token that has no bound secret and the option ppcp_return_url_binding_since
	 *       places "now" outside the one-day grace window, with no session and no login
	 * WHEN handle_request() processes the return
	 * THEN capture(), replace_order() and process_payment() are never called
	 * AND the notice shown to the requester names neither the WC order id, the token,
	 *     nor any ownership wording
	 */
	public function test_refuses_unbound_token_after_grace_window(): void {
		// Arrange
		$_GET['token'] = 'TOKEN-GRACE-CLOSED';
		unset( $_GET['ppcp_return_nonce'] );

		$order = $this->make_order( 'TOKEN-GRACE-CLOSED', OrderStatus::APPROVED, '112' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-GRACE-CLOSED' )->andReturn( $order );
		$this->order_endpoint->shouldReceive( 'capture' )->never();

		$wc_order = $this->make_wc_order( 0 );
		expect( 'wc_get_order' )->with( 112 )->andReturn( $wc_order );

		$this->return_url_secret->shouldReceive( 'verify' )->with( 'TOKEN-GRACE-CLOSED', '' )->andReturn( false );
		$this->return_url_secret->shouldReceive( 'has_secret' )
			->once()
			->with( 'TOKEN-GRACE-CLOSED' )
			->andReturn( false );

		$this->session_handler->shouldReceive( 'order' )->andReturn( null );
		$this->session_handler->shouldReceive( 'replace_order' )->never();

		when( 'get_current_user_id' )->justReturn( 0 );
		expect( 'get_option' )
			->once()
			->with( 'ppcp_return_url_binding_since', Mockery::any() )
			->andReturn( time() - ( DAY_IN_SECONDS * 2 ) );

		$this->gateway->shouldReceive( 'process_payment' )->never();

		expect( 'wc_add_notice' )
			->once()
			->with(
				Mockery::on( function ( string $message ): bool {
					$lowered = strtolower( $message );
					return strpos( $message, '112' ) === false
						&& strpos( $message, 'TOKEN-GRACE-CLOSED' ) === false
						&& strpos( $lowered, 'owner' ) === false
						&& strpos( $lowered, 'belongs' ) === false
						&& strpos( $lowered, 'your order' ) === false;
				} ),
				'error'
			);

		// When / Then
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			$this->assertSame( 'https://example.com/checkout', $redirected->url );
		}
	}

	/**
	 * GIVEN the WC session still holds the same PayPal order as the returning token,
	 *       and no ppcp_return_nonce is present
	 * WHEN handle_request() processes the return
	 * THEN session_handler->order() is consulted
	 * AND process_payment() is called once for the resolved WC order
	 */
	public function test_accepts_when_session_holds_the_same_paypal_order(): void {
		// Arrange
		$_GET['token'] = 'TOKEN-SESSION-ORDER';
		unset( $_GET['ppcp_return_nonce'] );

		$order = $this->make_order( 'TOKEN-SESSION-ORDER', OrderStatus::APPROVED, '121' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-SESSION-ORDER' )->andReturn( $order );

		$wc_order = $this->make_wc_order( 0 );
		expect( 'wc_get_order' )->with( 121 )->andReturn( $wc_order );

		$this->return_url_secret->shouldReceive( 'verify' )->with( 'TOKEN-SESSION-ORDER', '' )->andReturn( false );
		$this->return_url_secret->shouldReceive( 'consume' );

		$session_order = Mockery::mock( Order::class );
		$session_order->shouldReceive( 'id' )->andReturn( 'TOKEN-SESSION-ORDER' );

		$this->session_handler->shouldReceive( 'order' )
			->once()
			->andReturn( $session_order );
		$this->session_handler->shouldReceive( 'replace_order' );

		$this->gateway->shouldReceive( 'process_payment' )
			->once()
			->with( 121 )
			->andReturn( array( 'result' => 'success', 'redirect' => 'https://www.paypal.com/session' ) );

		// When
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			// Then
			$this->assertSame( 'https://www.paypal.com/session', $redirected->url );
		}
	}

	/**
	 * GIVEN the custom_id carries the customer-id prefix and its suffix matches the
	 *       WC session customer id, and no ppcp_return_nonce is present
	 * WHEN handle_request() processes the return
	 * NOTE: this custom_id casts to (int) 0, so the handler stays in the continuation
	 *       mode (never resolves a WC order and never calls process_payment())
	 * THEN WC()->session->get_customer_id() is consulted
	 * AND the request is redirected to the plain checkout URL without an error notice
	 * AND process_payment() is never called
	 */
	public function test_accepts_when_custom_id_session_suffix_matches_wc_session(): void {
		// Arrange
		$_GET['token'] = 'TOKEN-CUSTOM-ID-SUFFIX';
		unset( $_GET['ppcp_return_nonce'] );

		$custom_id = CustomIds::CUSTOMER_ID_PREFIX . 'sess-abc';
		$order     = $this->make_order( 'TOKEN-CUSTOM-ID-SUFFIX', OrderStatus::APPROVED, $custom_id );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-CUSTOM-ID-SUFFIX' )->andReturn( $order );

		$this->return_url_secret->shouldReceive( 'verify' )->with( 'TOKEN-CUSTOM-ID-SUFFIX', '' )->andReturn( false );

		$this->session_handler->shouldReceive( 'order' )
			->once()
			->andReturn( null );
		$this->session_handler->shouldReceive( 'replace_order' );

		$wc_session = Mockery::mock( 'WC_Session' );
		$wc_session->shouldReceive( 'get_customer_id' )
			->once()
			->andReturn( 'sess-abc' );
		$woocommerce          = new \stdClass();
		$woocommerce->session = $wc_session;
		when( 'WC' )->justReturn( $woocommerce );

		$this->gateway->shouldReceive( 'process_payment' )->never();
		expect( 'wc_add_notice' )->never();

		// When
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			// Then
			$this->assertSame( 'https://example.com/checkout', $redirected->url );
		}
	}

	/**
	 * GIVEN a logged-in user whose ID equals the customer ID of the WC order,
	 *       and no ppcp_return_nonce is present
	 * WHEN handle_request() processes the return
	 * THEN get_current_user_id() is consulted
	 * AND process_payment() is called once for the resolved WC order
	 */
	public function test_accepts_when_logged_in_user_owns_the_wc_order(): void {
		// Arrange
		$_GET['token'] = 'TOKEN-OWNER';
		unset( $_GET['ppcp_return_nonce'] );

		$order = $this->make_order( 'TOKEN-OWNER', OrderStatus::APPROVED, '131' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-OWNER' )->andReturn( $order );

		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_payment_method' )->andReturn( 'ppcp-gateway' );
		$wc_order->shouldReceive( 'get_customer_id' )->once()->andReturn( 42 );
		expect( 'wc_get_order' )->with( 131 )->andReturn( $wc_order );

		$this->return_url_secret->shouldReceive( 'verify' )->with( 'TOKEN-OWNER', '' )->andReturn( false );
		$this->return_url_secret->shouldReceive( 'consume' );

		$this->session_handler->shouldReceive( 'order' )->andReturn( null );
		$this->session_handler->shouldReceive( 'replace_order' );

		expect( 'get_current_user_id' )->once()->andReturn( 42 );

		$this->gateway->shouldReceive( 'process_payment' )
			->once()
			->with( 131 )
			->andReturn( array( 'result' => 'success', 'redirect' => 'https://www.paypal.com/owner' ) );

		// When
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			// Then
			$this->assertSame( 'https://www.paypal.com/owner', $redirected->url );
		}
	}

	/**
	 * GIVEN a guest (get_current_user_id() is 0) returning for a guest order
	 *       (get_customer_id() is also 0), with no nonce and no matching session
	 * WHEN handle_request() processes the return
	 * THEN the customer proof alone must not authorize the request: 0 does not equal 0
	 *      for ownership purposes, so capture(), replace_order() and process_payment()
	 *      are never called
	 * AND the notice shown to the requester names neither the WC order id, the token,
	 *     nor any ownership wording
	 */
	public function test_does_not_accept_guest_order_on_customer_proof_alone(): void {
		// Arrange
		$_GET['token'] = 'TOKEN-GUEST';
		unset( $_GET['ppcp_return_nonce'] );

		$order = $this->make_order( 'TOKEN-GUEST', OrderStatus::APPROVED, '141' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'TOKEN-GUEST' )->andReturn( $order );
		$this->order_endpoint->shouldReceive( 'capture' )->never();

		$wc_order = $this->make_wc_order( 0 );
		expect( 'wc_get_order' )->with( 141 )->andReturn( $wc_order );

		$this->return_url_secret->shouldReceive( 'verify' )->with( 'TOKEN-GUEST', '' )->andReturn( false );
		$this->return_url_secret->shouldReceive( 'has_secret' )->with( 'TOKEN-GUEST' )->andReturn( true );

		$this->session_handler->shouldReceive( 'order' )->andReturn( null );
		$this->session_handler->shouldReceive( 'replace_order' )->never();

		when( 'get_current_user_id' )->justReturn( 0 );

		$this->gateway->shouldReceive( 'process_payment' )->never();

		expect( 'wc_add_notice' )
			->once()
			->with(
				Mockery::on( function ( string $message ): bool {
					$lowered = strtolower( $message );
					return strpos( $message, '141' ) === false
						&& strpos( $message, 'TOKEN-GUEST' ) === false
						&& strpos( $lowered, 'owner' ) === false
						&& strpos( $lowered, 'belongs' ) === false
						&& strpos( $lowered, 'your order' ) === false;
				} ),
				'error'
			);

		// When / Then
		try {
			$this->sut->handle_request();
			$this->fail( 'Expected handle_request() to redirect.' );
		} catch ( ReturnUrlRedirected $redirected ) {
			$this->assertSame( 'https://example.com/checkout', $redirected->url );
		}
	}
}
