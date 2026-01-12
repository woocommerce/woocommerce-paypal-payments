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

	private $partners_endpoint;
	private $api_failure_registry;
	private $result_cache;

	public function setUp(): void {
		parent::setUp();
		$this->partners_endpoint    = Mockery::mock( PartnersEndpoint::class );
		$this->api_failure_registry = Mockery::mock( FailureRegistry::class );
		$this->result_cache         = Mockery::mock( ProductStatusResultCache::class );

		when( 'wc_string_to_bool' )->alias( static fn( $value ) => 'yes' === strtolower( $value ) );

		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}
	}

	private function create_test_product_status( bool $is_connected, $result_cache = null ): TestProductStatus {
		if ( null === $result_cache ) {
			$result_cache = $this->result_cache;
		}

		return new TestProductStatus(
			$is_connected,
			$this->partners_endpoint,
			$this->api_failure_registry,
			$result_cache
		);
	}

	public function test_is_active_returns_false_when_not_onboarded(): void {
		$testee = $this->create_test_product_status( false );

		// Mock that every cache::get() returns true. Should never be called.
		$this->result_cache->allows( 'get' )->andReturn( true );

		$result = $testee->is_active();

		$this->assertFalse( $result );
	}

	public function test_is_active_uses_local_state_when_available(): void {
		// Mock cache returning "yes" so check_local_state() returns true
		$this->result_cache->shouldReceive( 'get' )
			->with( TestProductStatus::KEY )
			->andReturn( 'yes' );

		// PartnersEndpoint should never be called when local state is available
		$this->partners_endpoint->shouldNotReceive( 'seller_status' );

		$testee = $this->create_test_product_status( true );

		$result = $testee->is_active();

		$this->assertTrue( $result );
	}

	/**
	 * @dataProvider check_local_state_provider
	 */
	public function test_check_local_state_returns_expected_value(
		string $cache_value,
		?bool $expected_result
	): void {
		$result_cache = Mockery::mock( ProductStatusResultCache::class );
		$result_cache->shouldReceive( 'get' )
			->with( TestProductStatus::KEY )
			->andReturn( $cache_value );

		$testee = $this->create_test_product_status( true, $result_cache );

		$result = $testee->check_local_state();

		$this->assertSame( $expected_result, $result );
	}

	public function check_local_state_provider(): array {
		return array(
			'cache_has_yes'  => array(
				'cache_value'    => 'yes',
				'expected_result' => true,
			),
			'cache_has_no'   => array(
				'cache_value'    => 'no',
				'expected_result' => false,
			),
			'cache_is_empty' => array(
				'cache_value'    => '',
				'expected_result' => null,
			),
		);
	}

	/**
	 * @dataProvider api_result_provider
	 */
	public function test_is_active_calls_api_once_and_caches_in_memory(
		bool $api_result
	): void {
		// Reset static seller_status cache between dataProvider iterations
		TestProductStatus::reset_seller_status();

		// Mock cache as empty so API will be called
		$this->result_cache->shouldReceive( 'get' )
			->with( TestProductStatus::KEY )
			->andReturn( '' );

		// Mock failure registry to allow API call
		$this->api_failure_registry->shouldReceive( 'has_failure_in_timeframe' )
			->andReturn( false );

		// Mock API response - should be called ONCE
		$seller_status = Mockery::mock( SellerStatus::class );
		$this->partners_endpoint->shouldReceive( 'seller_status' )
			->once()
			->andReturn( $seller_status );

		$testee = $this->create_test_product_status( true );
		$testee->set_active_state_result( $api_result );

		// First call: triggers API and caches result in memory
		$result = $testee->is_active();
		$this->assertSame( $api_result, $result );

		// Second call: served from memory cache, API not called again
		$result = $testee->is_active();
		$this->assertSame( $api_result, $result );
	}

	public function api_result_provider(): array {
		return array(
			'api_returns_true'  => array( 'api_result' => true ),
			'api_returns_false' => array( 'api_result' => false ),
		);
	}

	/**
	 * @dataProvider state_change_provider
	 */
	public function test_state_change_methods_update_cache(
		string $method_name,
		?bool $expected_state
	): void {
		TestProductStatusResultCache::reset_storage();

		$result_cache = new TestProductStatusResultCache();
		$testee       = $this->create_test_product_status( true, $result_cache );

		// Before: cache is empty, check_local_state returns null
		$this->assertNull( $testee->check_local_state() );

		$testee->$method_name();

		$this->assertSame( $expected_state, $testee->check_local_state() );
	}

	public function state_change_provider(): array {
		return array(
			'mark_as_enabled'  => array(
				'method_name'    => 'test_mark_as_enabled',
				'expected_state' => true,
			),
			'mark_as_disabled' => array(
				'method_name'    => 'test_mark_as_disabled',
				'expected_state' => false,
			),
			'clear'            => array(
				'method_name'    => 'clear',
				'expected_state' => null,
			),
		);
	}
}

class TestProductStatus extends ProductStatus {

	public const KEY = 'test_product';

	private bool $active_state_result = true;

	protected function check_active_state( SellerStatus $seller_status ): bool {
		return $this->active_state_result;
	}

	public function set_active_state_result( bool $result ): void {
		$this->active_state_result = $result;
	}

	public static function reset_seller_status(): void {
		$reflection = new \ReflectionClass( ProductStatus::class );
		$property   = $reflection->getProperty( 'seller_status' );
		$property->setValue( null, null );
	}

	public function test_mark_as_enabled(): void {
		$this->mark_as_enabled();
	}

	public function test_mark_as_disabled(): void {
		$this->mark_as_disabled();
	}
}
