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
	private RegistrationService $testee;

	public function setUp(): void {
		parent::setUp();

		$this->connection_state  = Mockery::mock( ConnectionState::class );
		$this->metadata_provider = Mockery::mock( MerchantMetadataProvider::class );

		$this->testee = new RegistrationService(
			$this->connection_state,
			$this->metadata_provider
		);
	}

	public function test_is_registered_returns_false(): void {
		$result = $this->testee->is_registered();

		$this->assertFalse( $result );
	}

	public function test_deregister_returns_null_when_not_registered(): void {
		$testee = Mockery::mock( RegistrationService::class, array( $this->connection_state, $this->metadata_provider ) )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$testee->shouldReceive( 'get_registration_token' )
			->once()
			->andReturn( false );

		$result = $testee->deregister();

		$this->assertNull( $result );
	}

	public function test_register_success(): void {
		$metadata = new MerchantMetadata(
			'Test Store',
			'https://example.com',
			'US',
			'USD',
			'MERCHANT123',
			'https://example.com/catalog.json'
		);

		$this->metadata_provider->shouldReceive( 'get_metadata' )
			->once()
			->andReturn( $metadata );

		$this->connection_state->shouldReceive( 'is_production' )
			->once()
			->andReturn( false );

		when( 'wp_remote_post' )->returnArg();
		when( 'is_wp_error' )->returnArg( false );
		when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'success' => true,
					'message' => 'Registration successful',
				)
			)
		);

		$testee = Mockery::mock( RegistrationService::class, array( $this->connection_state, $this->metadata_provider ) )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$testee->shouldReceive( 'get_registration_token' )
			->once()
			->andReturn( false );

		$testee->shouldReceive( 'delete_registration_token' )
			->once();

		$testee->shouldReceive( 'save_registration_token' )
			->once()
			->with( Mockery::type( 'string' ) );

		$result = $testee->register();

		$this->assertInstanceOf( RegistrationResult::class, $result );
		$this->assertTrue( $result->success );
		$this->assertSame( 'Registration successful', $result->message );
	}

	public function test_register_failure(): void {
		$metadata = new MerchantMetadata(
			'Test Store',
			'https://example.com',
			'US',
			'USD',
			'MERCHANT123',
			'https://example.com/catalog.json'
		);

		$this->metadata_provider->shouldReceive( 'get_metadata' )
			->once()
			->andReturn( $metadata );

		$this->connection_state->shouldReceive( 'is_production' )
			->once()
			->andReturn( false );

		when( 'wp_remote_post' )->returnArg();
		when( 'is_wp_error' )->returnArg( false );
		when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode(
				array(
					'success' => false,
					'message' => 'Registration rejected',
					'error'   => 'Invalid merchant data',
				)
			)
		);

		$testee = Mockery::mock( RegistrationService::class, array( $this->connection_state, $this->metadata_provider ) )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$testee->shouldReceive( 'get_registration_token' )
			->once()
			->andReturn( false );

		$testee->shouldReceive( 'delete_registration_token' )
			->twice();

		$result = $testee->register();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'registration_failed', $result->get_error_code() );
		$this->assertSame( 'Invalid merchant data', $result->get_error_message() );
	}
}
