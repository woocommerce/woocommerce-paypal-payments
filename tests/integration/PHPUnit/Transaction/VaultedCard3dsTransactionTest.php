<?php

namespace WooCommerce\PayPalCommerce\Tests\Integration\Transaction;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\CaptureCardPayment;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;

/**
 * @group transactions
 */
class VaultedCard3dsTransactionTest extends IntegrationMockedTestCase {
	/**
	 * @var callable|null Filter registered to expose the credit-card gateway; removed in tearDown.
	 */
	private $register_cc_gateway_filter = null;

	public function tearDown(): void {
		if ( null !== $this->register_cc_gateway_filter ) {
			remove_filter( 'woocommerce_payment_gateways', $this->register_cc_gateway_filter );
			$this->register_cc_gateway_filter = null;
			WC()->payment_gateways()->init();
		}

		parent::tearDown();
	}

	/**
	 * GIVEN a saved card whose issuer requires 3D Secure
	 * AND PayPal returns the created order with a payer-action link
	 * WHEN the saved card is charged through the credit card gateway
	 * THEN the buyer is redirected to the payer-action URL
	 * AND the order stores a resume nonce and the PayPal order id
	 */
	public function test_saved_card_requiring_3ds_redirects_to_payer_action() {
		wp_set_current_user( $this->customer_id );

		// WC core's get_tokens() only returns tokens whose gateway_id is a
		// registered payment gateway. In a live DCC checkout the credit-card
		// gateway is registered; this test env does not enable it, so register
		// it here or the saved token would be filtered out before the vault
		// branch can match it.
		$this->registerCreditCardGateway();

		$token = $this->createAPaymentTokenForTheCustomer(
			$this->customer_id,
			'ppcp-credit-card-gateway'
		);

		$order = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-credit-card-gateway',
			array( 'simple' ),
			array(),
			false
		);

		$payer_action_url = 'https://www.sandbox.paypal.com/checkoutnow?token=PP-ORDER-3DS';

		$created_order = Mockery::mock( Order::class )->shouldIgnoreMissing();
		$created_order->shouldReceive( 'id' )->andReturn( 'PP-ORDER-3DS' );
		$created_order->shouldReceive( 'intent' )->andReturn( 'CAPTURE' );
		$created_order->shouldReceive( 'payment_source' )->andReturn( null );
		$created_order->shouldReceive( 'payer' )->andReturn( null );
		$created_order->shouldReceive( 'purchase_units' )->andReturn( [] );
		$created_order->shouldReceive( 'links' )->andReturn( [
			(object) array(
				'rel'  => 'self',
				'href' => 'https://api.paypal.com/v2/checkout/orders/PP-ORDER-3DS',
			),
			(object) array( 'rel' => 'payer-action', 'href' => $payer_action_url ),
		] );

		$captureCardPayment = Mockery::mock( CaptureCardPayment::class );
		$captureCardPayment->shouldReceive( 'create_order' )->andReturn( $created_order );

		$orderEndpoint = Mockery::mock( OrderEndpoint::class );
		$orderEndpoint->shouldReceive( 'order' )->andReturn( $created_order );

		$c = $this->bootstrapModule( array(
			'wcgateway.endpoint.capture-card-payment' => fn() => $captureCardPayment,
			'api.endpoint.order'                      => fn() => $orderEndpoint,
		) );

		$_POST['wc-ppcp-credit-card-gateway-payment-token'] = (string) $token->get_id();

		$gateway = $c->get( 'wcgateway.credit-card-gateway' );
		$result  = $gateway->process_payment( $order->get_id() );

		$this->assertSame( 'success', $result['result'] );
		$this->assertSame( $payer_action_url, $result['redirect'] );

		$order = wc_get_order( $order->get_id() );
		$this->assertNotEmpty( $order->get_meta( CreditCardGateway::THREE_DS_RESUME_META ) );
		$this->assertSame( 'PP-ORDER-3DS', $order->get_meta( PayPalGateway::ORDER_ID_META_KEY ) );

