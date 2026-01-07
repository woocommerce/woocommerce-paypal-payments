<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\TestCase;

class ProductStatusResultCacheTest extends TestCase {

	public function test_if_class_exists(): void {
		$testee = new ProductStatusResultCache();
		$this->assertInstanceOf( ProductStatusResultCache::class, $testee );
	}
}
