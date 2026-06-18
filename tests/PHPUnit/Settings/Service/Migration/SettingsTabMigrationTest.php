<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * Tests the capture_for_virtual_only → capture_virtual_orders migration
 * in SettingsTabMigration.
 */
class SettingsTabMigrationTest extends TestCase {

	/**
	 * @dataProvider capture_for_virtual_only_cases
	 *
	 * @param array     $legacy_settings    Legacy woocommerce-ppcp-settings values.
	 * @param bool|null $expected_authorize Expected authorize_only value, or null if absent.
	 * @param bool|null $expected_capture   Expected capture_virtual_orders value, or null if
	 *                                      absent.
	 */
	public function test_capture_for_virtual_only_migration(
		array $legacy_settings,
		?bool $expected_authorize,
		?bool $expected_capture
	): void {
		$captured_data  = null;
		$settings_model = Mockery::mock( SettingsModel::class );
		$settings_model->shouldReceive( 'from_array' )
			->once()
			->withArgs( function ( array $data ) use ( &$captured_data ) {
				$captured_data = $data;

				return true;
			} );
		$settings_model->shouldReceive( 'save' )->once();

		$migration = new SettingsTabMigration( $legacy_settings, $settings_model );
		$migration->migrate();

		if ( $expected_authorize !== null ) {
			$this->assertArrayHasKey( 'authorize_only', $captured_data );
			$this->assertSame( $expected_authorize, $captured_data['authorize_only'] );
		}

		if ( $expected_capture !== null ) {
			$this->assertArrayHasKey( 'capture_virtual_orders', $captured_data );
			$this->assertSame( $expected_capture, $captured_data['capture_virtual_orders'] );
		} else {
			$this->assertArrayNotHasKey( 'capture_virtual_orders', $captured_data );
		}
	}

	public function capture_for_virtual_only_cases(): array {
		return array(
			'authorize + capture_for_virtual_only true'  => array(
				'legacy_settings'    => array(
					'intent'                   => 'authorize',
					'capture_for_virtual_only' => true,
				),
				'expected_authorize' => true,
				'expected_capture'   => true,
			),
			'authorize + capture_for_virtual_only false' => array(
				'legacy_settings'    => array(
					'intent'                   => 'authorize',
					'capture_for_virtual_only' => false,
				),
				'expected_authorize' => true,
				'expected_capture'   => false,
			),
			'capture intent without virtual-only flag'   => array(
				'legacy_settings'    => array(
					'intent' => 'capture',
				),
				'expected_authorize' => false,
				'expected_capture'   => null,
			),
			'capture + capture_for_virtual_only true'    => array(
				'legacy_settings'    => array(
					'intent'                   => 'capture',
					'capture_for_virtual_only' => true,
				),
				'expected_authorize' => false,
				'expected_capture'   => true,
			),
		);
	}

	/**
	 * Legacy classic-WooCommerce settings stored the vault_enabled flag as the
	 * string "yes" or "no". The new SettingsModel slot is a typed bool, so the
	 * migration must coerce the value before it reaches the typed setter.
	 *
	 * Without coercion the migration either throws a TypeError (strict mode)
	 * or persists "no" as true (weak mode), which downstream causes
	 * SettingsModel::get_save_paypal_and_venmo() -- declared `: bool` -- to
	 * fatal on PHP 8.x with "Return value must be of type bool, string
	 * returned" the next time the setting is read.
	 *
	 * @dataProvider vault_enabled_cases
	 *
	 * @param mixed     $legacy_value Legacy vault_enabled value, in any of the
	 *                                shapes the option has historically held.
	 * @param bool|null $expected     Expected save_paypal_and_venmo after
	 *                                migration, or null if the key should be
	 *                                absent from the captured payload.
	 */
	public function test_vault_enabled_migration( $legacy_value, ?bool $expected ): void {
		$legacy_settings = ( $legacy_value === '__missing__' )
			? array()
			: array( 'vault_enabled' => $legacy_value );

		$captured_data  = null;
		$settings_model = Mockery::mock( SettingsModel::class );
		$settings_model->shouldReceive( 'from_array' )
			->once()
			->withArgs( function ( array $data ) use ( &$captured_data ) {
				$captured_data = $data;

				return true;
			} );
		$settings_model->shouldReceive( 'save' )->once();

		$migration = new SettingsTabMigration( $legacy_settings, $settings_model );
		$migration->migrate();

		if ( $expected === null ) {
			$this->assertArrayNotHasKey( 'save_paypal_and_venmo', $captured_data );

			return;
		}

		$this->assertArrayHasKey( 'save_paypal_and_venmo', $captured_data );
		$this->assertSame( $expected, $captured_data['save_paypal_and_venmo'] );
		$this->assertIsBool( $captured_data['save_paypal_and_venmo'] );
	}

