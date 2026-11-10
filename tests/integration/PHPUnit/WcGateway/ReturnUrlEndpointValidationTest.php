<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\WcGateway;

use Exception;
use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Marker exception used to halt ReturnUrlEndpoint::handle_request() before it
 * reaches exit(). wp_safe_redirect() applies the 'wp_redirect' filter to the
 * target location before calling exit(); throwing from that filter callback
 * unwinds the call stack safely, without killing the PHPUnit process and
 * without needing @runInSeparateProcess (which would make the assertions
 * below unverifiable).
 */
class ReturnUrlEndpointHaltSignal extends Exception {
}

/**
 * Uses the `ppcp-` ID prefix of this plugin. process_payment() marks the order
 * paid, so each call shows as a paid order.
 */
class ReturnUrlEndpointTestGateway extends \WC_Payment_Gateway {

	const ID = 'ppcp-return-url-test-gateway';

	public function __construct() {
		$this->id = self::ID;
		$this->init_settings();
		$this->enabled = $this->get_option( 'enabled', 'no' );
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$order->payment_complete();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}
}

/**
 * @group transactions
 *
 * End-to-end proof that ReturnUrlEndpoint::handle_request() performs its
 * authorization test (ppcp_return_nonce vs. the secret bound to the PayPal
 * order token) before it captures, replaces the session order, or processes
 * payment
 */
class ReturnUrlEndpointValidationTest extends IntegrationMockedTestCase {

	/**
	 * @var callable|null Filter registered per-test to intercept wp_safe_redirect(); removed in tearDown as a safety net.
	 */
	private $redirect_filter = null;

	/**
	 * @var array<string, mixed> Stored gateway settings by gateway ID (false when absent), restored in tearDown.
	 */
	private array $original_gateway_settings = array();

