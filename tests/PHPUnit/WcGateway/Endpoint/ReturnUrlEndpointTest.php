<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use WC_Order;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint
 */
class ReturnUrlEndpointTest extends TestCase
{
	private const WC_ORDER_ID    = 42;
	private const NONCE          = 'resume-nonce';
	private const CARD_METHOD    = 'ppcp-credit-card-gateway';
	private const SUCCESS_URL    = 'https://example.com/order-received';
	private const CHECKOUT_URL   = 'https://example.com/checkout';

	private PayPalGateway $paypal_gateway;

	/** @var array<string, WC_Payment_Gateway> */
	private array $available_gateways = [];

	/** @var array<string, WC_Payment_Gateway> */
	private array $registered_gateways = [];

	private string $payment_method = self::CARD_METHOD;

	private ReturnUrlEndpoint $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->available_gateways  = [];
		$this->registered_gateways = [];
		$this->payment_method      = self::CARD_METHOD;

		$_GET = [
			'ppcp_resume_wc_order' => (string) self::WC_ORDER_ID,
			'ppcp_resume_nonce'    => self::NONCE,
		];

		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_meta' )->with( CreditCardGateway::THREE_DS_RESUME_META )->andReturn( self::NONCE );
		$wc_order->allows( 'get_payment_method' )->andReturnUsing(
			function (): string {
				return $this->payment_method;
			}
		);

		when( 'absint' )->alias( 'intval' );
		when( 'wc_get_order' )->justReturn( $wc_order );
		when( 'wc_add_notice' )->justReturn();
		when( 'wc_get_checkout_url' )->justReturn( self::CHECKOUT_URL );
		when( 'wp_safe_redirect' )->alias(
			static function ( string $url ): void {
				throw new RuntimeException( $url );
			}
		);

		$payment_gateways = Mockery::mock();
		$payment_gateways->allows( 'get_available_payment_gateways' )->andReturnUsing(
			function (): array {
				return $this->available_gateways;
			}
		);
		$payment_gateways->allows( 'payment_gateways' )->andReturnUsing(
			function (): array {
				return $this->registered_gateways;
			}
		);
		when( 'WC' )->justReturn( (object) array( 'payment_gateways' => $payment_gateways ) );

		$this->paypal_gateway     = Mockery::mock( PayPalGateway::class );
		$this->paypal_gateway->id = 'ppcp-gateway';

		$this->sut = new ReturnUrlEndpoint(
			$this->paypal_gateway,
			Mockery::mock( OrderEndpoint::class ),
			Mockery::mock( SessionHandler::class ),
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing()
		);
	}

	public function tearDown(): void
	{
		$_GET = [];
		parent::tearDown();
	}

	/**
	 * GIVEN the buyer returns from 3DS with a valid resume nonce
	 * AND the order's gateway is the PayPal gateway, an available gateway, or only a registered gateway
	 * WHEN the return URL endpoint handles the request
	 * THEN that gateway processes the payment for the order
	 * AND the buyer is redirected to the gateway's success URL
	 *
	 * @dataProvider gateway_location_provider
	 */
	public function test_resume_processes_payment_with_gateway_of_order(
		string $location
	): void {
		$gateway = $this->create_gateway_expecting_payment();

		if ( $location === 'injected' ) {
			$this->sut = $this->create_sut_with( $gateway );
		} elseif ( $location === 'available' ) {
			$this->available_gateways[ self::CARD_METHOD ]  = $gateway;
			$this->registered_gateways[ self::CARD_METHOD ] = $gateway;
		} else {
			$this->registered_gateways[ self::CARD_METHOD ] = $gateway;
		}

		$this->assertSame( self::SUCCESS_URL, $this->redirect_target() );
	}

	public function gateway_location_provider(): array
	{
		return [
			'gateway is the injected PayPal gateway'                  => [ 'injected' ],
			'gateway is available in the current checkout'            => [ 'available' ],
			'gateway is registered but hidden in continuation mode'   => [ 'registered' ],
		];
	}

	/**
	 * GIVEN the buyer returns from 3DS with a valid resume nonce
	 * AND the order's payment method matches no gateway
	 * WHEN the return URL endpoint handles the request
	 * THEN no gateway processes a payment
	 * AND the buyer is sent back to the checkout
	 */
	public function test_resume_processes_no_payment_when_order_gateway_is_unknown(): void
	{
		$other_gateway = Mockery::mock( WC_Payment_Gateway::class );
		$other_gateway->shouldNotReceive( 'process_payment' );
		$this->available_gateways['ppcp-other']  = $other_gateway;
		$this->registered_gateways['ppcp-other'] = $other_gateway;
		$this->paypal_gateway->shouldNotReceive( 'process_payment' );

		$this->assertSame( self::CHECKOUT_URL, $this->redirect_target() );
	}

	private function create_gateway_expecting_payment(): PayPalGateway
	{
		$gateway = Mockery::mock( PayPalGateway::class );
		$gateway->id = self::CARD_METHOD;
		$gateway->expects( 'process_payment' )
			->with( self::WC_ORDER_ID )
			->andReturn(
				array(
					'result'   => 'success',
					'redirect' => self::SUCCESS_URL,
				)
			);

		return $gateway;
	}

	private function create_sut_with( PayPalGateway $gateway ): ReturnUrlEndpoint
	{
		return new ReturnUrlEndpoint(
			$gateway,
			Mockery::mock( OrderEndpoint::class ),
			Mockery::mock( SessionHandler::class ),
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing()
		);
	}

	private function redirect_target(): string
	{
		try {
			$this->sut->handle_request();
		} catch ( RuntimeException $redirect ) {
			return $redirect->getMessage();
		}

		return '';
	}
}