	public function vault_enabled_cases(): array {
		return array(
			'legacy string yes coerces to true'  => array( 'yes', true ),
			'legacy string no coerces to false'  => array( 'no', false ),
			'legacy string 1 coerces to true'    => array( '1', true ),
			'legacy string 0 coerces to false'   => array( '0', false ),
			'empty string coerces to false'      => array( '', false ),
			'real boolean true passes through'   => array( true, true ),
			'real boolean false passes through'  => array( false, false ),
			'integer 1 coerces to true'          => array( 1, true ),
			'integer 0 coerces to false'         => array( 0, false ),
			'missing legacy key is not migrated' => array( '__missing__', null ),
		);
	}

	/**
	 * Same problem as vault_enabled: legacy classic-WooCommerce settings stored
	 * vault_enabled_dcc as the string "yes" or "no", while the new
	 * SettingsModel::save_card_details slot is a typed bool. The migration must
	 * coerce the value before it reaches the typed setter, otherwise a stored
	 * string aborts the whole migration with an uncaught TypeError.
	 *
	 * @dataProvider vault_enabled_dcc_cases
	 *
	 * @param mixed     $legacy_value Legacy vault_enabled_dcc value, in any of the
	 *                                shapes the option has historically held.
	 * @param bool|null $expected     Expected save_card_details after migration,
	 *                                or null if the key should be absent from the
	 *                                captured payload.
	 */
	public function test_vault_enabled_dcc_migration( $legacy_value, ?bool $expected ): void {
		$legacy_settings = ( $legacy_value === '__missing__' )
			? array()
			: array( 'vault_enabled_dcc' => $legacy_value );

		$captured_data  = null;
		$settings_model = Mockery::mock( SettingsModel::class );
		$settings_model->shouldReceive( 'from_array' )
			->once()
			->withArgs( function ( array $data ) use ( &$captured_data ) {
				$captured_data = $data;

				return true;
			} );
		$settings_model->shouldReceive( 'save' )->once();

		$migration = new SettingsTabMigration( $legacy_settings, $settings_model );
		$migration->migrate();

		if ( $expected === null ) {
			$this->assertArrayNotHasKey( 'save_card_details', $captured_data );

			return;
		}

		$this->assertArrayHasKey( 'save_card_details', $captured_data );
		$this->assertSame( $expected, $captured_data['save_card_details'] );
		$this->assertIsBool( $captured_data['save_card_details'] );
	}

	public function vault_enabled_dcc_cases(): array {
		return array(
			'legacy string yes coerces to true'  => array( 'yes', true ),
			'legacy string no coerces to false'  => array( 'no', false ),
			'legacy string 1 coerces to true'    => array( '1', true ),
			'legacy string 0 coerces to false'   => array( '0', false ),
			'empty string coerces to false'      => array( '', false ),
			'real boolean true passes through'   => array( true, true ),
			'real boolean false passes through'  => array( false, false ),
			'integer 1 coerces to true'          => array( 1, true ),
			'integer 0 coerces to false'         => array( 0, false ),
			'missing legacy key is not migrated' => array( '__missing__', null ),
		);
	}
}
