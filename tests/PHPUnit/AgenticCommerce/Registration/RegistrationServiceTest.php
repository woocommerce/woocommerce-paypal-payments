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
}
