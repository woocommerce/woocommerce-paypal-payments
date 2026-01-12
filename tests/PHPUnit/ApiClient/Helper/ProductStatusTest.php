<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use Mockery;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus
 */
class ProductStatusTest extends TestCase {

	public function test_can_instantiate_concrete_implementation(): void {
		$is_connected         = true;
		$partners_endpoint    = Mockery::mock( PartnersEndpoint::class );
		$api_failure_registry = Mockery::mock( FailureRegistry::class );
		$result_cache         = Mockery::mock( ProductStatusResultCache::class );

		$testee = new TestProductStatus( $is_connected, $partners_endpoint, $api_failure_registry, $result_cache );

		$this->assertInstanceOf( ProductStatus::class, $testee );
	}

	public function test_is_active_returns_false_when_not_onboarded(): void {
		$is_connected         = false;
		$partners_endpoint    = Mockery::mock( PartnersEndpoint::class );
		$api_failure_registry = Mockery::mock( FailureRegistry::class );
		$result_cache         = Mockery::mock( ProductStatusResultCache::class );

		$testee = new TestProductStatus( $is_connected, $partners_endpoint, $api_failure_registry, $result_cache );

		$result = $testee->is_active();

		$this->assertFalse( $result );
	}

	public function test_is_active_uses_local_state_when_available(): void {
		$is_connected         = true;
		$partners_endpoint    = Mockery::mock( PartnersEndpoint::class );
		$api_failure_registry = Mockery::mock( FailureRegistry::class );
		$result_cache         = Mockery::mock( ProductStatusResultCache::class );

		// PartnersEndpoint should never be called when local state is available
		$partners_endpoint->shouldNotReceive( 'seller_status' );

		$testee = new TestProductStatusWithLocalState( $is_connected, $partners_endpoint, $api_failure_registry, $result_cache );

		$result = $testee->is_active();

		$this->assertTrue( $result );
	}

	public function test_check_local_state_returns_true_when_cache_has_yes(): void {
		$is_connected         = true;
		$partners_endpoint    = Mockery::mock( PartnersEndpoint::class );
		$api_failure_registry = Mockery::mock( FailureRegistry::class );
		$result_cache         = Mockery::mock( ProductStatusResultCache::class );

		$result_cache->shouldReceive( 'get' )
			->with( TestProductStatus::KEY )
			->andReturn( 'yes' );

		when( 'wc_string_to_bool' )->justReturn( true );

		$testee = new TestProductStatus( $is_connected, $partners_endpoint, $api_failure_registry, $result_cache );

		$result = $testee->check_local_state();

		$this->assertTrue( $result );
	}

	public function test_check_local_state_returns_false_when_cache_has_no(): void {
		$is_connected         = true;
		$partners_endpoint    = Mockery::mock( PartnersEndpoint::class );
		$api_failure_registry = Mockery::mock( FailureRegistry::class );
		$result_cache         = Mockery::mock( ProductStatusResultCache::class );

		$result_cache->shouldReceive( 'get' )
			->with( TestProductStatus::KEY )
			->andReturn( 'no' );

		when( 'wc_string_to_bool' )->justReturn( false );

		$testee = new TestProductStatus( $is_connected, $partners_endpoint, $api_failure_registry, $result_cache );

		$result = $testee->check_local_state();

		$this->assertFalse( $result );
	}

	public function test_check_local_state_returns_null_when_cache_is_empty(): void {
		$is_connected         = true;
		$partners_endpoint    = Mockery::mock( PartnersEndpoint::class );
		$api_failure_registry = Mockery::mock( FailureRegistry::class );
		$result_cache         = Mockery::mock( ProductStatusResultCache::class );

		$result_cache->shouldReceive( 'get' )
			->with( TestProductStatus::KEY )
			->andReturn( '' );

		$testee = new TestProductStatus( $is_connected, $partners_endpoint, $api_failure_registry, $result_cache );

		$result = $testee->check_local_state();

		$this->assertNull( $result );
	}

	public function test_mark_as_enabled_stores_yes_in_cache(): void {
		$is_connected         = true;
		$partners_endpoint    = Mockery::mock( PartnersEndpoint::class );
		$api_failure_registry = Mockery::mock( FailureRegistry::class );
		$result_cache         = Mockery::mock( ProductStatusResultCache::class );

		$result_cache->shouldReceive( 'set' )
			->with( TestProductStatus::KEY, 'yes' )
			->once();

		$testee = new TestProductStatus( $is_connected, $partners_endpoint, $api_failure_registry, $result_cache );

		$testee->public_mark_as_enabled();
	}
}

class TestProductStatus extends ProductStatus {

	public const KEY = 'test_product';

	protected function check_active_state( SellerStatus $seller_status ): bool {
		return true;
	}

	public function public_mark_as_enabled(): void {
		$this->mark_as_enabled();
	}
}

class TestProductStatusWithLocalState extends ProductStatus {

	public function check_local_state( bool $skip_filters = false ): ?bool {
		return true;
	}

	protected function check_active_state( SellerStatus $seller_status ): bool {
		return true;
	}
}
