<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers BcdcOverride
 */
class BcdcOverrideTest extends TestCase {

	public function test_is_active_returns_false_by_default(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();

		$this->assertFalse( $override->is_active() );
	}

	public function test_is_active_returns_true_after_activate(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();

		$override->activate( 'plugin_update' );

		$this->assertTrue( $override->is_active() );
	}

	public function test_is_active_returns_false_after_deactivate(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );

		$override->deactivate( 'migration_complete' );

		$this->assertFalse( $override->is_active() );
	}

	public function test_activate_with_empty_reason_does_not_change_state(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();

		$override->activate( '' );

		$this->assertFalse( $override->is_active() );
	}

	public function test_deactivate_with_empty_reason_does_not_change_state(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );

		$override->deactivate( '' );

		$this->assertTrue( $override->is_active() );
	}

	public function test_describe_returns_inactive_status_by_default(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();

		$description = $override->describe();

		$this->assertIsArray( $description );
		$this->assertArrayHasKey( 'is_active', $description );
		$this->assertFalse( $description['is_active'] );
	}

	public function test_describe_includes_activation_reason_when_active(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();

		$override->activate( 'plugin_update' );
		$description = $override->describe();

		$this->assertIsArray( $description );
		$this->assertArrayHasKey( 'is_active', $description );
		$this->assertTrue( $description['is_active'] );
		$this->assertArrayHasKey( 'activate_reason', $description );
		$this->assertSame( 'plugin_update', $description['activate_reason'] );
	}

	public function test_describe_includes_different_activation_reason(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();

		$override->activate( 'ui_migration' );
		$description = $override->describe();

		$this->assertIsArray( $description );
		$this->assertArrayHasKey( 'is_active', $description );
		$this->assertTrue( $description['is_active'] );
		$this->assertArrayHasKey( 'activate_reason', $description );
		$this->assertSame( 'ui_migration', $description['activate_reason'] );
	}

	public function test_describe_includes_deactivation_reason(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );

		$override->deactivate( 'migration_complete' );
		$description = $override->describe();

		$this->assertIsArray( $description );
		$this->assertArrayHasKey( 'is_active', $description );
		$this->assertFalse( $description['is_active'] );
		$this->assertArrayHasKey( 'activate_reason', $description );
		$this->assertSame( 'plugin_update', $description['activate_reason'] );
		$this->assertArrayHasKey( 'deactivate_reason', $description );
		$this->assertSame( 'migration_complete', $description['deactivate_reason'] );
	}

	public function test_describe_includes_activation_timestamp(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();

		$override->activate( 'plugin_update' );
		$description = $override->describe();

		$this->assertIsArray( $description );
		$this->assertArrayHasKey( 'activate_time', $description );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $description['activate_time'] );
	}

	public function test_activate_when_already_active_does_not_change_state(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );
		$first_description = $override->describe();

		$override->activate( 'ui_migration' );
		$second_description = $override->describe();

		$this->assertTrue( $override->is_active() );
		$this->assertSame( 'plugin_update', $second_description['activate_reason'] );
		$this->assertSame( $first_description['activate_time'], $second_description['activate_time'] );
	}

	public function test_deactivate_when_already_inactive_does_not_change_state(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );
		$override->deactivate( 'migration_complete' );
		$first_description = $override->describe();

		$override->deactivate( 'user_requested' );
		$second_description = $override->describe();

		$this->assertFalse( $override->is_active() );
		$this->assertSame( 'migration_complete', $second_description['deactivate_reason'] );
		$this->assertSame( $first_description['deactivate_time'], $second_description['deactivate_time'] );
	}

	public function test_activate_clears_deactivate_fields(): void {
		when( 'get_option' )->justReturn( false );
		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );
		$override->deactivate( 'migration_complete' );

		$override->activate( 'ui_migration' );
		$description = $override->describe();

		$this->assertTrue( $override->is_active() );
		$this->assertSame( '', $description['deactivate_reason'] );
		$this->assertSame( '', $description['deactivate_time'] );
	}

}
