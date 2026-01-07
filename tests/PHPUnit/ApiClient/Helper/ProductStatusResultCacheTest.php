<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class ProductStatusResultCacheTest extends TestCase {

	public function setUp(): void {
		when( 'get_transient' )->justReturn( array() );
		when( 'set_transient' )->justReturn( true );
	}

	public function test_if_class_exists(): void {
		$testee = new ProductStatusResultCache();
		$this->assertInstanceOf( ProductStatusResultCache::class, $testee );
	}

	public function test_get_returns_empty_string_for_non_existent_key(): void {
		$testee = new ProductStatusResultCache();

		$result = $testee->get( 'non_existent_key' );

		$this->assertSame( '', $result );
	}

	public function test_set_stores_value_and_get_retrieves_it(): void {
		$testee = new ProductStatusResultCache();

		$testee->set( 'test_key', 'test_value' );
		$result = $testee->get( 'test_key' );

		$this->assertSame( 'test_value', $result );
	}

	public function test_clear_removes_value(): void {
		$testee = new ProductStatusResultCache();

		$testee->set( 'test_key', 'test_value' );
		$testee->clear( 'test_key' );
		$result = $testee->get( 'test_key' );

		$this->assertSame( '', $result );
	}

	public function test_data_persists_across_instances(): void {
		$cache1 = new TestProductStatusResultCache();
		$cache1->set( 'test_key', 'test_value' );

		$cache2 = new TestProductStatusResultCache();
		$result = $cache2->get( 'test_key' );

		$this->assertSame( 'test_value', $result );
	}

}

class TestProductStatusResultCache extends ProductStatusResultCache {
	private static array $storage = array();

	protected function load_from_storage(): array {
		return self::$storage;
	}

	protected function save_to_storage( array $data ): void {
		self::$storage = $data;
	}
}
