<?php
declare( strict_types=1 );

namespace PHPUnit\PpcpSettings\Service;

use Mockery;
use Psr\Log\AbstractLogger;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\LoginSeller;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayPalBearerFactory;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\Settings\DTO\OAuthConnectionDTO;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
use WooCommerce\PayPalCommerce\Settings\Service\AuthenticationManager;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
use WooCommerce\PayPalCommerce\WcGateway\Helper\EnvironmentConfig;

use function Brain\Monkey\Functions\when;

/**
 * Checks which connection details are written to the log.
 */
class AuthenticationManagerLoggingTest extends TestCase {
	private const CLIENT_VALUE  = 'CLIENT-VALUE-9f8e7d6c5b4a';
	private const ONE_TIME_CODE ='ONE-TIME-CODE-1a2b3c4d';

	private object $logger;
	private GeneralSettings $settings;
	private $login_endpoint;

	public function setUp(): void {
		parent::setUp();

		$this->logger = new class() extends AbstractLogger {
			/** @var array<int, array{message: string, context: array}> */
			public array $records = array();

			public function log( $level, $message, array $context = array() ): void {
				$this->records[] = array(
					'message' => (string) $message,
					'context' => $context,
				);
			}
		};

		$this->settings       = Mockery::mock( GeneralSettings::class )->shouldIgnoreMissing();
		$this->login_endpoint = Mockery::mock( EnvironmentConfig::class );
	}

	private function create_sut(): AuthenticationManager {
		return new AuthenticationManager(
			$this->settings,
			Mockery::mock( EnvironmentConfig::class ),
			$this->login_endpoint,
			Mockery::mock( ConnectionState::class )->shouldIgnoreMissing(),
			Mockery::mock( PayPalBearerFactory::class ),
			$this->logger
		);
	}

	private function dump_logs(): string {
		return print_r( $this->logger->records, true );
	}

	/**
	 * GIVEN a valid stored one-time OAuth connection and PayPal returning client credentials
	 * WHEN the OAuth authentication is completed
	 * THEN the log context holds exactly the listed connection fields, in order
	 * AND the merchant ID and client ID are logged
	 * AND the returned client value is not in the log messages or contexts.
	 */
	public function test_oauth_connection_logs_only_listed_connection_fields(): void {
		$merchant_id = 'MERCHANT12345';

		when( 'get_option' )->justReturn( new OAuthConnectionDTO( true, 'shared-id', self::ONE_TIME_CODE ) );
		when( 'delete_option' )->justReturn( true );

		$login = Mockery::mock( LoginSeller::class );
		$login->shouldReceive( 'credentials_for' )->andReturn(
			(object) array(
				'client_id'     => 'client-id-value',
				'client_secret' => self::CLIENT_VALUE,
				'payer_id'      => $merchant_id,
			)
		);
		$this->login_endpoint->shouldReceive( 'get_value' )->andReturn( $login );

		$this->settings->shouldReceive( 'get_merchant_data' )->andReturn(
			new MerchantConnectionDTO( false, '', '', '' )
		);
		$this->settings->shouldReceive( 'is_merchant_connected' )->andReturn( false );

		$this->create_sut()->handle_oauth_authentication(
			array(
				'merchant_id'    => $merchant_id,
				'merchant_email' => 'merchant@example.com',
				'seller_type'    => SellerTypeEnum::BUSINESS,
				'seller_nonce'   => 'nonce',
			)
		);

		$entries = array_values(
			array_filter(
				$this->logger->records,
				static fn( array $r ) => 'Updating connection details' === $r['message']
			)
		);

		$this->assertCount( 1, $entries );
		$this->assertSame( $merchant_id, $entries[0]['context']['merchant_id'] );
		$this->assertSame( 'client-id-value', $entries[0]['context']['client_id'] );

		$this->assertSame(
			array( 'is_sandbox', 'client_id', 'merchant_id', 'merchant_email', 'merchant_country', 'seller_type' ),
			array_keys( $entries[0]['context'] )
		);

		$this->assertStringNotContainsString( self::CLIENT_VALUE, $this->dump_logs() );
	}

	/**
	 * GIVEN a merchant starting the OAuth login
	 * WHEN the one-time OAuth details are stored
	 * THEN the shared ID is logged
	 * AND the one-time code value is not in the log.
	 */
	public function test_remembering_oauth_details_logs_only_shared_id(): void {
		when( 'update_option' )->justReturn( true );

		$this->create_sut()->remember_oauth_connection_details( 'shared-id', self::ONE_TIME_CODE, true );

		$logs = $this->dump_logs();
		$this->assertStringContainsString( 'shared-id', $logs );
		$this->assertStringNotContainsString( self::ONE_TIME_CODE, $logs );
	}
}
