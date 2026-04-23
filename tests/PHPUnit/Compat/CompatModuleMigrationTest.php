<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Compat;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Tests the capture_on_status_change migration logic in CompatModule.
 */
class CompatModuleMigrationTest extends TestCase {

	/**
	 * Anonymous subclass used to call the protected migrate_capture_on_status_change() method.
	 */
	private object $testee;

	public function setUp(): void {
		parent::setUp();

		/**
		 * Intercept all "add_action" calls, and when encountering an exact call to
		 * `add_action( 'woocommerce_paypal_payments_gateway_migrate_on_update', $cb )`
		 * then the $cb is instantly invoked.
		 *
		 * Short: turns "add_action" into "call function"
		 */
		when( 'add_action' )->alias(
			static function ( string $hook, callable $cb ): void {
				if ( $hook === 'woocommerce_paypal_payments_gateway_migrate_on_update' ) {
					$cb();
				}
			}
		);

		$this->testee = new class extends CompatModule {
			public function run_migration(): void {
				$this->migrate_capture_on_status_change();
			}
		};
	}

	/**
	 * @dataProvider migration_cases
	 *
	 * @param array      $legacy_settings  Value of the legacy 'woocommerce-ppcp-settings' option.
	 * @param array      $payment_settings Value of the new 'woocommerce-ppcp-data-payment' option.
	 * @param array|null $expected_update  Expected payload passed to update_option, or null if no write should occur.
	 */
	public function test_migration( array $legacy_settings, array $payment_settings, ?array $expected_update ): void {
		when( 'get_option' )->alias(
			static function ( string $key ) use ( $legacy_settings, $payment_settings ): array {
				return $key === 'woocommerce-ppcp-settings' ? $legacy_settings : $payment_settings;
			}
		);

		if ( $expected_update === null ) {
			expect( 'update_option' )->never();
		} else {
			expect( 'update_option' )
				->once()
				->with( 'woocommerce-ppcp-data-payment', $expected_update );
		}

		$this->testee->run_migration();
		$this->addToAssertionCount( 1 );
	}

	public function migration_cases(): array {
		return array(
			'legacy setting absent'  => array(
				'legacy_settings'  => array(),
				'payment_settings' => array(),
				'expected_update'  => null,
			),
			'already migrated'       => array(
				'legacy_settings'  => array( 'capture_on_status_change' => false ),
				'payment_settings' => array( 'capture_on_status_change' => true ),
				'expected_update'  => null,
			),
			'migrates false'         => array(
				'legacy_settings'  => array( 'capture_on_status_change' => false ),
				'payment_settings' => array(),
				'expected_update'  => array( 'capture_on_status_change' => false ),
			),
			'migrates true'          => array(
				'legacy_settings'  => array( 'capture_on_status_change' => true ),
				'payment_settings' => array(),
				'expected_update'  => array( 'capture_on_status_change' => true ),
			),
		);
	}
}