	/**
	 * These tests model an unauthenticated third party replaying/guessing a
	 * return URL. wp_set_current_user( 0 ) forces every test in this class to
	 * start logged out, regardless of what an earlier test class in the same
	 * PHPUnit process left behind (e.g. a class that logs a user in and never
	 * logs them out again). Without this, Proof C in
	 * ReturnUrlEndpoint::is_authorized_return() ("the logged-in user owns the
	 * WC order") can spuriously succeed and the refusal tests below stop
	 * testing refusal.
	 */
	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( 0 );
		wc_clear_notices();
	}

	public function tearDown(): void {
		wc_clear_notices();

		if ( null !== $this->redirect_filter ) {
			remove_filter( 'wp_redirect', $this->redirect_filter );
			$this->redirect_filter = null;
		}

		unset( $_GET['token'], $_GET['ppcp_return_nonce'], $_GET['ppcp_resume_wc_order'], $_GET['ppcp_resume_nonce'] );

		foreach ( $this->original_gateway_settings as $gateway_id => $original ) {
			$option_name = 'woocommerce_' . $gateway_id . '_settings';
			if ( false === $original ) {
				delete_option( $option_name );
			} else {
				update_option( $option_name, $original );
			}

			$registered = WC()->payment_gateways()->payment_gateways();
			if ( isset( $registered[ $gateway_id ] ) ) {
				$registered[ $gateway_id ]->init_settings();
			}
		}
		$this->original_gateway_settings = array();

		unset( WC()->payment_gateways()->payment_gateways[ ReturnUrlEndpointTestGateway::ID ] );

		// Reset so this class does not leak an authenticated current user into
		// classes that run after it in the same PHPUnit process.
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * GIVEN a PayPal order token that has a secret bound to it (the buyer's flow
	 *       already went through OrderEndpoint::create()) and requires 3DS completion
	 * AND the request carries no ppcp_return_nonce, or a wrong one
	 * WHEN ReturnUrlEndpoint::handle_request() handles the return
	 * THEN OrderEndpoint::capture() is never called
	 * AND SessionHandler::replace_order() is never called
	 * AND the payment gateway's process_payment() is never called
	 * AND the WC order's transaction id is still empty and its status is unchanged
	 *
	 * @scenario Proves acceptance criterion 1: the authorization test must complete
	 *           before handle_request() calls order_endpoint->capture() at
	 *           ReturnUrlEndpoint.php:270. Today's code performs no such test, so
	 *           capture() runs unconditionally for a CREATED-status order and the
	 *           shouldNotReceive('capture') expectation is violated.
	 * @dataProvider invalid_nonce_provider
	 */
	public function test_refuses_and_never_captures_when_returning_order_needs_3ds_and_nonce_is_invalid( ?string $provided_nonce ): void {
		// Arrange
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			PayPalGateway::ID,
			array( 'simple' ),
			array(),
			false
		);
		$status_before      = $order->get_status();
		$transaction_before = $order->get_transaction_id();

		// The requester must be neither the order's owner (Proof C) nor the
		// session that created the order (Proof B), otherwise the request is
		// authorized on a proof other than the nonce and this test would stop
		// being a refusal test.
		$this->assertSame( 0, get_current_user_id(), 'Requester must be logged out.' );
		$this->assertNotSame(
			get_current_user_id(),
			$order->get_customer_id(),
			'Requester must not be the order owner.'
		);

		$token = 'PP-RETURN-3DS-' . uniqid();
		set_transient( 'ppcp_ru_' . $token, 'the-real-bound-secret-' . uniqid(), DAY_IN_SECONDS );

		$_GET['token'] = $token;
		if ( null === $provided_nonce ) {
			unset( $_GET['ppcp_return_nonce'] );
		} else {
			$_GET['ppcp_return_nonce'] = $provided_nonce;
		}

		$paypal_order          = $this->create_paypal_order_double( OrderStatus::CREATED, (string) $order->get_id() );
		$captured_if_leaked    = $this->create_captured_paypal_order_double( (string) $order->get_id() );

		$order_endpoint = Mockery::mock( OrderEndpoint::class )->shouldIgnoreMissing();
		$order_endpoint->shouldReceive( 'order' )->with( $token )->andReturn( $paypal_order );
		// If today's unfixed code calls capture() despite the invalid proof, let it
		// return a well-formed captured order instead of null, so the assertion
		// failure below comes from the violated expectation, not an unrelated crash.
		$order_endpoint->shouldNotReceive( 'capture' )->andReturn( $captured_if_leaked );

		$session_handler = Mockery::mock( SessionHandler::class )->shouldIgnoreMissing();
		$session_handler->shouldNotReceive( 'replace_order' );

		$gateway     = Mockery::mock( PayPalGateway::class );
		$gateway->id = PayPalGateway::ID;
		$gateway->shouldNotReceive( 'process_payment' );

		$endpoint = new ReturnUrlEndpoint(
			$gateway,
			$order_endpoint,
			$session_handler,
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing()
		);

		// When
		$this->invoke_and_halt_before_exit( $endpoint );

		delete_transient( 'ppcp_ru_' . $token );

		// Then
		$refreshed = wc_get_order( $order->get_id() );
		$this->assertSame( $status_before, $refreshed->get_status() );
		$this->assertSame( $transaction_before, $refreshed->get_transaction_id() );
		$this->assertEmpty( $refreshed->get_transaction_id() );
	}

	public function invalid_nonce_provider(): array {
		return array(
			'no nonce supplied'    => array( null ),
			'wrong nonce supplied' => array( 'unexpected-value' ),
		);
	}

	/**
	 * GIVEN a PayPal order token that has a secret bound to it and is already APPROVED
	 *       (no 3DS completion needed, so the request would otherwise reach
	 *       session_handler->replace_order() and then process_payment())
	 * AND the request carries no ppcp_return_nonce, or a wrong one
	 * WHEN ReturnUrlEndpoint::handle_request() handles the return
	 * THEN SessionHandler::replace_order() is never called
	 * AND the payment gateway's process_payment() is never called
	 * AND the WC order's transaction id is still empty and its status is unchanged
	 *
	 * @dataProvider invalid_nonce_provider
	 */
	public function test_refuses_and_never_proceeds_when_approved_order_return_has_invalid_nonce( ?string $provided_nonce ): void {
		// Arrange
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			PayPalGateway::ID,
			array( 'simple' ),
			array(),
			false
		);
		$status_before      = $order->get_status();
		$transaction_before = $order->get_transaction_id();

		// The requester must be neither the order's owner (Proof C) nor the
		// session that created the order (Proof B), otherwise the request is
		// authorized on a proof other than the nonce and this test would stop
		// being a refusal test.
		$this->assertSame( 0, get_current_user_id(), 'Requester must be logged out.' );
		$this->assertNotSame(
			get_current_user_id(),
			$order->get_customer_id(),
			'Requester must not be the order owner.'
		);

		$token = 'PP-RETURN-APPROVED-' . uniqid();
		set_transient( 'ppcp_ru_' . $token, 'the-real-bound-secret-' . uniqid(), DAY_IN_SECONDS );

		$_GET['token'] = $token;
		if ( null === $provided_nonce ) {
			unset( $_GET['ppcp_return_nonce'] );
		} else {
			$_GET['ppcp_return_nonce'] = $provided_nonce;
		}

		$paypal_order = $this->create_paypal_order_double( OrderStatus::APPROVED, (string) $order->get_id() );

		$order_endpoint = Mockery::mock( OrderEndpoint::class )->shouldIgnoreMissing();
		$order_endpoint->shouldReceive( 'order' )->with( $token )->andReturn( $paypal_order );
		$order_endpoint->shouldNotReceive( 'capture' );

		$session_handler = Mockery::mock( SessionHandler::class )->shouldIgnoreMissing();
		$session_handler->shouldNotReceive( 'replace_order' );

		$gateway     = Mockery::mock( PayPalGateway::class );
		$gateway->id = PayPalGateway::ID;
		$gateway->shouldNotReceive( 'process_payment' );

		$endpoint = new ReturnUrlEndpoint(
			$gateway,
			$order_endpoint,
			$session_handler,
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing()
		);

		// When
		$this->invoke_and_halt_before_exit( $endpoint );

		delete_transient( 'ppcp_ru_' . $token );

		// Then
		$refreshed = wc_get_order( $order->get_id() );
		$this->assertSame( $status_before, $refreshed->get_status() );
		$this->assertSame( $transaction_before, $refreshed->get_transaction_id() );
		$this->assertEmpty( $refreshed->get_transaction_id() );
	}

	/**
	 * GIVEN a PayPal order token that has a secret bound to it and is APPROVED
	 * AND the request carries the exact ppcp_return_nonce that was bound to that token
	 * WHEN ReturnUrlEndpoint::handle_request() handles the return, through the real
	 *      PayPalGateway and OrderProcessor wired via the container
	 * THEN the WC order actually gets captured: its status becomes "processing"
	 * AND its transaction id is populated from the PayPal capture
	 *
	 */
	public function test_accepts_return_and_processes_payment_when_nonce_matches_bound_secret(): void {
		// Arrange
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			PayPalGateway::ID,
			array( 'simple' ),
			array(),
			false
		);

		$token       = 'PP-RETURN-VALID-' . uniqid();
		$real_secret = 'the-real-bound-secret-' . uniqid();
		set_transient( 'ppcp_ru_' . $token, $real_secret, DAY_IN_SECONDS );

		$_GET['token']             = $token;
		$_GET['ppcp_return_nonce'] = $real_secret;

		$approved_order = $this->create_paypal_order_double( OrderStatus::APPROVED, (string) $order->get_id() );
		$captured_order = $this->create_captured_paypal_order_double( (string) $order->get_id() );

		$order_endpoint = Mockery::mock( OrderEndpoint::class )->shouldIgnoreMissing();
		$order_endpoint->shouldReceive( 'order' )->andReturn( $approved_order );
		$order_endpoint->shouldReceive( 'capture' )->andReturn( $captured_order );
		$order_endpoint->shouldReceive( 'patch_order_with' )->andReturn( $approved_order );

		$session_handler = Mockery::mock( SessionHandler::class )->shouldIgnoreMissing();
		$session_handler->shouldReceive( 'replace_order' )->once()->with( $approved_order );
		$session_handler->shouldReceive( 'order' )->andReturn( $approved_order );

		$c = $this->bootstrapModule(
			array(
				'api.endpoint.order' => fn() => $order_endpoint,
				'session.handler'    => fn() => $session_handler,
			)
		);

		$gateway = $c->get( 'wcgateway.paypal-gateway' );

		$endpoint = new ReturnUrlEndpoint(
			$gateway,
			$order_endpoint,
			$session_handler,
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing()
		);

		// When
		$this->invoke_and_halt_before_exit( $endpoint );

		delete_transient( 'ppcp_ru_' . $token );

		// Then
		$refreshed = wc_get_order( $order->get_id() );
		$this->assertSame( 'processing', $refreshed->get_status() );
		$this->assertNotEmpty( $refreshed->get_transaction_id() );
	}

	/**
	 * GIVEN an authorized return (valid bound secret, APPROVED PayPal order)
	 * AND the pending WC order was switched to a gateway that must not finish it
	 * WHEN ReturnUrlEndpoint::handle_request() handles the return
	 * THEN the order stays pending and unpaid
	 * AND the return secret is not consumed
	 * AND the buyer is sent back to the checkout
	 *
	 * @dataProvider disabled_gateway_provider
	 */
	public function test_leaves_order_pending_when_payment_method_is_a_disabled_gateway( string $gateway_id ): void {
		// Arrange
		$this->disable_gateway( $gateway_id );

		$order = $this->create_pending_order_with_payment_method( $gateway_id );

		$token  = 'PP-RETURN-DISABLED-GW-' . uniqid();
		$secret = 'the-real-bound-secret-' . uniqid();
		set_transient( 'ppcp_ru_' . $token, $secret, DAY_IN_SECONDS );

		$_GET['token']             = $token;
		$_GET['ppcp_return_nonce'] = $secret;

		$approved_order = $this->create_paypal_order_double( OrderStatus::APPROVED, (string) $order->get_id() );

		$order_endpoint = Mockery::mock( OrderEndpoint::class )->shouldIgnoreMissing();
		$order_endpoint->shouldReceive( 'order' )->andReturn( $approved_order );

		$endpoint = $this->create_endpoint( $order_endpoint );

		// When
		$this->invoke_and_halt_before_exit( $endpoint );

		$secret_still_present = false !== get_transient( 'ppcp_ru_' . $token );
		delete_transient( 'ppcp_ru_' . $token );

		// Then
		$refreshed = wc_get_order( $order->get_id() );
		$this->assertSame( 'pending', $refreshed->get_status() );
		$this->assertFalse( $refreshed->is_paid() );
		$this->assertTrue( $secret_still_present );
		$this->assertContains(
			'Payment gateway is unavailable. Please try again or contact support.',
			wp_list_pluck( wc_get_notices( 'error' ), 'notice' )
		);
	}

	public function disabled_gateway_provider(): array {
		return array(
			'disabled cash on delivery'       => array( 'cod' ),
			'disabled gateway of this plugin' => array( ReturnUrlEndpointTestGateway::ID ),
		);
	}

	/**
	 * GIVEN a card order that waits for its 3-D Secure resume
	 * AND the request carries the matching resume nonce
	 * AND the pending WC order was switched to the disabled cash on delivery gateway
	 * WHEN ReturnUrlEndpoint::handle_request() handles the return without a token
	 * THEN the order stays pending and unpaid
	 */
	public function test_leaves_order_pending_when_3ds_resume_targets_a_disabled_gateway(): void {
		// Arrange
		$this->disable_gateway( 'cod' );

		$order = $this->create_pending_order_with_payment_method( 'cod' );
		$nonce = 'the-resume-nonce-' . uniqid();
		$order->update_meta_data( CreditCardGateway::THREE_DS_RESUME_META, $nonce );
		$order->save();

		$_GET['ppcp_resume_wc_order'] = (string) $order->get_id();
		$_GET['ppcp_resume_nonce']    = $nonce;

		$endpoint = $this->create_endpoint( Mockery::mock( OrderEndpoint::class )->shouldIgnoreMissing() );

		// When
		$this->invoke_and_halt_before_exit( $endpoint );

		// Then
		$refreshed = wc_get_order( $order->get_id() );
		$this->assertSame( 'pending', $refreshed->get_status() );
		$this->assertFalse( $refreshed->is_paid() );
	}

	private function create_endpoint( OrderEndpoint $order_endpoint ): ReturnUrlEndpoint {
		$session_handler = Mockery::mock( SessionHandler::class )->shouldIgnoreMissing();

		$gateway     = Mockery::mock( PayPalGateway::class );
		$gateway->id = PayPalGateway::ID;

		return new ReturnUrlEndpoint(
			$gateway,
			$order_endpoint,
			$session_handler,
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing()
		);
	}

	private function create_pending_order_with_payment_method( string $payment_method ): \WC_Order {
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			$payment_method,
			array( 'simple' ),
			array(),
			false
		);
		$order->set_customer_id( 0 );
		$order->set_status( 'pending' );
		$order->save();

		$this->assertSame( $payment_method, $order->get_payment_method() );

		return $order;
	}

	/**
	 * Stored settings are restored in tearDown().
	 */
	private function disable_gateway( string $gateway_id ): void {
		$option_name = 'woocommerce_' . $gateway_id . '_settings';

		if ( ! array_key_exists( $gateway_id, $this->original_gateway_settings ) ) {
			$this->original_gateway_settings[ $gateway_id ] = get_option( $option_name, false );
		}

		$settings            = (array) get_option( $option_name, array() );
		$settings['enabled'] = 'no';
		update_option( $option_name, $settings );

		if ( ReturnUrlEndpointTestGateway::ID === $gateway_id ) {
			WC()->payment_gateways()->payment_gateways[ $gateway_id ] = new ReturnUrlEndpointTestGateway();
		}

		$registered = WC()->payment_gateways()->payment_gateways();
		$this->assertArrayHasKey( $gateway_id, $registered );
		$registered[ $gateway_id ]->init_settings();
		$this->assertFalse( $registered[ $gateway_id ]->is_available() );
	}

	/**
	 * Builds a PayPal order double whose status and purchase-unit custom_id are
	 * configurable, matching the shape ReturnUrlEndpoint::handle_request() reads
	 * before any capture happens. Its amount mirrors the WC order, so
	 * OrderProcessor::patch_order() sees an unchanged amount.
	 */
	private function create_paypal_order_double( string $status, string $custom_id ): Order {
		$order_status = Mockery::mock( OrderStatus::class );
		$order_status->shouldReceive( 'is' )->with( $status )->andReturn( true );
		$order_status->shouldReceive( 'is' )->andReturn( false )->byDefault();
		$order_status->shouldReceive( 'name' )->andReturn( $status );

		$wc_order = wc_get_order( (int) $custom_id );

		$purchase_unit = Mockery::mock( PurchaseUnit::class )->shouldIgnoreMissing();
		$purchase_unit->shouldReceive( 'custom_id' )->andReturn( $custom_id );
		$purchase_unit->shouldReceive( 'amount' )->andReturn(
			new Amount( new Money( (float) $wc_order->get_total(), $wc_order->get_currency() ) )
		);

		$paypal_order = Mockery::mock( Order::class )->shouldIgnoreMissing();
		$paypal_order->shouldReceive( 'id' )->andReturn( 'PP-ORDER-' . uniqid() );
		$paypal_order->shouldReceive( 'intent' )->andReturn( 'CAPTURE' );
		$paypal_order->shouldReceive( 'status' )->andReturn( $order_status );
		$paypal_order->shouldReceive( 'purchase_units' )->andReturn( array( $purchase_unit ) );
		$paypal_order->shouldReceive( 'payment_source' )->andReturn( $this->create_paypal_payment_source() );

		return $paypal_order;
	}

	private function create_paypal_payment_source(): PaymentSource {
		return new PaymentSource(
			'paypal',
			(object) array(
				'email_address' => 'buyer@example.com',
				'account_id'    => 'TESTPAYERID123',
			)
		);
	}

	/**
	 * Builds a PayPal order double shaped like the result of a successful
	 * capture: COMPLETED status, with a completed Capture on its purchase unit,
	 * matching what TransactionIdHandlingTrait/PaymentsStatusHandlingTrait read
	 * to set the WC order's transaction id and "processing" status.
	 */
	private function create_captured_paypal_order_double( string $custom_id ): Order {
		$order_status = Mockery::mock( OrderStatus::class );
		$order_status->shouldReceive( 'is' )->with( OrderStatus::COMPLETED )->andReturn( true );
		$order_status->shouldReceive( 'is' )->andReturn( false )->byDefault();
		$order_status->shouldReceive( 'name' )->andReturn( 'COMPLETED' );

		$capture_status = Mockery::mock( CaptureStatus::class )->shouldIgnoreMissing();
		$capture_status->shouldReceive( 'name' )->andReturn( 'COMPLETED' );
		$capture_status->shouldReceive( 'details' )->andReturn( null );

		$capture = Mockery::mock( Capture::class )->shouldIgnoreMissing();
		$capture->shouldReceive( 'id' )->andReturn( 'TEST-CAPTURE-' . uniqid() );
		$capture->shouldReceive( 'status' )->andReturn( $capture_status );

		$payments = Mockery::mock( Payments::class )->shouldIgnoreMissing();
		$payments->shouldReceive( 'captures' )->andReturn( array( $capture ) );
		$payments->shouldReceive( 'authorizations' )->andReturn( array() );

		$purchase_unit = Mockery::mock( PurchaseUnit::class )->shouldIgnoreMissing();
		$purchase_unit->shouldReceive( 'custom_id' )->andReturn( $custom_id );
		$purchase_unit->shouldReceive( 'payments' )->andReturn( $payments );

		$paypal_order = Mockery::mock( Order::class )->shouldIgnoreMissing();
		$paypal_order->shouldReceive( 'id' )->andReturn( 'PP-CAPTURED-' . uniqid() );
		$paypal_order->shouldReceive( 'payment_source' )->andReturn( $this->create_paypal_payment_source() );
		$paypal_order->shouldReceive( 'intent' )->andReturn( 'CAPTURE' );
		$paypal_order->shouldReceive( 'status' )->andReturn( $order_status );
		$paypal_order->shouldReceive( 'purchase_units' )->andReturn( array( $purchase_unit ) );

		return $paypal_order;
	}

	/**
	 * Invokes ReturnUrlEndpoint::handle_request(), intercepting the terminal
	 * wp_safe_redirect()/exit() via the 'wp_redirect' filter so the PHPUnit
	 * process survives and the redirect target can be inspected.
	 *
	 * @return string The location handle_request() redirected to.
	 */
	private function invoke_and_halt_before_exit( ReturnUrlEndpoint $endpoint ): string {
		$captured_location = '';

		$this->redirect_filter = function ( $location ) use ( &$captured_location ) {
			$captured_location = (string) $location;
			throw new ReturnUrlEndpointHaltSignal( 'halt-before-exit' );
		};
		add_filter( 'wp_redirect', $this->redirect_filter );

		try {
			$endpoint->handle_request();
		} catch ( ReturnUrlEndpointHaltSignal $signal ) {
			// Expected: halts execution right before the real exit() call.
		} finally {
			remove_filter( 'wp_redirect', $this->redirect_filter );
			$this->redirect_filter = null;
		}

		return $captured_location;
	}
}
