<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\VaultComponent\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class CreateVaultOrderEndpointTest extends TestCase {

	private RequestData $request_data;
	private OrderEndpoint $order_endpoint;
	private PurchaseUnitFactory $purchase_unit_factory;
	private ShippingPreferenceFactory $shipping_preference_factory;
	private SessionHandler $session_handler;
	private LoggerInterface $logger;
	private CreateVaultOrderEndpoint $endpoint;

	public function setUp(): void {
		parent::setUp();

		$this->request_data                = Mockery::mock( RequestData::class );
		$this->order_endpoint              = Mockery::mock( OrderEndpoint::class );
		$this->purchase_unit_factory       = Mockery::mock( PurchaseUnitFactory::class );
		$this->shipping_preference_factory = Mockery::mock( ShippingPreferenceFactory::class );
		$this->session_handler             = Mockery::mock( SessionHandler::class );
		$this->logger                      = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		$this->endpoint = new CreateVaultOrderEndpoint(
			$this->request_data,
			$this->order_endpoint,
			$this->purchase_unit_factory,
			$this->shipping_preference_factory,
			$this->session_handler,
			$this->logger
		);
	}

	/**
	 * Mocks WC()->session as an in-memory key/value store.
	 */
	private function mock_wc_session_store(): \WC_Session {
		$store = array();

		$wc_session = Mockery::mock( \WC_Session::class );
		$wc_session->shouldReceive( 'set' )->andReturnUsing(
			static function ( string $key, $value ) use ( &$store ): void {
				$store[ $key ] = $value;
			}
		);
		$wc_session->shouldReceive( 'get' )->andReturnUsing(
			static function ( string $key ) use ( &$store ) {
				return $store[ $key ] ?? null;
			}
		);

		$wc          = Mockery::mock();
		$wc->session = $wc_session;
		when( 'WC' )->justReturn( $wc );

		return $wc_session;
	}

	/**
	 * GIVEN a shopper opens the Vault Component paysheet and the server successfully
	 *   creates a PayPal order for the current cart
	 * WHEN handle_request() completes
	 * THEN the created order id is stored in WC()->session under `ppcp_vault_component_order`
	 */
	public function test_handle_request_flags_session_with_created_order_id_on_success(): void {
		when( 'get_current_user_id' )->justReturn( 0 );

		$this->request_data->shouldReceive( 'read_request' )->with( CreateVaultOrderEndpoint::nonce() )->once();

		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$this->purchase_unit_factory->shouldReceive( 'from_wc_cart' )->andReturn( $purchase_unit );
		$this->shipping_preference_factory->shouldReceive( 'from_state' )->with( $purchase_unit, 'checkout' )->andReturn( 'GET_FROM_FILE' );

		$paypal_order = Mockery::mock( Order::class );
		$paypal_order->shouldReceive( 'id' )->andReturn( 'PAYPAL-ORDER-123' );
		$this->order_endpoint->shouldReceive( 'create' )->andReturn( $paypal_order );

		$this->session_handler->shouldReceive( 'replace_order' )->once()->with( $paypal_order );

		$wc_session = $this->mock_wc_session_store();

		expect( 'wp_send_json_success' )->once()->with( array( 'id' => 'PAYPAL-ORDER-123' ) );

		$this->endpoint->handle_request();

		$this->assertSame( 'PAYPAL-ORDER-123', $wc_session->get( 'ppcp_vault_component_order' ) );
	}

	/**
	 * GIVEN the order-creation call to PayPal fails
	 * WHEN handle_request() catches the resulting exception
	 * THEN the `ppcp_vault_component_order` session flag is never set
	 */
	public function test_handle_request_does_not_flag_session_when_order_creation_fails(): void {
		when( 'get_current_user_id' )->justReturn( 0 );

		$this->request_data->shouldReceive( 'read_request' )->with( CreateVaultOrderEndpoint::nonce() )->once();

		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$this->purchase_unit_factory->shouldReceive( 'from_wc_cart' )->andReturn( $purchase_unit );
		$this->shipping_preference_factory->shouldReceive( 'from_state' )->with( $purchase_unit, 'checkout' )->andReturn( 'GET_FROM_FILE' );

		$this->order_endpoint->shouldReceive( 'create' )->andThrow( new \Exception( 'order creation failed' ) );

		$this->session_handler->shouldReceive( 'replace_order' )->never();

		$wc_session = $this->mock_wc_session_store();

		expect( 'wp_send_json_error' )->once();
		expect( 'wp_send_json_success' )->never();

		$this->endpoint->handle_request();

		$this->assertNull( $wc_session->get( 'ppcp_vault_component_order' ) );
	}

	public function testStripRequestBodyReducesPurchaseUnitsToAmount(): void {
		$data = array(
			'purchase_units' => array(
				array(
					'amount'      => array( 'value' => '10.00' ),
					'items'       => array( array( 'name' => 'A' ) ),
					'description' => 'something',
				),
			),
		);

		$result = $this->endpoint->strip_request_body( $data );

		$this->assertSame(
			array( array( 'amount' => array( 'value' => '10.00' ) ) ),
			$result['purchase_units']
		);
	}

	public function testStripRequestBodyRemovesPayer(): void {
		$data = array( 'payer' => array( 'email_address' => 'buyer@example.com' ) );

		$result = $this->endpoint->strip_request_body( $data );

		$this->assertArrayNotHasKey( 'payer', $result );
	}

	public function testStripRequestBodyRemovesPaypalTokenFromArrayShape(): void {
		$data = array(
			'payment_source' => array(
				'paypal' => array(
					'token'              => array( 'id' => 'tok_1' ),
					'experience_context' => array( 'return_url' => 'https://example.com' ),
				),
			),
		);

		$result = $this->endpoint->strip_request_body( $data );

		$this->assertArrayNotHasKey( 'token', $result['payment_source']['paypal'] );
		$this->assertArrayHasKey( 'experience_context', $result['payment_source']['paypal'] );
	}

	public function testStripRequestBodyRemovesPaypalTokenFromObjectShape(): void {
		$paypal        = (object) array(
			'token'              => (object) array( 'id' => 'tok_1' ),
			'experience_context' => (object) array( 'return_url' => 'https://example.com' ),
		);
		$data          = array( 'payment_source' => array( 'paypal' => $paypal ) );

		$result = $this->endpoint->strip_request_body( $data );

		$this->assertFalse( property_exists( $result['payment_source']['paypal'], 'token' ) );
		$this->assertTrue( property_exists( $result['payment_source']['paypal'], 'experience_context' ) );
	}

	public function testStripRequestBodyLeavesUnrelatedDataUntouched(): void {
		$data = array( 'intent' => 'CAPTURE' );

		$result = $this->endpoint->strip_request_body( $data );

		$this->assertSame( array( 'intent' => 'CAPTURE' ), $result );
	}
}
