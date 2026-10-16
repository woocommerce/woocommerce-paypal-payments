<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use function Brain\Monkey\Functions\when;

class WalletGatewayPayerActionTest extends TestCase {
	private const WALLET_NOTICE = 'We could not complete the payment. Try again or choose a different payment method.';

	private $order_processor;
	private $notices;
	private $order_status;

	public function setUp(): void {
		parent::setUp();

		$this->order_processor = Mockery::mock( OrderProcessor::class );
		$this->notices         = array();
		$this->order_status    = null;

		when( 'wc_add_notice' )->alias(
			function ( string $message, string $type ): void {
				$this->notices[] = array( $type, $message );
			}
		);
		when( 'wc_get_checkout_url' )->justReturn( 'http://example.com/checkout' );
		when( 'is_checkout_pay_page' )->justReturn( false );

		$session = Mockery::mock( \WC_Session::class );
		$session->allows( 'get' )->andReturn( null );
		$session->allows( 'set' );
		$woocommerce          = Mockery::mock( \WooCommerce::class );
		$woocommerce->session = $session;
		when( 'WC' )->justReturn( $woocommerce );
	}

	private function create_gateway( string $gateway ) {
		$arguments = array(
			$this->order_processor,
			static fn ( $id ) => 'checkoutnow=' . $id,
			Mockery::mock( RefundProcessor::class ),
			Mockery::mock( TransactionUrlProvider::class ),
			Mockery::mock( SessionHandler::class )->shouldIgnoreMissing(),
			new AssetGetter( 'http://example.com', '/plugin/', 'module' ),
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing(),
		);

		return 'apple_pay' === $gateway ? new ApplePayGateway( ...$arguments ) : new GooglePayGateway( ...$arguments );
	}

	private function create_order_with_source( string $payment_source ): WC_Order {
		$order = Mockery::mock( WC_Order::class );
		$order->allows( 'get_meta' )->andReturnUsing(
			static fn ( $key ) => PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY === $key ? $payment_source : ''
		);
		$order->allows( 'update_status' )->andReturnUsing(
			function ( string $status ): bool {
				$this->order_status = $status;
				return true;
			}
		);

		return $order;
	}

	private function capture_fails_with( string $issue ): void {
		$response          = new \stdClass();
		$response->details = array( (object) array( 'issue' => $issue ) );

		$this->order_processor->allows( 'process' )->andThrow( new PayPalApiException( $response, 422 ) );
	}

	public function wallet_gateway_provider(): array {
		return array(
			'Apple Pay gateway'  => array( 'apple_pay' ),
			'Google Pay gateway' => array( 'google_pay' ),
		);
	}

	/**
	 * GIVEN a wallet order whose capture needs a payer action PayPal cannot show in the wallet
	 * WHEN the wallet gateway processes the payment
	 * THEN the buyer gets a failure with a clear try-again notice
	 * AND the WooCommerce order is marked as failed
	 *
	 * @dataProvider wallet_gateway_provider
	 */
	public function test_payer_action_required_on_wallet_order_fails_with_clear_notice( string $source ): void {
		$order = $this->create_order_with_source( $source );
		when( 'wc_get_order' )->justReturn( $order );
		$this->capture_fails_with( 'PAYER_ACTION_REQUIRED' );

		$result = $this->create_gateway( $source )->process_payment( 1 );

		$this->assertSame( 'failure', $result['result'] );
		$this->assertSame( self::WALLET_NOTICE, $result['errorMessage'] );
		$this->assertSame( array( array( 'error', self::WALLET_NOTICE ) ), $this->notices );
		$this->assertSame( 'failed', $this->order_status );
	}

	public function generic_failure_provider(): array {
		return array(
			'other PayPal error on Apple Pay order'  => array( 'apple_pay', 'apple_pay', 'INSTRUMENT_DECLINED' ),
			'other PayPal error on Google Pay order' => array( 'google_pay', 'google_pay', 'INSTRUMENT_DECLINED' ),
			'payer action on a PayPal-source order'  => array( 'apple_pay', 'paypal', 'PAYER_ACTION_REQUIRED' ),
		);
	}

	/**
	 * GIVEN a capture fails for a reason other than a payer action on a wallet order
	 * WHEN the wallet gateway processes the payment
	 * THEN the buyer gets the generic payment error
	 * AND the WooCommerce order is marked as failed
	 *
	 * @dataProvider generic_failure_provider
	 */
	public function test_other_capture_errors_keep_generic_message( string $gateway, string $source, string $issue ): void {
		$order = $this->create_order_with_source( $source );
		when( 'wc_get_order' )->justReturn( $order );
		$this->capture_fails_with( $issue );

		$result = $this->create_gateway( $gateway )->process_payment( 1 );

		$this->assertSame( 'failure', $result['result'] );
		$this->assertStringStartsWith( Messages::generic_payment_error_message(), $result['errorMessage'] );
		$this->assertNotSame( self::WALLET_NOTICE, $result['errorMessage'] );
		$this->assertSame( 'failed', $this->order_status );
	}
}
