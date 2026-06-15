<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class CaptureCardPaymentTest extends TestCase {

	/**
	 * Build a fully-wired CaptureCardPayment instance.
	 *
	 * @param string $three_d_secure_enum Value returned by settings_provider->three_d_secure_enum().
	 * @param array  &$captured_body      Passed by reference; populated with the decoded request body inside the wp_remote_get stub.
	 */
	private function make_testee( string $three_d_secure_enum, array &$captured_body ): CaptureCardPayment {
		$token = Mockery::mock( \WooCommerce\PayPalCommerce\ApiClient\Entity\Token::class );
		$token->shouldReceive( 'token' )->andReturn( 'test-bearer-token' );

		$bearer = Mockery::mock( Bearer::class );
		$bearer->shouldReceive( 'bearer' )->andReturn( $token );

		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'to_array' )->andReturn( array() );

		$purchase_unit_factory = Mockery::mock( PurchaseUnitFactory::class );
		$purchase_unit_factory->shouldReceive( 'from_wc_order' )->andReturn( $purchase_unit );

		$returned_order = Mockery::mock( Order::class );

		$order_factory = Mockery::mock( OrderFactory::class );
		$order_factory->shouldReceive( 'from_paypal_response' )->andReturn( $returned_order );

		$settings_provider = Mockery::mock( SettingsProvider::class );
		$settings_provider->shouldReceive( 'payment_intent' )->andReturn( 'capture' );
		$settings_provider->shouldReceive( 'three_d_secure_enum' )->andReturn( $three_d_secure_enum );

		$experience_context = Mockery::mock( ExperienceContext::class );
		$experience_context->shouldReceive( 'to_array' )->andReturn(
			array(
				'return_url' => 'https://example.com/return',
				'cancel_url' => 'https://example.com/cancel',
			)
		);

		$experience_context_builder = Mockery::mock( ExperienceContextBuilder::class );
		$experience_context_builder->shouldReceive( 'with_custom_return_url' )->andReturnSelf();
		$experience_context_builder->shouldReceive( 'with_custom_cancel_url' )->andReturnSelf();
		$experience_context_builder->shouldReceive( 'build' )->andReturn( $experience_context );

		$logger = Mockery::mock( LoggerInterface::class );
		$logger->shouldReceive( 'debug' )->zeroOrMoreTimes();

		when( 'home_url' )->alias( function ( string $path = '' ): string {
			return 'https://example.com' . $path;
		} );
		when( 'add_query_arg' )->alias( function ( string $key, $value, string $url ): string {
			return $url . '&' . $key . '=' . $value;
		} );
		when( 'wc_get_checkout_url' )->justReturn( 'https://example.com/checkout/' );
		when( 'trailingslashit' )->alias( function ( string $value ): string {
			return rtrim( $value, '/' ) . '/';
		} );
		expect( 'wp_json_encode' )->andReturnUsing( 'json_encode' );
		when( 'apply_filters' )->alias( function ( string $hook, ...$args ) {
			return $args[0] ?? null;
		} );

		// RequestTrait::request_response_string() calls $response['headers']->getAll(), so headers must be an object.
		$headers_stub = Mockery::mock( \Requests_Utility_CaseInsensitiveDictionary::class );
		$headers_stub->shouldReceive( 'getAll' )->andReturn( array() );

		expect( 'wp_remote_get' )->andReturnUsing(
			function ( string $url, array $args ) use ( &$captured_body, $headers_stub ): array {
				$captured_body = (array) json_decode( $args['body'], true );
				return array(
					'body'     => '{}',
					'response' => array( 'code' => 200 ),
					'headers'  => $headers_stub,
				);
			}
		);

		return new CaptureCardPayment(
			'https://api.paypal.com',
			$bearer,
			$order_factory,
			$purchase_unit_factory,
			$settings_provider,
			$experience_context_builder,
			$logger
		);
	}

	/**
	 * GIVEN a vaulted card vault_id is passed to create_order
	 * WHEN the HTTP request body is built
	 * THEN payment_source.card.vault_id in the body equals the passed vault_id
	 * AND stored_credential.payment_initiator equals 'CUSTOMER'
	 *
	 * @dataProvider three_d_secure_enum_provider
	 */
	public function test_create_order_includes_vault_id_and_stored_credential(
		string $three_d_secure_enum
	): void {
		$captured_body = array();
		$testee        = $this->make_testee( $three_d_secure_enum, $captured_body );
		$wc_order      = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );

		$testee->create_order( 'vault-abc-123', $wc_order );

		$this->assertSame(
			'vault-abc-123',
			$captured_body['payment_source']['card']['vault_id'],
			'vault_id must be forwarded verbatim into the request body'
		);
		$this->assertSame(
			'CUSTOMER',
			$captured_body['payment_source']['card']['stored_credential']['payment_initiator'],
			'stored_credential.payment_initiator must be CUSTOMER for vaulted card charges'
		);
	}

	/**
	 * GIVEN the merchant has configured a 3DS setting (SCA_WHEN_REQUIRED or SCA_ALWAYS)
	 * WHEN create_order builds the PayPal API request for a vaulted card
	 * THEN payment_source.card.attributes.verification.method equals the configured enum value
	 *
	 * @dataProvider three_d_secure_enum_with_verification_provider
	 */
	public function test_create_order_includes_3ds_verification_when_configured(
		string $three_d_secure_enum,
		string $expected_method
	): void {
		$captured_body = array();
		$testee        = $this->make_testee( $three_d_secure_enum, $captured_body );
		$wc_order      = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );

		$testee->create_order( 'vault-abc-123', $wc_order );

		$this->assertSame(
			$expected_method,
			$captured_body['payment_source']['card']['attributes']['verification']['method'] ?? null,
			"Request body must include payment_source.card.attributes.verification.method={$expected_method} for 3DS setting '{$three_d_secure_enum}'"
		);
	}

	/**
	 * GIVEN the merchant has disabled 3DS (empty enum value)
	 * WHEN create_order builds the PayPal API request for a vaulted card
	 * THEN payment_source.card has no 'attributes' key (no verification sent)
	 */
	public function test_create_order_omits_attributes_when_3ds_disabled(): void {
		$captured_body = array();
		$testee        = $this->make_testee( '', $captured_body );
		$wc_order      = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );

		$testee->create_order( 'vault-abc-123', $wc_order );

		$this->assertArrayNotHasKey(
			'attributes',
			$captured_body['payment_source']['card'],
			'When 3DS is disabled the request body must not contain payment_source.card.attributes'
		);
	}

	/**
	 * GIVEN a vaulted card charge is built by create_order
	 * WHEN the request body is inspected
	 * THEN payment_source.card.experience_context exists and is a non-empty array
	 * AND experience_context.return_url is a non-empty string
	 */
	public function test_create_order_includes_experience_context_with_return_url(): void {
		$captured_body = array();
		$testee        = $this->make_testee( 'SCA_WHEN_REQUIRED', $captured_body );
		$wc_order      = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );

		$testee->create_order( 'vault-abc-123', $wc_order );

		$this->assertTrue(
			array_key_exists( 'experience_context', $captured_body['payment_source']['card'] ),
			'create_order() must include payment_source.card.experience_context with a return_url'
		);
		$this->assertNotEmpty(
			$captured_body['payment_source']['card']['experience_context']['return_url'] ?? null,
			'create_order() must include payment_source.card.experience_context with a return_url'
		);
	}

	public function three_d_secure_enum_provider(): array {
		return array(
			'SCA_WHEN_REQUIRED setting' => array( 'SCA_WHEN_REQUIRED' ),
			'SCA_ALWAYS setting'        => array( 'SCA_ALWAYS' ),
			'No 3DS setting'            => array( '' ),
		);
	}

	public function three_d_secure_enum_with_verification_provider(): array {
		return array(
			'SCA_WHEN_REQUIRED maps to verification method SCA_WHEN_REQUIRED' => array( 'SCA_WHEN_REQUIRED', 'SCA_WHEN_REQUIRED' ),
			'SCA_ALWAYS maps to verification method SCA_ALWAYS'               => array( 'SCA_ALWAYS', 'SCA_ALWAYS' ),
		);
	}
}
