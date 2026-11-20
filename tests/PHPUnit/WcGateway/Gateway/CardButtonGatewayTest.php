<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

class CardButtonGatewayTest extends TestCase {
	private const SESSION_ORDER_ID = 'PAYPAL-ORDER-ID';

	private $order_processor;
	private $session_handler;
	private $subscription_helper;
	private $order_status;
	private $order_notes;

	public function setUp(): void {
		parent::setUp();

		$this->order_processor     = Mockery::mock( OrderProcessor::class );
		$this->session_handler     = Mockery::mock( SessionHandler::class )->shouldIgnoreMissing();
		$this->subscription_helper = Mockery::mock( SubscriptionHelper::class );
		$this->order_status        = null;
		$this->order_notes         = array();

		$this->subscription_helper->allows( 'has_subscription' )->andReturn( false );

		when( 'wc_add_notice' )->justReturn( null );
		when( 'wc_get_checkout_url' )->justReturn( 'http://example.com/checkout' );
		when( 'is_checkout_pay_page' )->justReturn( false );

		$session = Mockery::mock( \WC_Session::class );
		$session->allows( 'get' )->andReturn( null );
		$session->allows( 'set' );
		$woocommerce          = Mockery::mock( \WooCommerce::class );
		$woocommerce->session = $session;
		when( 'WC' )->justReturn( $woocommerce );
	}

	private function create_gateway(): CardButtonGateway {
		return new CardButtonGateway(
			$this->order_processor,
			$this->session_handler,
			Mockery::mock( RefundProcessor::class ),
			true,
			Mockery::mock( TransactionUrlProvider::class ),
			$this->subscription_helper,
			true,
			Mockery::mock( Environment::class ),
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing(),
			static fn ( $id ) => 'checkoutnow=' . $id
		);
	}

	private function create_wc_order(): WC_Order {
		$order = Mockery::mock( WC_Order::class );
		$order->allows( 'get_meta' )->andReturn( '' );
		$order->allows( 'update_status' )->andReturnUsing(
			function ( string $status ): bool {
				$this->order_status = $status;
				return true;
			}
		);
		$order->allows( 'add_order_note' )->andReturnUsing(
			function ( string $note ): int {
				$this->order_notes[] = $note;
				return 1;
			}
		);

		when( 'wc_get_order' )->justReturn( $order );

		return $order;
	}

	private function capture_fails_with( string $issue ): void {
		$response          = new \stdClass();
		$response->details = array(
			(object) array(
				'issue'       => $issue,
				'description' => 'Additional action needed.',
			),
		);

		$this->order_processor->allows( 'process' )->andThrow( new PayPalApiException( $response, 422 ) );
	}

	private function session_has_order( string $id ): void {
		$paypal_order = Mockery::mock( Order::class );
		$paypal_order->allows( 'id' )->andReturn( $id );

		$this->session_handler->allows( 'order' )->andReturn( $paypal_order );
	}

	public function retry_issue_provider(): array {
		return array(
			'instrument declined'   => array( 'INSTRUMENT_DECLINED', 'Instrument declined.' ),
			'payer action required' => array( 'PAYER_ACTION_REQUIRED', 'Payer action required, possibly overcharge.' ),
		);
	}

	/**
	 * GIVEN a card button order whose capture fails with a recoverable issue on the first try
	 * WHEN the gateway processes the payment
	 * THEN the buyer is sent back to PayPal to approve the same PayPal order again
	 * AND the WooCommerce order is not marked as failed
	 *
	 * @dataProvider retry_issue_provider
	 */
	public function test_recoverable_capture_issue_sends_buyer_back_to_paypal( string $issue ): void {
		$this->create_wc_order();
		$this->capture_fails_with( $issue );
		$this->session_handler->allows( 'insufficient_funding_tries' )->andReturn( 1 );
		$this->session_has_order( self::SESSION_ORDER_ID );

		$result = $this->create_gateway()->process_payment( 1 );

		$this->assertSame( 'success', $result['result'] );
		$this->assertSame( 'checkoutnow=' . self::SESSION_ORDER_ID, $result['redirect'] );
		$this->assertNull( $this->order_status );
	}

	/**
	 * GIVEN a card button order whose capture fails with a recoverable issue on the first try
	 * WHEN the gateway processes the payment
	 * THEN an order note records the reason for the retry
	 *
	 * @dataProvider retry_issue_provider
	 */
	public function test_recoverable_capture_issue_is_noted_on_the_order( string $issue, string $expected_note ): void {
		$this->create_wc_order();
		$this->capture_fails_with( $issue );
		$this->session_handler->allows( 'insufficient_funding_tries' )->andReturn( 1 );
		$this->session_has_order( self::SESSION_ORDER_ID );

		$this->create_gateway()->process_payment( 1 );

		$this->assertSame( array( $expected_note . ' Additional action needed.' ), $this->order_notes );
	}

	/**
	 * GIVEN a card button order whose capture fails with a recoverable issue on the first try
	 * WHEN the gateway processes the payment
	 * THEN the session keeps the PayPal order id the buyer is sent back to
	 *
	 * @dataProvider retry_issue_provider
	 */
	public function test_recoverable_capture_issue_keeps_session_order_for_the_retry( string $issue ): void {
		$this->create_wc_order();
		$this->capture_fails_with( $issue );
		$this->session_handler->allows( 'insufficient_funding_tries' )->andReturn( 1 );
		$this->session_has_order( self::SESSION_ORDER_ID );
		$this->session_handler->shouldReceive( 'replace_payer_action_order_id' )->once()->with( self::SESSION_ORDER_ID );

		$this->create_gateway()->process_payment( 1 );

		$this->addToAssertionCount( 1 );
	}
}
