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

	public function test_is_active_returns_false_after_deactivate(): void {
		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );

		$override->deactivate( 'migration_complete' );

		$this->assertFalse( $override->is_active() );
	}

	public function test_activate_with_empty_reason_does_not_change_state(): void {
		$override = new BcdcOverride();

		$override->activate( '' );

		$this->assertFalse( $override->is_active() );
	}

	public function test_deactivate_with_empty_reason_does_not_change_state(): void {
		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );

		$override->deactivate( '' );

		$this->assertTrue( $override->is_active() );
	}

	public function test_describe_returns_inactive_status_by_default(): void {
		$override = new BcdcOverride();

		$description = $override->describe();

		$this->assertStringContainsString( 'inactive', $description );
	}

	public function test_describe_includes_activation_reason_when_active(): void {
		$override = new BcdcOverride();

		$override->activate( 'plugin_update' );
		$description = $override->describe();

		$this->assertStringContainsString( 'active', $description );
		$this->assertStringContainsString( 'plugin_update', $description );
	}

}
