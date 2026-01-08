<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatusResultCache
 */
class ProductStatusResultCacheTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		when( 'get_transient' )->justReturn( array() );
		when( 'set_transient' )->justReturn( true );
	}

	public function tearDown(): void {
		TestProductStatusResultCache::reset_storage();
		parent::tearDown();
	}

	public function test_get_returns_empty_string_for_non_existent_key(): void {
		$testee = new TestProductStatusResultCache();

		$result = $testee->get( 'non_existent_key' );

		$this->assertSame( '', $result );
	}

	public function test_set_stores_value_and_get_retrieves_it(): void {
		$testee = new TestProductStatusResultCache();

		$testee->set( 'test_key', 'test_value' );
		$result = $testee->get( 'test_key' );

		$this->assertSame( 'test_value', $result );
	}

	public function test_clear_removes_value(): void {
		$testee = new TestProductStatusResultCache();

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

	public function test_handles_multiple_keys_independently(): void {
		$testee = new TestProductStatusResultCache();

		$testee->set( 'key1', 'dummy' );
		$testee->set( 'key1', 'value1' );
		$testee->set( 'key2', 'value2' );
		$testee->set( 'key3', 'value3' );

		$this->assertSame( 'value1', $testee->get( 'key1' ) );
		$this->assertSame( 'value2', $testee->get( 'key2' ) );
		$this->assertSame( 'value3', $testee->get( 'key3' ) );
	}

}

class TestProductStatusResultCache extends ProductStatusResultCache {
	private static array $storage = array();

	public static function reset_storage(): void {
		self::$storage = array();
	}

	protected function load_from_storage(): array {
		return self::$storage;
	}

	protected function save_to_storage( array $data ): void {
		self::$storage = $data;
	}
}
