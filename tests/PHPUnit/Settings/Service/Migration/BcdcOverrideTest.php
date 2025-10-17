<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use Mockery;

/**
 * @covers BcdcOverride
 */
class BcdcOverrideTest extends TestCase {

	private const OPTION_NAME = 'woocommerce_paypal_payments_bcdc_migration_override';

	public function test_is_active_returns_false_by_default(): void {
		when( 'get_option' )->justReturn( false );
		when( 'update_option' )->justReturn( true );
		$override = new BcdcOverride();

		$this->assertFalse( $override->is_active() );
	}

	public function test_is_active_returns_true_after_activate(): void {
		when( 'get_option' )->justReturn( false );
		when( 'update_option' )->justReturn( true );
		$override = new BcdcOverride();

		$override->activate( 'plugin_update' );

		$this->assertTrue( $override->is_active() );
	}

	public function test_is_active_returns_false_after_deactivate(): void {
		when( 'get_option' )->justReturn( false );
		when( 'update_option' )->justReturn( true );
		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );

		$override->deactivate( 'migration_complete' );

		$this->assertFalse( $override->is_active() );
	}

	public function test_activate_with_empty_reason_does_not_change_state(): void {
		when( 'get_option' )->justReturn( false );
		when( 'update_option' )->justReturn( true );
		$override = new BcdcOverride();

		$override->activate( '' );

		$this->assertFalse( $override->is_active() );
	}

	public function test_deactivate_with_empty_reason_does_not_change_state(): void {
		when( 'get_option' )->justReturn( false );
		when( 'update_option' )->justReturn( true );
		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );

		$override->deactivate( '' );

		$this->assertTrue( $override->is_active() );
	}

	public function test_describe_returns_inactive_status_by_default(): void {
		when( 'get_option' )->justReturn( false );
		when( 'update_option' )->justReturn( true );
		$override = new BcdcOverride();

		$description = $override->describe();

		$this->assertIsArray( $description );
		$this->assertArrayHasKey( 'is_active', $description );
		$this->assertFalse( $description['is_active'] );
	}

	public function test_describe_includes_activation_reason_when_active(): void {
		when( 'get_option' )->justReturn( false );
		when( 'update_option' )->justReturn( true );
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
		when( 'update_option' )->justReturn( true );
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
		when( 'update_option' )->justReturn( true );
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
		when( 'update_option' )->justReturn( true );
		$override = new BcdcOverride();

		$override->activate( 'plugin_update' );
		$description = $override->describe();

		$this->assertIsArray( $description );
		$this->assertArrayHasKey( 'activate_time', $description );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $description['activate_time'] );
	}

	public function test_activate_when_already_active_does_not_change_state(): void {
		when( 'get_option' )->justReturn( false );
		when( 'update_option' )->justReturn( true );
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
		when( 'update_option' )->justReturn( true );
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
		when( 'update_option' )->justReturn( true );
		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );
		$override->deactivate( 'migration_complete' );

		$override->activate( 'ui_migration' );
		$description = $override->describe();

		$this->assertTrue( $override->is_active() );
		$this->assertSame( '', $description['deactivate_reason'] );
		$this->assertSame( '', $description['deactivate_time'] );
	}

	public function test_constructor_loads_active_state_from_database(): void {
		expect( 'get_option' )
			->with( self::OPTION_NAME )
			->andReturn(
				array(
					'is_active'         => true,
					'activate_time'     => '2024-01-15 10:30:00',
					'activate_reason'   => 'plugin_update',
					'deactivate_time'   => '',
					'deactivate_reason' => '',
				)
			);

		$override = new BcdcOverride();

		$this->assertTrue( $override->is_active() );
		$description = $override->describe();
		$this->assertTrue( $description['is_active'] );
		$this->assertSame( '2024-01-15 10:30:00', $description['activate_time'] );
		$this->assertSame( 'plugin_update', $description['activate_reason'] );
	}

	public function test_empty_db_value_initializes_correctly(): void {
		expect( 'get_option' )
			->with( self::OPTION_NAME )
			->andReturn( null );

		$override = new BcdcOverride();

		$this->assertFalse( $override->is_active() );
		$description = $override->describe();
		$this->assertFalse( $description['is_active'] );
		$this->assertEmpty( $description['activate_time'] );
		$this->assertEmpty( $description['activate_reason'] );
	}


	public function test_legacy_db_value_initializes_as_active(): void {
		expect( 'get_option' )
			->with( self::OPTION_NAME )
			->andReturn( true );

		$override = new BcdcOverride();

		$this->assertTrue( $override->is_active() );
		$description = $override->describe();
		$this->assertTrue( $description['is_active'] );
		$this->assertEmpty( $description['activate_time'] );
		$this->assertEmpty( $description['activate_reason'] );
	}

	public function test_constructor_handles_missing_option_with_default_state(): void {
		expect( 'get_option' )
			->with( self::OPTION_NAME )
			->andReturn( false );

		$override = new BcdcOverride();

		$this->assertFalse( $override->is_active() );
		$description = $override->describe();
		$this->assertFalse( $description['is_active'] );
		$this->assertSame( '', $description['activate_time'] );
		$this->assertSame( '', $description['activate_reason'] );
	}

	public function test_constructor_handles_corrupted_data_with_default_state(): void {
		expect( 'get_option' )
			->with( self::OPTION_NAME )
			->andReturn( 'not-an-array' );

		$override = new BcdcOverride();

		$this->assertFalse( $override->is_active() );
		$description = $override->describe();
		$this->assertFalse( $description['is_active'] );
	}

	public function test_constructor_loads_inactive_state_from_database(): void {
		expect( 'get_option' )
			->with( self::OPTION_NAME )
			->andReturn(
				array(
					'is_active'         => false,
					'activate_time'     => '2024-01-15 10:30:00',
					'activate_reason'   => 'plugin_update',
					'deactivate_time'   => '2024-01-20 14:45:00',
					'deactivate_reason' => 'migration_complete',
				)
			);

		$override = new BcdcOverride();

		$this->assertFalse( $override->is_active() );
		$description = $override->describe();
		$this->assertFalse( $description['is_active'] );
		$this->assertSame( '2024-01-15 10:30:00', $description['activate_time'] );
		$this->assertSame( 'plugin_update', $description['activate_reason'] );
		$this->assertSame( '2024-01-20 14:45:00', $description['deactivate_time'] );
		$this->assertSame( 'migration_complete', $description['deactivate_reason'] );
	}

	public function test_constructor_handles_incomplete_data_with_defaults(): void {
		expect( 'get_option' )
			->with( self::OPTION_NAME )
			->andReturn(
				array( 'is_active' => true )
			);

		$override = new BcdcOverride();

		$this->assertTrue( $override->is_active() );
		$description = $override->describe();
		$this->assertTrue( $description['is_active'] );
		$this->assertSame( '', $description['activate_time'] );
		$this->assertSame( '', $description['activate_reason'] );
	}

	public function test_activate_saves_state_to_database(): void {
		when( 'get_option' )->justReturn( false );

		expect( 'update_option' )
			->once()
			->with(
				self::OPTION_NAME,
				Mockery::on(
					static fn( $data ) => is_array( $data )
						&& $data['is_active'] === true
						&& $data['activate_reason'] === 'plugin_update'
						&& ! empty( $data['activate_time'] )
						&& $data['deactivate_reason'] === ''
						&& $data['deactivate_time'] === ''

				)
			)
			->andReturn( true );

		$override = new BcdcOverride();
		$override->activate( 'plugin_update' );

		// Mockery expectations also count.
		$this->addToAssertionCount( 1 );
	}

	public function test_deactivate_saves_state_to_database(): void {
		expect( 'get_option' )
			->with( self::OPTION_NAME )
			->andReturn(
				array(
					'is_active'         => true,
					'activate_time'     => '2024-01-15 10:30:00',
					'activate_reason'   => 'plugin_update',
					'deactivate_time'   => '',
					'deactivate_reason' => '',
				)
			);

		expect( 'update_option' )
			->once()
			->with(
				self::OPTION_NAME,
				Mockery::on(
					static fn( $data ) => is_array( $data )
						&& $data['is_active'] === false
						&& $data['activate_reason'] === 'plugin_update'
						&& $data['activate_time'] === '2024-01-15 10:30:00'
						&& $data['deactivate_reason'] === 'migration_complete'
						&& ! empty( $data['deactivate_time'] )
				)
			)
			->andReturn( true );

		$override = new BcdcOverride();
		$override->deactivate( 'migration_complete' );

		// Mockery expectations also count.
		$this->addToAssertionCount( 1 );
	}

	public function test_activate_with_empty_reason_does_not_save_to_database(): void {
		when( 'get_option' )->justReturn( false );
		expect( 'update_option' )->never();

		$override = new BcdcOverride();
		$override->activate( '' );
	}

}
