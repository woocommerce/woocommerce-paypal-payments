<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class CapturePayPalPaymentTest extends TestCase {

	/**
	 * Build a fully-wired CapturePayPalPayment instance.
	 *
	 * @param array       &$captured_body   Passed by reference; populated with the decoded request body inside the wp_remote_get stub.
	 * @param PurchaseUnit|null $purchase_unit The purchase unit returned by from_wc_order(); defaults to one whose to_array() is empty.
	 */
	private function make_testee( array &$captured_body, ?PurchaseUnit $purchase_unit = null ): array {
		$token = Mockery::mock( \WooCommerce\PayPalCommerce\ApiClient\Entity\Token::class );
		$token->shouldReceive( 'token' )->andReturn( 'test-bearer-token' );

		$bearer = Mockery::mock( Bearer::class );
		$bearer->shouldReceive( 'bearer' )->andReturn( $token );

		if ( ! $purchase_unit ) {
			$purchase_unit = Mockery::mock( PurchaseUnit::class );
			$purchase_unit->shouldReceive( 'to_array' )->andReturn( array() );
		}

		$purchase_unit_factory = Mockery::mock( PurchaseUnitFactory::class );
		$purchase_unit_factory->shouldReceive( 'from_wc_order' )->andReturn( $purchase_unit );
		$purchase_unit_factory->shouldNotReceive( 'from_wc_cart' );

		$returned_order = Mockery::mock( Order::class );

		$order_factory = Mockery::mock( OrderFactory::class );
		$order_factory->shouldReceive( 'from_paypal_response' )->andReturn( $returned_order );

		$settings_provider = Mockery::mock( SettingsProvider::class );
		$settings_provider->shouldReceive( 'payment_intent' )->andReturn( 'capture' );

		$logger = Mockery::mock( LoggerInterface::class );
		$logger->allows( 'debug' );

		when( 'trailingslashit' )->alias( function ( string $value ): string {
			return rtrim( $value, '/' ) . '/';
		} );
		expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );

		// RequestTrait::request_response_string() calls $response['headers']->getAll(), so headers must be an object.
		$headers_stub = Mockery::mock( \Requests_Utility_CaseInsensitiveDictionary::class );
		$headers_stub->shouldReceive( 'getAll' )->andReturn( array() );

		expect( 'wp_remote_get' )->andReturnUsing(
			function ( string $url, array $args ) use ( &$captured_body, $headers_stub ): array {
				$captured_body = (array) json_decode( $args['body'], true );
				return array(
					'body'     => '{"id":"MOCK-ORDER-ID"}',
					'response' => array( 'code' => 200 ),
					'headers'  => $headers_stub,
				);
			}
		);
		when( 'wp_remote_retrieve_response_code' )->alias(
			function ( $response ): int {
				return (int) ( $response['response']['code'] ?? 0 );
			}
		);

		$testee = new CapturePayPalPayment(
			'https://api.paypal.com',
			$bearer,
			$order_factory,
			$purchase_unit_factory,
			$settings_provider,
			$logger
		);

		return array( $testee, $returned_order );
	}

	/**
	 * GIVEN a saved-token payment is captured for a WC order
	 * WHEN create_order builds the purchase units
	 * THEN they come from PurchaseUnitFactory::from_wc_order(), never from_wc_cart()
	 *
	 * This is the PCP-6702 regression: from_wc_cart() always produces an
	 * empty invoice_id and a session-based custom_id, so the order must be
	 * the single source of truth here regardless of the current request
	 * (there is no more `?pay_for_order=1` special case).
	 */
	public function test_create_order_builds_purchase_unit_from_wc_order(): void {
		$captured_body = array();
		[ $testee ]    = $this->make_testee( $captured_body );

		$wc_order = Mockery::mock( WC_Order::class );

		// No assertion needed beyond make_testee()'s `shouldNotReceive('from_wc_cart')`
		// and `shouldReceive('from_wc_order')` expectations; Mockery fails the test
		// if either is violated.
		$testee->create_order( 'vault-abc-123', $wc_order );

		$this->assertArrayHasKey( 'purchase_units', $captured_body );
	}

	/**
	 * GIVEN a saved-token payment is captured for a WC order
	 * WHEN the HTTP request body is built
	 * THEN it has no top-level custom_id or invoice_id keys
	 *
	 * The PayPal Orders v2 API only reads these fields from inside a
	 * purchase unit; leaving them at the request root (the PCP-6702 bug)
	 * means PayPal silently ignores them.
	 */
	public function test_create_order_does_not_send_top_level_custom_id_or_invoice_id(): void {
		$captured_body = array();
		[ $testee ]    = $this->make_testee( $captured_body );

		$wc_order = Mockery::mock( WC_Order::class );

		$testee->create_order( 'vault-abc-123', $wc_order );

		$this->assertArrayNotHasKey( 'custom_id', $captured_body );
		$this->assertArrayNotHasKey( 'invoice_id', $captured_body );
	}

	/**
	 * GIVEN PurchaseUnitFactory::from_wc_order() populates custom_id/invoice_id
	 * WHEN create_order serializes the purchase unit into the request body
	 * THEN purchase_units[0] carries those values through untouched
	 */
	public function test_create_order_forwards_purchase_unit_custom_id_and_invoice_id(): void {
		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'to_array' )->andReturn(
			array(
				'custom_id'  => '14121',
				'invoice_id' => 'PP-FLYERALARMDigital-422136',
			)
		);

		$captured_body = array();
		[ $testee ]    = $this->make_testee( $captured_body, $purchase_unit );

		$wc_order = Mockery::mock( WC_Order::class );

		$testee->create_order( 'vault-abc-123', $wc_order );

		$this->assertSame( '14121', $captured_body['purchase_units'][0]['custom_id'] ?? null );
		$this->assertSame( 'PP-FLYERALARMDigital-422136', $captured_body['purchase_units'][0]['invoice_id'] ?? null );
	}

	/**
	 * GIVEN a vault id and an explicit payment source name are passed to create_order
	 * WHEN the request body is built
	 * THEN payment_source is keyed by the given name and carries the vault_id
	 */
	public function test_create_order_forwards_vault_id_and_payment_source_name(): void {
		$captured_body = array();
		[ $testee ]    = $this->make_testee( $captured_body );

		$wc_order = Mockery::mock( WC_Order::class );

		$testee->create_order( 'vault-venmo-1', $wc_order, 'venmo' );

		$this->assertSame(
			'vault-venmo-1',
			$captured_body['payment_source']['venmo']['vault_id'] ?? null
		);
	}

	/**
	 * GIVEN create_order is called without an explicit payment source name
	 * WHEN the request body is built
	 * THEN payment_source defaults to 'paypal'
	 */
	public function test_create_order_defaults_payment_source_name_to_paypal(): void {
		$captured_body = array();
		[ $testee ]    = $this->make_testee( $captured_body );

		$wc_order = Mockery::mock( WC_Order::class );

		$testee->create_order( 'vault-abc-123', $wc_order );

		$this->assertArrayHasKey( 'paypal', $captured_body['payment_source'] ?? array() );
	}
}
