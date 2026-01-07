<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\TestCase;

class ProductStatusResultCacheTest extends TestCase {

	public function test_if_class_exists(): void {
		$testee = new ProductStatusResultCache();
		$this->assertInstanceOf( ProductStatusResultCache::class, $testee );
	}

	public function test_get_returns_empty_string_for_non_existent_key(): void {
		$testee = new ProductStatusResultCache();

		$result = $testee->get( 'non_existent_key' );

		$this->assertSame( '', $result );
	}

}
