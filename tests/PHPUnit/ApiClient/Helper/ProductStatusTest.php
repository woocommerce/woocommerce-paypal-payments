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

		defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
	}

	private function create_test_product_status( bool $is_connected = true ): TestProductStatus {
		return new TestProductStatus(
			$is_connected,
			$this->partners_endpoint,
			$this->api_failure_registry,
			$this->result_cache
		);
	}

	public function test_is_active_returns_false_when_not_onboarded(): void {
		$testee = $this->create_test_product_status( false );

		// Mock that every cache::get() returns true. Should never be called.
		$this->result_cache->allows( 'get' )->andReturn( 'yes' );

		$result = $testee->is_active();

		$this->assertFalse( $result );
	}

	public function test_local_state_skips_api_check(): void {
		// Mock cache returning "yes" so check_local_state() returns true
		$this->result_cache->shouldReceive( 'get' )
			->with( TestProductStatus::KEY )
			->andReturn( 'yes' );

		// PartnersEndpoint should never be called when local state is available
		$this->partners_endpoint->shouldNotReceive( 'seller_status' );

		$testee = $this->create_test_product_status();

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
		$this->result_cache = Mockery::mock( ProductStatusResultCache::class );
		$this->result_cache->allows( 'get' )->andReturn( $cache_value );

		$testee = $this->create_test_product_status();

		$result = $testee->check_local_state();

		$this->assertSame( $expected_result, $result );
	}

	public function check_local_state_provider(): array {
		return array(
			'cache_has_yes'  => array(
				'cache_value'     => 'yes',
				'expected_result' => true,
			),
			'cache_has_no'   => array(
				'cache_value'     => 'no',
				'expected_result' => false,
			),
			'cache_is_empty' => array(
				'cache_value'     => '',
				'expected_result' => null,
			),
		);
	}

	/**
	 * @dataProvider verify_api_result_cache_provider
	 */
	public function test_is_active_calls_api_once_then_caches_in_memory( bool $api_response ): void {
		$this->result_cache->allows( 'get' )->andReturn( '' );

		$testee = $this->create_test_product_status();
		$testee->mock_api_response_state( $api_response );

		// First call: triggers API and caches result in memory
		$result = $testee->is_active();
		$this->assertEquals( $api_response, $result, 'The API result was not used' );

		// Test: Change API response; should not impact next assertion, which comes from cache.
		$testee->mock_api_response_state( ! $api_response );

		// Second call: served from memory cache, API not called again
		$result = $testee->is_active();
		$this->assertEquals( $api_response, $result, 'The cached result should be returned, but the API was queried again' );
	}

	public function verify_api_result_cache_provider(): array {
		return array(
			'api_returns_true'  => array(
				'api_response' => true,
			),
			'api_returns_false' => array(
				'api_response' => false,
			),
		);
	}
}

class TestProductStatus extends ProductStatus {

	public const KEY = 'test_product';

	private bool $dummy_api_result = false;

	// Sample "API check."
	protected function check_api_response( SellerStatus $seller_status ): bool {
		return $this->dummy_api_result;
	}

	// Dummy override without logic.
	protected function get_seller_status_object(): SellerStatus {
		return Mockery::mock( SellerStatus::class );
	}

	// Not an API, just used for testing.
	public function mock_api_response_state( bool $result ): void {
		$this->dummy_api_result = $result;
	}
}
