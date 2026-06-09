<?php
declare( strict_types=1 );

namespace PHPUnit\Settings\Service;

use Mockery;
use RuntimeException;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
use WooCommerce\PayPalCommerce\Settings\Service\SellerTypeResolver;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Settings\Service\SellerTypeResolver
 */
class SellerTypeResolverTest extends TestCase {

	private SellerTypeResolver $resolver;

	public function setUp(): void {
		parent::setUp();
		when( 'do_action' )->justReturn( null );
		$this->resolver = new SellerTypeResolver();
	}

	public function test_does_nothing_when_seller_type_is_already_known(): void {
		$general_settings  = Mockery::mock( GeneralSettings::class );
		$partners_endpoint = Mockery::mock( PartnersEndpoint::class );
		$logger            = Mockery::mock( LoggerInterface::class );

		$general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$general_settings->shouldReceive( 'get_merchant_data' )->andReturn(
			$this->merchant_data( SellerTypeEnum::BUSINESS )
		);

		// No API call, no persistence.
		$partners_endpoint->shouldNotReceive( 'seller_status' );
		$general_settings->shouldNotReceive( 'save' );

		$this->resolver->resolve_unknown_seller_type( $general_settings, $partners_endpoint, $logger );

		$this->assertTrue( true );
	}

	public function test_does_nothing_when_merchant_is_not_connected(): void {
		$general_settings  = Mockery::mock( GeneralSettings::class );
		$partners_endpoint = Mockery::mock( PartnersEndpoint::class );
		$logger            = Mockery::mock( LoggerInterface::class );

		$general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( false );

		// Disconnected merchant short-circuits everything.
		$partners_endpoint->shouldNotReceive( 'seller_status' );
		$general_settings->shouldNotReceive( 'save' );

		$this->resolver->resolve_unknown_seller_type( $general_settings, $partners_endpoint, $logger );

		$this->assertTrue( true );
	}

	public function test_resolves_business_and_persists_it(): void {
		$general_settings  = Mockery::mock( GeneralSettings::class );
		$partners_endpoint = Mockery::mock( PartnersEndpoint::class );
		$logger            = Mockery::mock( LoggerInterface::class );

		$general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$general_settings->shouldReceive( 'get_merchant_data' )->andReturn(
			$this->merchant_data( SellerTypeEnum::UNKNOWN )
		);

		$partners_endpoint->shouldReceive( 'seller_status' )->once()->andReturn(
			new SellerStatus(
				array(),
				array( new SellerStatusCapability( 'COMMERCIAL_ENTITY', SellerStatusCapability::STATUS_ACTIVE ) ),
				'DE'
			)
		);

		// The resolved business type must be persisted.
		$general_settings->shouldReceive( 'set_merchant_data' )->once()->with(
			Mockery::on(
				static fn( $dto ) => $dto instanceof MerchantConnectionDTO && SellerTypeEnum::BUSINESS === $dto->seller_type
			)
		);
		$general_settings->shouldReceive( 'save' )->once();

		$this->resolver->resolve_unknown_seller_type( $general_settings, $partners_endpoint, $logger );

		$this->assertTrue( true );
	}

	public function test_does_not_persist_when_api_call_throws(): void {
		$general_settings  = Mockery::mock( GeneralSettings::class );
		$partners_endpoint = Mockery::mock( PartnersEndpoint::class );
		$logger            = Mockery::mock( LoggerInterface::class );

		$general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$general_settings->shouldReceive( 'get_merchant_data' )->andReturn(
			$this->merchant_data( SellerTypeEnum::UNKNOWN )
		);

		$partners_endpoint->shouldReceive( 'seller_status' )->once()->andThrow( new RuntimeException( '403' ) );
		$logger->shouldReceive( 'debug' )->once();

		// On failure: nothing persisted.
		$general_settings->shouldNotReceive( 'save' );

		$this->resolver->resolve_unknown_seller_type( $general_settings, $partners_endpoint, $logger );

		$this->assertTrue( true );
	}

	public function test_does_not_persist_when_no_business_capability(): void {
		$general_settings  = Mockery::mock( GeneralSettings::class );
		$partners_endpoint = Mockery::mock( PartnersEndpoint::class );
		$logger            = Mockery::mock( LoggerInterface::class );

		$general_settings->shouldReceive( 'is_merchant_connected' )->andReturn( true );
		$general_settings->shouldReceive( 'get_merchant_data' )->andReturn(
			$this->merchant_data( SellerTypeEnum::UNKNOWN )
		);

		// API succeeds but exposes no business capabilities -> resolve() returns UNKNOWN.
		$partners_endpoint->shouldReceive( 'seller_status' )->once()->andReturn(
			new SellerStatus( array(), array(), 'DE' )
		);

		$general_settings->shouldNotReceive( 'save' );

		$this->resolver->resolve_unknown_seller_type( $general_settings, $partners_endpoint, $logger );

		$this->assertTrue( true );
	}

	private function merchant_data( string $seller_type ): MerchantConnectionDTO {
		return new MerchantConnectionDTO( false, 'cid', 'secret', 'MID', 'merchant@example.com', 'DE', $seller_type );
	}
}
