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
}
