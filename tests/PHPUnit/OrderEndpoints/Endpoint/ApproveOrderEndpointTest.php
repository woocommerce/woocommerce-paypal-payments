<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\ApiClient\Helper\OrderHelper;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\OrderEndpoints\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;

/**
 * @covers \WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ApproveOrderEndpoint
 */
class ApproveOrderEndpointTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	private RequestData $request_data;
	private OrderEndpoint $api_endpoint;
	private SessionHandler $session_handler;
	private ThreeDSecure $threed_secure;
	private SettingsProvider $settings_provider;
	private SettingsModel $settings_model;
	private DccApplies $dcc_applies;
	private OrderHelper $order_helper;
	private PayPalGateway $gateway;
	private WooCommerceOrderCreator $wc_order_creator;
	private LoggerInterface $logger;
	private Context $context;
	private ApproveOrderEndpoint $sut;

	public function setUp(): void
	{
		parent::setUp();
		$this->request_data     = Mockery::mock( RequestData::class );
		$this->api_endpoint     = Mockery::mock( OrderEndpoint::class );
		$this->session_handler  = Mockery::mock( SessionHandler::class );
		$this->threed_secure    = Mockery::mock( ThreeDSecure::class );
		$this->settings_provider = Mockery::mock( SettingsProvider::class );
		$this->settings_model   = Mockery::mock( SettingsModel::class );
		$this->dcc_applies      = Mockery::mock( DccApplies::class );
		$this->order_helper     = Mockery::mock( OrderHelper::class );
		$this->gateway          = Mockery::mock( PayPalGateway::class );
		$this->wc_order_creator = Mockery::mock( WooCommerceOrderCreator::class );
		$this->logger           = Mockery::mock( LoggerInterface::class );
		$this->context          = Mockery::mock( Context::class );

		$this->sut = new ApproveOrderEndpoint(
			$this->request_data,
			$this->api_endpoint,
			$this->session_handler,
			$this->threed_secure,
			$this->settings_provider,
			$this->settings_model,
			$this->dcc_applies,
			$this->order_helper,
			false,
			$this->gateway,
			$this->wc_order_creator,
			$this->logger,
			$this->context
		);
	}

	/**
	 * @scenario When handle_request() fetches a PayPal order, it extracts custom_id from
	 *           purchase_units[0] and parses the pcp_customer_<session_id> value.
	 */
	public function test_extracts_session_id_from_purchase_unit_custom_id(): void
	{
		// Arrange
		$session_id    = 'abc123';
		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'custom_id' )->andReturn( 'pcp_customer_' . $session_id );

		$order_status = Mockery::mock( OrderStatus::class );
		$order_status->shouldReceive( 'is' )->andReturn( true );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'purchase_units' )->andReturn( array( $purchase_unit ) );
		$order->shouldReceive( 'payment_source' )->andReturn( null );
		$order->shouldReceive( 'status' )->andReturn( $order_status );

		$this->request_data->shouldReceive( 'read_request' )
			->with( ApproveOrderEndpoint::nonce() )
			->andReturn( array( 'order_id' => 'ORDER-123', 'funding_source' => null, 'should_create_wc_order' => false ) );
		$this->api_endpoint->shouldReceive( 'order' )->with( 'ORDER-123' )->andReturn( $order );
		$this->session_handler->shouldReceive( 'replace_funding_source' )->once();
		$this->session_handler->shouldReceive( 'replace_order' )->once()->with( $order );
		$this->context->shouldReceive( 'is_checkout' )->andReturn( false );

		$wc_session = Mockery::mock( \WC_Session_Handler::class );
		$wc_session->shouldReceive( 'get_customer_unique_id' )->andReturn( $session_id );
		$wc_session->shouldReceive( 'set' );
		$wc          = Mockery::mock();
		$wc->session = $wc_session;
		when( 'WC' )->justReturn( $wc );
		expect( 'wp_send_json_success' )->once();

		// When
		$this->sut->handle_request();

		// Then — Mockery expectation on replace_order verifies extraction + session match succeeded
	}

	/**
	 * @scenario If the extracted session ID does not match the current WC session ID,
	 *           handle_request() raises an exception and does not call replace_order()
	 *           or process_payment().
	 */
	public function test_mismatched_session_id_raises_exception_and_blocks_state_changes(): void
	{
		// Arrange
		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'custom_id' )->andReturn( 'pcp_customer_victim-session' );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'purchase_units' )->andReturn( array( $purchase_unit ) );
		$order->shouldReceive( 'payment_source' )->andReturn( null );

		$this->request_data->shouldReceive( 'read_request' )
			->with( ApproveOrderEndpoint::nonce() )
			->andReturn( array( 'order_id' => 'ORDER', 'funding_source' => null, 'should_create_wc_order' => false ) );
		$this->api_endpoint->shouldReceive( 'order' )->with( 'ORDER' )->andReturn( $order );
		$this->session_handler->shouldReceive( 'replace_order' )->never();
		$this->gateway->shouldReceive( 'process_payment' )->never();
		$this->logger->shouldReceive( 'error' )->once();

		$wc_session  = Mockery::mock( \WC_Session_Handler::class );
		$wc_session->shouldReceive( 'get_customer_unique_id' )->andReturn( 'attacker-session' );
		$wc          = Mockery::mock();
		$wc->session = $wc_session;
		when( 'WC' )->justReturn( $wc );
		expect( 'wp_send_json_error' )->once();

		// When
		$this->sut->handle_request();

		// Then — replace_order and process_payment never called (Mockery ->never() enforces this)
	}

	/**
	 * @scenario An attacker-supplied order_id belonging to a different session is rejected
	 *           with a non-descriptive error response (no information leakage about order ownership).
	 */
	public function test_mismatched_session_response_reveals_no_order_ownership(): void
	{
		// Arrange
		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'custom_id' )->andReturn( 'pcp_customer_victim-session' );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'purchase_units' )->andReturn( array( $purchase_unit ) );
		$order->shouldReceive( 'payment_source' )->andReturn( null );

		$this->request_data->shouldReceive( 'read_request' )
			->with( ApproveOrderEndpoint::nonce() )
			->andReturn( array( 'order_id' => 'ORDER', 'funding_source' => null, 'should_create_wc_order' => false ) );
		$this->api_endpoint->shouldReceive( 'order' )->with( 'ORDER' )->andReturn( $order );
		$this->logger->shouldReceive( 'error' )->once();

		$wc_session  = Mockery::mock( \WC_Session_Handler::class );
		$wc_session->shouldReceive( 'get_customer_unique_id' )->andReturn( 'attacker-session' );
		$wc          = Mockery::mock();
		$wc->session = $wc_session;
		when( 'WC' )->justReturn( $wc );
		expect( 'wp_send_json_error' )
			->once()
			->with(
				Mockery::on(
					static function ( array $data ): bool {
						$message = strtolower( $data['message'] ?? '' );
						return strpos( $message, 'session' ) === false
							&& strpos( $message, 'owner' ) === false
							&& strpos( $message, 'belong' ) === false
							&& strpos( $message, 'other user' ) === false;
					}
				)
			);

		// When
		$this->sut->handle_request();

		// Then — error response contains no ownership-revealing phrases
	}

	/**
	 * @scenario An order_id supplied by the same session that created it proceeds normally
	 *           through replace_order() and process_payment().
	 */
	public function test_valid_order_session_proceeds_to_replace_order(): void
	{
		// Arrange
		$session_id    = 'session-99';
		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'custom_id' )->andReturn( 'pcp_customer_' . $session_id );

		$order_status = Mockery::mock( OrderStatus::class );
		$order_status->shouldReceive( 'is' )->andReturn( true );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'purchase_units' )->andReturn( array( $purchase_unit ) );
		$order->shouldReceive( 'payment_source' )->andReturn( null );
		$order->shouldReceive( 'status' )->andReturn( $order_status );

		$this->request_data->shouldReceive( 'read_request' )
			->with( ApproveOrderEndpoint::nonce() )
			->andReturn( array( 'order_id' => 'VALID-ORDER', 'funding_source' => 'paypal', 'should_create_wc_order' => false ) );
		$this->api_endpoint->shouldReceive( 'order' )->with( 'VALID-ORDER' )->andReturn( $order );
		$this->session_handler->shouldReceive( 'replace_funding_source' )->once()->with( 'paypal' );
		$this->session_handler->shouldReceive( 'replace_order' )->once()->with( $order );
		$this->context->shouldReceive( 'is_checkout' )->andReturn( false );

		$wc_session  = Mockery::mock( \WC_Session_Handler::class );
		$wc_session->shouldReceive( 'get_customer_unique_id' )->andReturn( $session_id );
		$wc_session->shouldReceive( 'set' );
		$wc          = Mockery::mock();
		$wc->session = $wc_session;
		when( 'WC' )->justReturn( $wc );
		expect( 'wp_send_json_success' )->once();

		// When
		$this->sut->handle_request();

		// Then — replace_order called with the correct order and success response sent
	}

	/**
	 * GIVEN a Pay Now approval outside the checkout page that asks for a WC order
	 * AND the gateway's process_payment() returns a redirect URL
	 * WHEN the approval request is handled
	 * THEN the response carries exactly the redirect the gateway returned
	 *      (order-received, PayPal approval or checkout URL)
	 *
	 * @dataProvider gateway_redirect_provider
	 */
	public function test_pay_now_responds_with_the_redirect_returned_by_the_gateway( string $redirect ): void
	{
		$order_status = Mockery::mock( OrderStatus::class );
		$order_status->shouldReceive( 'is' )->andReturn( true );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'purchase_units' )->andReturn( array() );
		$order->shouldReceive( 'payment_source' )->andReturn( null );
		$order->shouldReceive( 'status' )->andReturn( $order_status );

		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 55 );

		$this->request_data->shouldReceive( 'read_request' )
			->andReturn( array( 'order_id' => 'ORDER-1', 'funding_source' => 'paypal', 'should_create_wc_order' => true ) );
		$this->api_endpoint->shouldReceive( 'order' )->with( 'ORDER-1' )->andReturn( $order );
		$this->session_handler->shouldReceive( 'replace_funding_source' );
		$this->session_handler->shouldReceive( 'replace_order' );
		$this->context->shouldReceive( 'is_checkout' )->andReturn( false );
		$this->wc_order_creator->shouldReceive( 'create_from_paypal_order' )->andReturn( $wc_order );
		$this->gateway->shouldReceive( 'process_payment' )
			->with( 55 )
			->andReturn( array( 'result' => 'success', 'redirect' => $redirect ) );

		$wc_session = Mockery::mock( \WC_Session_Handler::class );
		$wc_session->shouldReceive( 'set' );
		$wc          = Mockery::mock();
		$wc->session = $wc_session;
		$wc->cart    = Mockery::mock();
		when( 'WC' )->justReturn( $wc );

		$responses = array();
		when( 'wp_send_json_success' )->alias(
			static function ( $data = null ) use ( &$responses ): void {
				$responses[] = $data;
			}
		);

		$this->sut->handle_request();

		$this->assertSame( array( 'order_received_url' => $redirect ), $responses[0] );
	}

	public function gateway_redirect_provider(): array
	{
		return array(
			'order-received URL after a successful payment' => array( 'https://shop.example/checkout/order-received/55/?key=wc_order_abc' ),
			'PayPal approval URL when payment needs another approval' => array( 'https://www.paypal.com/checkoutnow?token=ORDER-1' ),
			'checkout URL when the payment fails' => array( 'https://shop.example/checkout/' ),
		);
	}
}
