<?php
declare( strict_types=1 );

namespace PHPUnit\Settings\Endpoint;

use Mockery;
use WP_REST_Request;
use WP_REST_Response;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Endpoint\RefreshFeatureStatusEndpoint;
use WooCommerce\PayPalCommerce\Settings\Service\SellerTypeResolver;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Settings\Endpoint\RefreshFeatureStatusEndpoint
 */
class RefreshFeatureStatusEndpointTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		when( 'do_action' )->justReturn( null );
		when( 'rest_ensure_response' )->alias( static fn( $data ) => new WP_REST_Response( $data ) );
	}

	public function test_refresh_triggers_seller_type_resolution(): void {
		$cache = Mockery::mock( Cache::class );
		$cache->shouldReceive( 'get' )->andReturn( 0 ); // No recent request.
		$cache->shouldReceive( 'set' )->andReturn( true );

		$resolver          = Mockery::mock( SellerTypeResolver::class );
		$general_settings  = Mockery::mock( GeneralSettings::class );
		$partners_endpoint = Mockery::mock( PartnersEndpoint::class );
		$logger            = Mockery::mock( LoggerInterface::class );
		$logger->shouldReceive( 'info' );

		// The whole point of the change: refresh re-resolves the seller type.
		$resolver->shouldReceive( 'resolve_unknown_seller_type' )
			->once()
			->with( $general_settings, $partners_endpoint, $logger );

		$endpoint = new RefreshFeatureStatusEndpoint(
			$cache,
			$logger,
			$resolver,
			$general_settings,
			$partners_endpoint
		);

		$response = $endpoint->refresh_status( new WP_REST_Request( 'POST', '/refresh-features' ) );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
	}

	public function test_refresh_throttle_blocks_resolution(): void {
		$cache = Mockery::mock( Cache::class );
		$cache->shouldReceive( 'get' )->andReturn( time() ); // A request just happened.
		$cache->shouldNotReceive( 'set' );

		$resolver          = Mockery::mock( SellerTypeResolver::class );
		$general_settings  = Mockery::mock( GeneralSettings::class );
		$partners_endpoint = Mockery::mock( PartnersEndpoint::class );
		$logger            = Mockery::mock( LoggerInterface::class );

		// Within the throttle window, no resolution (and thus no API call) happens.
		$resolver->shouldNotReceive( 'resolve_unknown_seller_type' );

		$endpoint = new RefreshFeatureStatusEndpoint(
			$cache,
			$logger,
			$resolver,
			$general_settings,
			$partners_endpoint
		);

		$response = $endpoint->refresh_status( new WP_REST_Request( 'POST', '/refresh-features' ) );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
	}
}
