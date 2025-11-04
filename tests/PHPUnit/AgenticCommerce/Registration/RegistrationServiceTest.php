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
 * @covers RegistrationService
 */
class RegistrationServiceTest extends TestCase {

	private ConnectionState $connection_state;
	private MerchantMetadataProvider $metadata_provider;

	public function setUp(): void {
		parent::setUp();

		$this->connection_state  = Mockery::mock( ConnectionState::class );
		$this->metadata_provider = Mockery::mock( MerchantMetadataProvider::class );
	}

	private function create_testable_service( bool $has_token = false ): TestableRegistrationService {
		return new TestableRegistrationService(
			$this->connection_state,
			$this->metadata_provider,
			$has_token ? 'stored-token' : false
		);
	}

	private function stub_metadata(): MerchantMetadata {
		$metadata = new MerchantMetadata(
			'Test Store',
			'https://example.com',
			'US',
			'USD',
			'MERCHANT123',
			'https://example.com/catalog.json'
		);

		$this->metadata_provider->allows( 'get_metadata' )
			->andReturn( $metadata );

		return $metadata;
	}

	public function test_is_registered_returns_false_without_token(): void {
		$testee = $this->create_testable_service( false );

		$result = $testee->is_registered();

		$this->assertFalse( $result );
	}

	public function test_is_registered_returns_true_with_token(): void {
		$testee = $this->create_testable_service( true );

		$result = $testee->is_registered();

		$this->assertTrue( $result );
	}

	public function test_is_registered_returns_true_after_successful_registration(): void {
		$this->stub_metadata();
		$this->connection_state->allows( 'is_production' )->andReturn( false );

		when( 'wp_remote_post' )->returnArg();
		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'success' => true,
					'message' => 'Registration successful',
				)
			)
		);

		$testee = $this->create_testable_service( false );

		$this->assertFalse( $testee->is_registered() );

		$testee->register();

		$this->assertTrue( $testee->is_registered() );
	}

	public function test_is_registered_returns_false_after_deregistration(): void {
		$this->connection_state->allows( 'is_production' )->andReturn( false );

		when( 'wp_remote_post' )->returnArg();
		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'success' => true,
					'message' => 'Deregistration successful',
				)
			)
		);

		$testee = $this->create_testable_service( true );

		$this->assertTrue( $testee->is_registered() );

		$testee->deregister();

		$this->assertFalse( $testee->is_registered() );
	}

	public function test_deregister_returns_null_when_not_registered(): void {
		$testee = $this->create_testable_service( false );

		$result = $testee->deregister();

		$this->assertNull( $result );
	}

	/**
	 * @dataProvider registration_outcomes_provider
	 */
	public function test_register_outcomes( bool $success, string $error_code = null ): void {
		$this->stub_metadata();
		$this->connection_state->allows( 'is_production' )->andReturn( false );

		when( 'wp_remote_post' )->returnArg();
		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'success' => $success,
					'message' => $success ? 'Registration successful' : 'Registration failed',
					'error'   => $success ? null : 'Error details',
				)
			)
		);

		$testee = $this->create_testable_service( false );
		$result = $testee->register();

		if ( $success ) {
			$this->assertInstanceOf( RegistrationResult::class, $result );
			$this->assertTrue( $result->success );
			$this->assertTrue( $testee->was_token_saved );
		} else {
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( $error_code, $result->get_error_code() );
			$this->assertFalse( $testee->was_token_saved );
		}
	}

	public function registration_outcomes_provider(): array {
		return array(
			'success' => array( true ),
			'failure' => array( false, 'registration_failed' ),
		);
	}

	public function test_register_webhook_network_error(): void {
		$this->stub_metadata();
		$this->connection_state->allows( 'is_production' )->andReturn( false );

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

	public function test_register_json_parsing_error(): void {
		$this->stub_metadata();
		$this->connection_state->allows( 'is_production' )->andReturn( false );

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
	 * @dataProvider deregistration_outcomes_provider
	 */
	public function test_deregister_outcomes( bool $success, string $error_code = null ): void {
		$this->connection_state->allows( 'is_production' )->andReturn( false );

		when( 'wp_remote_post' )->returnArg();
		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'success' => $success,
					'message' => $success ? 'Deregistration successful' : 'Deregistration failed',
					'error'   => $success ? null : 'Token not found',
				)
			)
		);

		$testee = $this->create_testable_service( true );
		$result = $testee->deregister();

		if ( $success ) {
			$this->assertInstanceOf( RegistrationResult::class, $result );
			$this->assertTrue( $result->success );
		} else {
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( $error_code, $result->get_error_code() );
		}

		$this->assertTrue( $testee->was_token_deleted );
	}

	public function deregistration_outcomes_provider(): array {
		return array(
			'success' => array( true ),
			'failure' => array( false, 'deregistration_failed' ),
		);
	}

	/**
	 * @dataProvider environment_urls_provider
	 */
	public function test_uses_correct_environment_url( bool $is_production, string $expected_url ): void {
		$this->connection_state->allows( 'is_production' )->andReturn( $is_production );

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

	public function environment_urls_provider(): array {
		return array(
			'production' => array( true, 'https://d.joinhoney.com/webhooks/ws/uninstall' ),
			'sandbox'    => array( false, 'https://d-sandbox.joinhoney.com/webhooks/ws/uninstall' ),
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
