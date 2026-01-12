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

	public function setUp(): void {
		parent::setUp();
		$this->partners_endpoint    = Mockery::mock( PartnersEndpoint::class );
		$this->api_failure_registry = Mockery::mock( FailureRegistry::class );
	}

	private function create_test_product_status( bool $is_connected, $result_cache = null ): TestProductStatus {
		if ( null === $result_cache ) {
			$result_cache = Mockery::mock( ProductStatusResultCache::class );
		}

		return new TestProductStatus(
			$is_connected,
			$this->partners_endpoint,
			$this->api_failure_registry,
			$result_cache
		);
	}

	public function test_can_instantiate_concrete_implementation(): void {
		$testee = $this->create_test_product_status( true );

		$this->assertInstanceOf( ProductStatus::class, $testee );
	}

	public function test_is_active_returns_false_when_not_onboarded(): void {
		$testee = $this->create_test_product_status( false );

		$result = $testee->is_active();

		$this->assertFalse( $result );
	}

	public function test_is_active_uses_local_state_when_available(): void {
		// PartnersEndpoint should never be called when local state is available
		$this->partners_endpoint->shouldNotReceive( 'seller_status' );

		$testee = new TestProductStatusWithLocalState(
			true,
			$this->partners_endpoint,
			$this->api_failure_registry,
			Mockery::mock( ProductStatusResultCache::class )
		);

		$result = $testee->is_active();

		$this->assertTrue( $result );
	}

	/**
	 * @dataProvider check_local_state_provider
	 */
	public function test_check_local_state_returns_expected_value(
		string $cache_value,
		?bool $wc_string_result,
		?bool $expected_result
	): void {
		$result_cache = Mockery::mock( ProductStatusResultCache::class );
		$result_cache->shouldReceive( 'get' )
			->with( TestProductStatus::KEY )
			->andReturn( $cache_value );

		if ( null !== $wc_string_result ) {
			when( 'wc_string_to_bool' )->justReturn( $wc_string_result );
		}

		$testee = $this->create_test_product_status( true, $result_cache );

		$result = $testee->check_local_state();

		$this->assertSame( $expected_result, $result );
	}

	public function check_local_state_provider(): array {
		return array(
			'cache_has_yes'  => array(
				'cache_value'      => 'yes',
				'wc_string_result' => true,
				'expected_result'  => true,
			),
			'cache_has_no'   => array(
				'cache_value'      => 'no',
				'wc_string_result' => false,
				'expected_result'  => false,
			),
			'cache_is_empty' => array(
				'cache_value'      => '',
				'wc_string_result' => null,
				'expected_result'  => null,
			),
		);
	}

	public function test_mark_as_enabled_stores_yes_in_cache(): void {
		$result_cache = new TestProductStatusResultCache();
		$testee       = $this->create_test_product_status( true, $result_cache );

		// Before: cache is empty, check_local_state returns null
		$this->assertNull( $testee->check_local_state() );

		$testee->public_mark_as_enabled();

		// After: check_local_state returns true
		when( 'wc_string_to_bool' )->justReturn( true );
		$this->assertTrue( $testee->check_local_state() );
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
