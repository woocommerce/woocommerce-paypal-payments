<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Registration;

use Mockery;
use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadata;
use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadataProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
use WP_Error;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationService
 */
class RegistrationServiceTest extends TestCase {

	private ConnectionState $connection_state;
	private MerchantMetadataProvider $metadata_provider;

	public function setUp(): void {
		parent::setUp();

		$this->connection_state  = $this->createStub( ConnectionState::class );
		$this->metadata_provider = $this->createStub( MerchantMetadataProvider::class );
	}

	private function create_testable_service( bool $has_token = false ): TestableRegistrationService {
		return new TestableRegistrationService(
			$this->connection_state,
			$this->metadata_provider,
			$has_token ? 'stored-token' : false
		);
	}

	private function stub_merchant_metadata(): MerchantMetadata {
		$metadata = new MerchantMetadata(
			'Test Store',
			'https://example.com',
			'US',
			'USD',
			'MERCHANT123',
			'https://example.com/catalog.json',
			'US'
		);

		$this->metadata_provider->method( 'get_metadata' )->willReturn( $metadata );

		return $metadata;
	}

	private function stub_successful_webhook_response(): void {
		when( 'wp_remote_post' )->returnArg();
		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'success' => true,
					'message' => 'Operation successful',
				)
			)
		);
	}

	private function stub_failed_webhook_response( string $error_message ): void {
		when( 'wp_remote_post' )->returnArg();
		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'success' => false,
					'message' => 'Operation failed',
					'error'   => $error_message,
				)
			)
		);
	}

	/**
	 * GIVEN a store without a registration token
	 * WHEN checking registration status
	 * THEN the store should not be considered registered
	 */
	public function test_store_is_not_registered_without_token(): void {
		$testee = $this->create_testable_service( false );

		$result = $testee->is_registered();

		$this->assertFalse( $result );
	}

	/**
	 * GIVEN a store with a valid registration token
	 * WHEN checking registration status
	 * THEN the store should be considered registered
	 */
	public function test_store_is_registered_with_token(): void {
		$testee = $this->create_testable_service( true );

		$result = $testee->is_registered();

		$this->assertTrue( $result );
	}

	/**
	 * GIVEN an unregistered store with valid merchant metadata
	 * WHEN registering with PayPal Agentic Commerce
	 * AND the webhook returns success
	 * THEN registration should succeed with valid result
	 * AND the registration token should be persisted
	 * AND the store should be marked as registered
	 */
	public function test_registration_succeeds_with_valid_merchant_data(): void {
		$this->stub_merchant_metadata();
		$this->connection_state->method( 'is_production' )->willReturn( false );
		$this->stub_successful_webhook_response();

		$testee = $this->create_testable_service( false );

		$this->assertFalse( $testee->is_registered() );

		$result = $testee->register();

		$this->assertInstanceOf( RegistrationResult::class, $result );
		$this->assertTrue( $result->success );
		$this->assertTrue( $testee->was_token_saved );
		$this->assertTrue( $testee->is_registered() );
	}

	/**
	 * GIVEN an unregistered store
	 * WHEN registering with PayPal Agentic Commerce
	 * AND the webhook returns a failure response
	 * THEN registration should fail with error
	 * AND the registration token should not be saved
	 */
	public function test_registration_fails_when_webhook_returns_error(): void {
		$this->stub_merchant_metadata();
		$this->connection_state->method( 'is_production' )->willReturn( false );
		$this->stub_failed_webhook_response( 'Invalid merchant data' );

		$testee = $this->create_testable_service( false );
		$result = $testee->register();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'registration_failed', $result->get_error_code() );
		$this->assertSame( 'Invalid merchant data', $result->get_error_message() );
		$this->assertFalse( $testee->was_token_saved );
	}

	/**
	 * GIVEN an already registered store
	 * WHEN attempting to register again
	 * THEN registration should fail with appropriate error
	 */
	public function test_registration_fails_when_already_registered(): void {
		$testee = $this->create_testable_service( true );

		$result = $testee->register();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'registration_failed', $result->get_error_code() );
		$this->assertSame( 'Already registered', $result->get_error_message() );
	}

	/**
	 * GIVEN an unregistered store
	 * WHEN registering with PayPal Agentic Commerce
	 * AND the webhook request encounters a network error
	 * THEN registration should fail with network error details
	 */
	public function test_registration_fails_on_webhook_network_error(): void {
		$this->stub_merchant_metadata();
		$this->connection_state->method( 'is_production' )->willReturn( false );

		$wp_error = new WP_Error( 'http_request_failed', 'Connection timeout' );

		when( 'wp_remote_post' )->justReturn( $wp_error );
		when( 'is_wp_error' )->alias(
			function ( $thing ) {
				return $thing instanceof WP_Error;
			}
		);

		$testee = $this->create_testable_service( false );
		$result = $testee->register();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'webhook_request_failed', $result->get_error_code() );
		$this->assertSame( 'Connection timeout', $result->get_error_message() );
	}

	/**
	 * GIVEN an unregistered store
	 * WHEN registering with PayPal Agentic Commerce
	 * AND the webhook returns invalid JSON response
	 * THEN registration should fail with JSON parsing error
	 */
	public function test_registration_fails_on_invalid_json_response(): void {
		$this->stub_merchant_metadata();
		$this->connection_state->method( 'is_production' )->willReturn( false );

		when( 'wp_remote_post' )->returnArg();
		when( 'is_wp_error' )->alias(
			function ( $thing ) {
				return $thing instanceof WP_Error;
			}
		);
		when( 'wp_remote_retrieve_body' )->justReturn( 'invalid json {[' );

		$testee = $this->create_testable_service( false );
		$result = $testee->register();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'webhook_response_failed', $result->get_error_code() );
	}

	/**
	 * GIVEN a registered store
	 * WHEN deregistering from PayPal Agentic Commerce
	 * AND the webhook returns success
	 * THEN deregistration should succeed with valid result
	 * AND the registration token should be deleted
	 * AND the store should no longer be registered
	 */
	public function test_deregistration_succeeds_for_registered_store(): void {
		$this->connection_state->method( 'is_production' )->willReturn( false );
		$this->stub_successful_webhook_response();

		$testee = $this->create_testable_service( true );

		$this->assertTrue( $testee->is_registered() );

		$result = $testee->deregister();

		$this->assertInstanceOf( RegistrationResult::class, $result );
		$this->assertTrue( $result->success );
		$this->assertTrue( $testee->was_token_deleted );
		$this->assertFalse( $testee->is_registered() );
	}

	/**
	 * GIVEN a registered store
	 * WHEN deregistering from PayPal Agentic Commerce
	 * AND the webhook returns a failure response
	 * THEN deregistration should fail with error
	 * AND the registration token should still be deleted locally
	 */
	public function test_deregistration_fails_when_webhook_returns_error(): void {
		$this->connection_state->method( 'is_production' )->willReturn( false );
		$this->stub_failed_webhook_response( 'Token not found' );

		$testee = $this->create_testable_service( true );
		$result = $testee->deregister();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'deregistration_failed', $result->get_error_code() );
		$this->assertSame( 'Token not found', $result->get_error_message() );
		$this->assertTrue( $testee->was_token_deleted );
	}

	/**
	 * GIVEN an unregistered store
	 * WHEN attempting to deregister
	 * THEN deregistration should return null without making API calls
	 */
	public function test_deregistration_returns_null_when_not_registered(): void {
		$testee = $this->create_testable_service( false );

		$result = $testee->deregister();

		$this->assertNull( $result );
	}

	/**
	 * GIVEN a registered store in production mode
	 * WHEN deregistering from PayPal Agentic Commerce
	 * THEN the production webhook URL should be called
	 *
	 * @dataProvider environment_webhook_urls_provider
	 */
	public function test_uses_correct_webhook_url_for_environment( bool $is_production, string $expected_url ): void {
		$this->connection_state->method( 'is_production' )->willReturn( $is_production );

		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'success' => true,
					'message' => 'Success',
				)
			)
		);

		expect( 'wp_remote_post' )
			->with( $expected_url, Mockery::type( 'array' ) )
			->andReturn( array() );

		$testee = $this->create_testable_service( true );
		$result = $testee->deregister();

		$this->assertInstanceOf( RegistrationResult::class, $result );
		$this->assertTrue( $result->success );
	}

	public function environment_webhook_urls_provider(): array {
		return array(
			'production environment uses live webhook URL' => array(
				true,
				'https://d.joinhoney.com/webhooks/ws/uninstall',
			),
			'sandbox environment uses sandbox webhook URL' => array(
				false,
				'https://d-sandbox.joinhoney.com/webhooks/ws/uninstall',
			),
		);
	}
}

/**
 * Testable version of RegistrationService that exposes protected methods.
 */
class TestableRegistrationService extends RegistrationService {

	public bool $was_token_saved = false;
	public bool $was_token_deleted = false;

	/**
	 * @var string|false
	 */
	private $stored_token;

	public function __construct( $connection_state, $metadata_provider, $initial_token ) {
		parent::__construct( $connection_state, $metadata_provider );
		$this->stored_token = $initial_token;
	}

	protected function get_registration_token() {
		return $this->stored_token;
	}

	protected function save_registration_token( string $token ): void {
		$this->was_token_saved = true;
		$this->stored_token    = $token;
	}

	protected function delete_registration_token(): void {
		$this->was_token_deleted = true;
		$this->stored_token      = false;
	}
}