		unset( $_POST['wc-ppcp-credit-card-gateway-payment-token'] );
	}

	/**
	 * GIVEN an order with a stored resume nonce and PayPal order id
	 * AND the buyer returns with the matching nonce in the URL
	 * AND PayPal reports the order as capturable and the capture completes
	 * WHEN the payment is processed
	 * THEN the order is captured and reaches the processing status
	 * AND the resume nonce is cleared
	 */
	public function test_returning_from_3ds_captures_and_completes_the_order() {
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-credit-card-gateway',
			array( 'simple' ),
			array(),
			false
		);

		$resume_nonce = 'test-resume-nonce-abc123';
		$order->update_meta_data( CreditCardGateway::THREE_DS_RESUME_META, $resume_nonce );
		$order->update_meta_data( PayPalGateway::ORDER_ID_META_KEY, 'PP-ORDER-3DS' );
		$order->save();

		$orderEndpoint = $this->mockOrderEndpoint( 'CAPTURE', true, true );

		$sessionHandler = Mockery::mock( SessionHandler::class )->shouldIgnoreMissing();
		$sessionHandler->shouldReceive( 'order' )->andReturn( null );
		$sessionHandler->shouldReceive( 'destroy_session_data' )->andReturnSelf();

		$c = $this->bootstrapModule( array(
			'api.endpoint.order' => fn() => $orderEndpoint,
			'session.handler'    => fn() => $sessionHandler,
		) );

		$_GET['ppcp_resume_wc_order'] = (string) $order->get_id();
		$_GET['ppcp_resume_nonce']    = $resume_nonce;

		$gateway = $c->get( 'wcgateway.credit-card-gateway' );
		$result  = $gateway->process_payment( $order->get_id() );

		unset( $_GET['ppcp_resume_wc_order'], $_GET['ppcp_resume_nonce'] );

		$this->assertSame( 'success', $result['result'] );

		$order = wc_get_order( $order->get_id() );
		$this->assertSame( 'processing', $order->get_status() );
		$this->assertNotEmpty( $order->get_transaction_id() );
		$this->assertEmpty( $order->get_meta( CreditCardGateway::THREE_DS_RESUME_META ) );
	}

	/**
	 * GIVEN a resume request whose ppcp_resume_wc_order points to an order that has
	 * NO stored resume nonce (e.g. a guessed/arbitrary order id)
	 * AND a nonce is supplied in the URL
	 * WHEN the return endpoint handles it
	 * THEN payment processing is not invoked and the order is left untouched
	 */
	public function test_resume_ignores_order_without_stored_nonce() {
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			PayPalGateway::ID,
			array( 'simple' ),
			array(),
			false
		);

		$status_before = $order->get_status();

		$_GET['ppcp_resume_wc_order'] = (string) $order->get_id();
		$_GET['ppcp_resume_nonce']    = 'any-nonce';

		$this->invokeResume();

		unset( $_GET['ppcp_resume_wc_order'], $_GET['ppcp_resume_nonce'] );

		$this->assertSame( $status_before, wc_get_order( $order->get_id() )->get_status() );
	}

	/**
	 * GIVEN an order that is awaiting a 3D Secure resume (a nonce is stored)
	 * AND the buyer/attacker returns with a nonce that does NOT match
	 * WHEN the return endpoint handles it
	 * THEN payment processing is not invoked and the order is left untouched
	 */
	public function test_resume_rejects_mismatched_nonce() {
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			PayPalGateway::ID,
			array( 'simple' ),
			array(),
			false
		);

		$order->update_meta_data( CreditCardGateway::THREE_DS_RESUME_META, 'the-real-nonce' );
		$order->save();
		$status_before = $order->get_status();

		$_GET['ppcp_resume_wc_order'] = (string) $order->get_id();
		$_GET['ppcp_resume_nonce']    = 'a-guessed-nonce';

		$this->invokeResume();

		unset( $_GET['ppcp_resume_wc_order'], $_GET['ppcp_resume_nonce'] );

		$this->assertSame( $status_before, wc_get_order( $order->get_id() )->get_status() );
		$this->assertSame( 'the-real-nonce', wc_get_order( $order->get_id() )->get_meta( CreditCardGateway::THREE_DS_RESUME_META ) );
	}

	/**
	 * GIVEN an order with a matching stored resume nonce
	 * AND the order's payment_method is not the injected gateway's id
	 *     and is not registered as a WC payment gateway in this test environment
	 * WHEN maybe_resume_card_3ds runs
	 * THEN it returns early without invoking payment processing
	 * AND the order status is unchanged and the stored nonce is still present
	 */
	public function test_resume_returns_when_gateway_not_found(): void {
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-credit-card-gateway',
			array( 'simple' ),
			array(),
			false
		);

		$resume_nonce = 'test-nonce-no-gateway';
		$order->update_meta_data( CreditCardGateway::THREE_DS_RESUME_META, $resume_nonce );
		$order->save();
		$status_before = $order->get_status();

		$_GET['ppcp_resume_wc_order'] = (string) $order->get_id();
		$_GET['ppcp_resume_nonce']    = $resume_nonce;

		$no_process_gateway     = Mockery::mock( PayPalGateway::class );
		$no_process_gateway->id = PayPalGateway::ID;
		$no_process_gateway->shouldNotReceive( 'process_payment' );

		$this->invokeResume( $no_process_gateway );

		unset( $_GET['ppcp_resume_wc_order'], $_GET['ppcp_resume_nonce'] );

		$refreshed = wc_get_order( $order->get_id() );
		$this->assertSame( $status_before, $refreshed->get_status() );
		$this->assertSame( $resume_nonce, $refreshed->get_meta( CreditCardGateway::THREE_DS_RESUME_META ) );
	}

	/**
	 * GIVEN an order with a matching stored resume nonce
	 * AND the order's payment_method equals the injected gateway's id
	 * AND the gateway's process_payment() returns a failure result (not a success array)
	 * WHEN maybe_resume_card_3ds runs
	 * THEN it returns without redirecting or exiting
	 * AND the order is left untouched
	 */
	public function test_resume_returns_when_process_payment_fails(): void {
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			PayPalGateway::ID,
			array( 'simple' ),
			array(),
			false
		);

		$resume_nonce = 'test-nonce-failure-result';
		$order->update_meta_data( CreditCardGateway::THREE_DS_RESUME_META, $resume_nonce );
		$order->save();
		$status_before = $order->get_status();

		$_GET['ppcp_resume_wc_order'] = (string) $order->get_id();
		$_GET['ppcp_resume_nonce']    = $resume_nonce;

		$failing_gateway     = Mockery::mock( PayPalGateway::class );
		$failing_gateway->id = PayPalGateway::ID;
		$failing_gateway->allows( 'process_payment' )->andReturn( [ 'result' => 'failure' ] );

		$this->invokeResume( $failing_gateway );

		unset( $_GET['ppcp_resume_wc_order'], $_GET['ppcp_resume_nonce'] );

		$refreshed = wc_get_order( $order->get_id() );
		$this->assertSame( $status_before, $refreshed->get_status() );
	}

	/**
	 * GIVEN an order with a stored resume nonce and PayPal order id
	 * AND the buyer returns with the matching nonce in the URL
	 * AND PayPal returns an AUTHORIZE-intent order
	 * WHEN the payment is processed via the resume path
	 * THEN finalize_vaulted_card_order takes the AUTHORIZE branch:
	 *      order_endpoint->authorize() is called and the order reaches on-hold status
	 * AND the CAPTURED_META_KEY is set to 'false' on the WC order
	 * AND the resume nonce is cleared
	 */
	public function test_returning_from_3ds_authorizes_the_order(): void {
		$order = $this->getConfiguredOrder(
			$this->customer_id,
			'ppcp-credit-card-gateway',
			array( 'simple' ),
			array(),
			false
		);

		$resume_nonce = 'test-resume-nonce-authorize';
		$order->update_meta_data( CreditCardGateway::THREE_DS_RESUME_META, $resume_nonce );
		$order->update_meta_data( PayPalGateway::ORDER_ID_META_KEY, 'PP-ORDER-AUTH' );
		$order->save();

		$orderEndpoint = $this->mockOrderEndpoint( 'AUTHORIZE', true, true );

		$sessionHandler = Mockery::mock( SessionHandler::class )->shouldIgnoreMissing();
		$sessionHandler->shouldReceive( 'order' )->andReturn( null );
		$sessionHandler->shouldReceive( 'destroy_session_data' )->andReturnSelf();

		$c = $this->bootstrapModule( array(
			'api.endpoint.order' => fn() => $orderEndpoint,
			'session.handler'    => fn() => $sessionHandler,
		) );

		$_GET['ppcp_resume_wc_order'] = (string) $order->get_id();
		$_GET['ppcp_resume_nonce']    = $resume_nonce;

		$gateway = $c->get( 'wcgateway.credit-card-gateway' );
		$result  = $gateway->process_payment( $order->get_id() );

		unset( $_GET['ppcp_resume_wc_order'], $_GET['ppcp_resume_nonce'] );

		$this->assertSame( 'success', $result['result'] );

		$refreshed = wc_get_order( $order->get_id() );
		$this->assertSame( 'on-hold', $refreshed->get_status() );
		$this->assertSame( 'false', $refreshed->get_meta( AuthorizedPaymentsProcessor::CAPTURED_META_KEY ) );
		$this->assertEmpty( $refreshed->get_meta( CreditCardGateway::THREE_DS_RESUME_META ) );
	}

	/**
	 * Registers a minimal stub gateway under the credit-card gateway id so WC
	 * core's WC_Payment_Token_Data_Store::get_tokens() does not filter out the
	 * saved card token (it keeps only tokens whose gateway is registered).
	 */
	private function registerCreditCardGateway(): void {
		$stub = new class extends WC_Payment_Gateway {
			public function __construct() {
				$this->id = CreditCardGateway::ID;
			}
		};

		$this->register_cc_gateway_filter =
			static function ( array $gateways ) use ( $stub ): array {
				$gateways[] = $stub;

				return $gateways;
			};

		add_filter( 'woocommerce_payment_gateways', $this->register_cc_gateway_filter );
		WC()->payment_gateways()->init();
	}

	/**
	 * Invokes the private ReturnUrlEndpoint::maybe_resume_card_3ds() with the
	 * supplied gateway. Defaults to a gateway that must never be asked to process
	 * a payment, so a rejected resume is proven by process_payment not being
	 * called and the order being left untouched.
	 *
	 * @param WC_Payment_Gateway|null $gateway  Gateway to inject; null uses the default no-process
	 *                                          mock.
	 */
	private function invokeResume( $gateway = null ): void {
		if ( $gateway === null ) {
			$gateway     = Mockery::mock( PayPalGateway::class );
			$gateway->id = PayPalGateway::ID;
			$gateway->shouldNotReceive( 'process_payment' );
		}

		$endpoint = new ReturnUrlEndpoint(
			$gateway,
			Mockery::mock( OrderEndpoint::class ),
			Mockery::mock( SessionHandler::class ),
			Mockery::mock( LoggerInterface::class )
		);

		$method = new \ReflectionMethod( ReturnUrlEndpoint::class, 'maybe_resume_card_3ds' );
		$method->setAccessible( true );
		$method->invoke( $endpoint );
	}
}
