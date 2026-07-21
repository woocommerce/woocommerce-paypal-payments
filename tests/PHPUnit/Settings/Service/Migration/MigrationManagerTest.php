<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use Exception;
use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Settings\Service\Migration\MigrationManager
 */
class MigrationManagerTest extends TestCase {

	/**
	 * GIVEN a general_settings_migration that throws / succeeds
	 * AND   is_merchant_connected() returning true or false
	 * WHEN  migrate() is called
	 * THEN  set_completed(true) is called only when migration succeeds AND merchant is connected
	 * AND   OPTION_NAME_MIGRATION_IS_DONE is written only when migration does not abort early
	 *
	 * @dataProvider set_completed_behavior_provider
	 */
	public function test_set_completed_reflects_migration_outcome(
		bool $general_migration_throws,
		bool $merchant_connected,
		int $expected_set_completed_calls,
		bool $expect_migration_done_written
	): void {
		// Stub get_option so is-new-merchant returns false (simulate missing option entry).
		when( 'get_option' )->justReturn( false );

		$written_options = array();
		when( 'update_option' )->alias(
			function ( string $key, $value ) use ( &$written_options ): bool {
				$written_options[ $key ] = $value;

				return true;
			}
		);

		when( 'do_action' )->justReturn( null );
		when( 'did_action' )->justReturn( 0 );
		when( 'add_action' )->justReturn( null );

		// General settings migration: either a clean no-op or a throw to simulate failed API call.
		$general_migration = $this->createStub( SettingsMigration::class );
		if ( $general_migration_throws ) {
			$general_migration->method( 'migrate' )
				->willThrowException( new Exception( 'API call failed' ) );
		} else {
			$general_migration->method( 'is_merchant_connected' )
				->willReturn( $merchant_connected );
		}

		// All other migrations are plain no-ops.
		$tab_migration      = $this->createStub( SettingsTabMigration::class );
		$styling_migration  = $this->createStub( StylingSettingsMigration::class );
		$payment_migration  = $this->createStub( PaymentSettingsMigration::class );
		$fastlane_migration = $this->createStub( FastlaneSettingsMigration::class );

		// Logger stub — we are not asserting on it.
		$logger = $this->createStub( LoggerInterface::class );

		// OnboardingProfile: mock because the assertion IS about whether set_completed is called.
		$onboarding_profile = Mockery::mock( OnboardingProfile::class );
		$onboarding_profile->shouldReceive( 'set_completed' )
			->with( true )
			->times( $expected_set_completed_calls );

		// Allow set_completed(false) — called when migration succeeds but merchant is not connected.
		$onboarding_profile->shouldReceive( 'set_completed' )
			->with( false )
			->zeroOrMoreTimes();

		// Allow any other OnboardingProfile calls that may occur on the happy path.
		$onboarding_profile->shouldReceive( 'set_gateways_refreshed' )->zeroOrMoreTimes();
		$onboarding_profile->shouldReceive( 'set_gateways_synced' )->zeroOrMoreTimes();
		$onboarding_profile->shouldReceive( 'save' )->zeroOrMoreTimes();

		$manager = new MigrationManager(
			$general_migration,
			$tab_migration,
			$styling_migration,
			$payment_migration,
			$fastlane_migration,
			$onboarding_profile,
			$logger
		);

		$manager->migrate();

		if ( $expect_migration_done_written ) {
			$this->assertArrayHasKey(
				MigrationManager::OPTION_NAME_MIGRATION_IS_DONE,
				$written_options,
				'OPTION_NAME_MIGRATION_IS_DONE must be written when migration completes successfully.'
			);
			$this->assertTrue( $written_options[ MigrationManager::OPTION_NAME_MIGRATION_IS_DONE ] );
		} else {
			$this->assertArrayNotHasKey(
				MigrationManager::OPTION_NAME_MIGRATION_IS_DONE,
				$written_options,
				'OPTION_NAME_MIGRATION_IS_DONE must NOT be written when migration aborts.'
			);
		}
	}

	public function set_completed_behavior_provider(): array {
		return array(
			'general migration throws — set_completed must not be called' => array(
				'general_migration_throws'      => true,
				'merchant_connected'            => false,
				'expected_set_completed_calls'  => 0,
				'expect_migration_done_written' => false,
			),

			'migration succeeds but merchant not connected — set_completed(false)' => array(
				'general_migration_throws'      => false,
				'merchant_connected'            => false,
				'expected_set_completed_calls'  => 0,
				'expect_migration_done_written' => true,
			),

			'happy path — merchant connected — set_completed(true)' => array(
				'general_migration_throws'      => false,
				'merchant_connected'            => true,
				'expected_set_completed_calls'  => 1,
				'expect_migration_done_written' => true,
			),
		);
	}
}
