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

	public function test_multiple_keys_with_different_expirations(): void {
		TestProductStatusResultCache::set_time( 1000 );
		$testee = new TestProductStatusResultCache();

		$testee->set( 'short_ttl', 'value1', 30 );
		$testee->set( 'long_ttl', 'value2', 120 );

		TestProductStatusResultCache::set_time( 1050 );

		$this->assertSame( '', $testee->get( 'short_ttl' ) );
		$this->assertSame( 'value2', $testee->get( 'long_ttl' ) );
	}

	/**
	 * @dataProvider expiration_data_provider
	 */
	public function test_value_expiration_behavior( int $expiration, int $test_delay, string $value, string $expected_value ): void {
		$start_time = 1000;
		TestProductStatusResultCache::set_time( $start_time );
		$testee = new TestProductStatusResultCache();

		$testee->set( 'test_key', $value, $expiration );

		TestProductStatusResultCache::set_time( $start_time + $test_delay );
		$result = $testee->get( 'test_key' );

		$this->assertSame( $expected_value, $result );
	}

	public function expiration_data_provider(): array {
		return [
			'no delay'       => [
				'expiration'     => 100,
				'test_delay'     => 0,
				'value'          => 'yes',
				'expected_value' => 'yes',
			],
			'short delay'    => [
				'expiration'     => 100,
				'test_delay'     => 1,
				'value'          => 'yes',
				'expected_value' => 'yes',
			],
			'long delay'     => [
				'expiration'     => 100,
				'test_delay'     => 99,
				'value'          => 'yes',
				'expected_value' => 'yes',
			],
			'full delay'     => [
				'expiration'     => 100,
				'test_delay'     => 100,
				'value'          => 'yes',
				'expected_value' => 'yes',
			],
			'after delay'    => [
				'expiration'     => 100,
				'test_delay'     => 101,
				'value'          => 'yes',
				'expected_value' => '',
			],
			'no expiration'  => [
				'expiration'     => 0,
				'test_delay'     => 999999,
				'value'          => 'permanent',
				'expected_value' => 'permanent',
			],
		];
	}

}

class TestProductStatusResultCache extends ProductStatusResultCache {
	private static array $storage = array();
	private static int $current_time = 0;

	public static function reset_storage(): void {
		self::$storage      = array();
		self::$current_time = 0;
	}

	public static function set_time( int $time ): void {
		self::$current_time = $time;
	}

	protected function get_time(): int {
		return self::$current_time > 0 ? self::$current_time : parent::get_time();
	}

	protected function load_from_storage(): array {
		return self::$storage;
	}

	protected function save_to_storage( array $data ): void {
		self::$storage = $data;
	}
}
