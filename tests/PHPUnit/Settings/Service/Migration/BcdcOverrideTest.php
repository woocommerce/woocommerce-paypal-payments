<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers BcdcOverride
 */
class BcdcOverrideTest extends TestCase {

	public function test_is_active_returns_false_by_default(): void {
		$override = new BcdcOverride();

		$this->assertFalse( $override->is_active() );
	}

	public function test_is_active_returns_true_after_activate(): void {
		$override = new BcdcOverride();

		$override->activate( 'plugin_update' );

		$this->assertTrue( $override->is_active() );
	}

}
