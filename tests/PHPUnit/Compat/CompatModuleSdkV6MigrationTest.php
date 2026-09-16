<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Compat;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Tests the migrate_sdk_v6_default() migration logic in CompatModule.
 *
 * @covers \WooCommerce\PayPalCommerce\Compat\CompatModule::migrate_sdk_v6_default
 */
class CompatModuleSdkV6MigrationTest extends TestCase {

	private const MARKER_OPTION   = 'woocommerce_ppcp-is_sdk_v6_default_migrated';
	private const ELIGIBLE_OPTION = 'woocommerce-ppcp-sdk-v6-eligible';
	private const FILTER          = 'woocommerce_paypal_payments_sdk_v6_unsupported_countries';

	/**
	 * Anonymous subclass used to call the protected migrate_sdk_v6_default() method.
	 */
	private object $testee;

	public function setUp(): void {
		parent::setUp();

		$this->testee = new class extends CompatModule {
			public function run_migration( ContainerInterface $c ): void {
				$this->migrate_sdk_v6_default( $c );
			}
		};
	}

	/**
	 * GIVEN a store that already went through the SDK v6 default handover
	 * WHEN the module runs
	 * THEN no migration callback is registered, so the store is never re-evaluated
	 */
	public function test_marker_already_set_registers_no_callback(): void {
		when( 'get_option' )->justReturn( true );

		$container = Mockery::mock( ContainerInterface::class );
		$container->shouldNotReceive( 'get' );

		expect( 'add_action' )->never();

		$this->testee->run_migration( $container );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a store already marked eligible for SDK v6 ('yes')
	 * WHEN the migration callback runs
	 * THEN the eligible option is left untouched, the settings provider is never consulted,
	 * AND the marker is still set so the migration does not run again
	 */
	public function test_already_eligible_store_is_untouched_and_marker_is_set(): void {
		$this->stub_add_action_autorun();

		when( 'get_option' )->alias(
			static function ( string $key ) {
				if ( $key === self::MARKER_OPTION ) {
					return false;
				}
				if ( $key === self::ELIGIBLE_OPTION ) {
					return 'yes';
				}
				return false;
			}
		);

		$container = Mockery::mock( ContainerInterface::class );
		$container->shouldNotReceive( 'get' );

		expect( 'update_option' )->never()->with( self::ELIGIBLE_OPTION, 'yes' );
		expect( 'update_option' )->once()->with( self::MARKER_OPTION, true );

		$this->testee->run_migration( $container );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a store held back or old enough to carry no eligibility answer, in a supported country
	 * WHEN the migration callback runs
	 * THEN the eligible option is handed over to 'yes'
	 * AND the marker is set so the migration does not run again
	 *
	 * @dataProvider eligible_no_or_absent_cases
	 */
	public function test_supported_store_is_handed_over_to_v6( $eligible_value ): void {
		$this->stub_add_action_autorun();

		when( 'get_option' )->alias(
			static function ( string $key ) use ( $eligible_value ) {
				if ( $key === self::MARKER_OPTION ) {
					return false;
				}
				if ( $key === self::ELIGIBLE_OPTION ) {
					return $eligible_value;
				}
				return false;
			}
		);

		$container = $this->container_resolving_to( 'US' );

		expect( 'update_option' )->once()->with( self::ELIGIBLE_OPTION, 'yes' );
		expect( 'update_option' )->once()->with( self::MARKER_OPTION, true );

		$this->testee->run_migration( $container );
		$this->addToAssertionCount( 1 );
	}

	/** @return array<string, array{string|false}> */
	public function eligible_no_or_absent_cases(): array {
		return array(
			'eligible explicitly no' => array( 'no' ),
			'eligible absent'        => array( false ),
		);
	}

	/**
	 * GIVEN a store held back, whose merchant country is withheld from SDK v6 by default (Mexico)
	 * WHEN the migration callback runs
	 * THEN the eligible option is written as 'no', since an absent value would now default to v6
	 * AND the marker is still set
	 *
	 * @dataProvider eligible_no_or_absent_cases
	 */
	public function test_unsupported_country_by_default_is_written_no( $eligible_value ): void {
		$this->stub_add_action_autorun();

		when( 'get_option' )->alias(
			static function ( string $key ) use ( $eligible_value ) {
				if ( $key === self::MARKER_OPTION ) {
					return false;
				}
				if ( $key === self::ELIGIBLE_OPTION ) {
					return $eligible_value;
				}
				return false;
			}
		);

		$container = $this->container_resolving_to( 'MX' );

		expect( 'update_option' )->once()->with( self::ELIGIBLE_OPTION, 'no' );
		expect( 'update_option' )->once()->with( self::MARKER_OPTION, true );

		$this->testee->run_migration( $container );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a third party widens the unsupported-countries filter to include the merchant's country
	 * WHEN the migration callback runs
	 * THEN the eligible option is written as 'no', since an absent value would now default to v6
	 * AND the marker is still set
	 */
	public function test_country_withheld_by_filter_is_written_no(): void {
		$this->stub_add_action_autorun();
		expectApplied( self::FILTER )->andReturn( array( 'MX', 'BR' ) );

		when( 'get_option' )->alias(
			static function ( string $key ) {
				if ( $key === self::MARKER_OPTION ) {
					return false;
				}
				if ( $key === self::ELIGIBLE_OPTION ) {
					return 'no';
				}
				return false;
			}
		);

		$container = $this->container_resolving_to( 'BR' );

		expect( 'update_option' )->once()->with( self::ELIGIBLE_OPTION, 'no' );
		expect( 'update_option' )->once()->with( self::MARKER_OPTION, true );

		$this->testee->run_migration( $container );
		$this->addToAssertionCount( 1 );
	}

	/**
	 * Builds a container that resolves 'settings.settings-provider' to a stub reporting
	 * the given merchant country, exactly once.
	 */
	private function container_resolving_to( string $merchant_country ): ContainerInterface {
		$settings_provider = $this->createStub( SettingsProvider::class );
		$settings_provider->method( 'merchant_country' )->willReturn( $merchant_country );

		$container = Mockery::mock( ContainerInterface::class );
		$container->shouldReceive( 'get' )
			->once()
			->with( 'settings.settings-provider' )
			->andReturn( $settings_provider );

		return $container;
	}

	/**
	 * Turns add_action() into an immediate call for the migrate-on-update hook this
	 * migration registers against.
	 */
	private function stub_add_action_autorun(): void {
		when( 'add_action' )->alias(
			static function ( string $hook, callable $cb ): void {
				if ( $hook === 'woocommerce_paypal_payments_gateway_migrate_on_update' ) {
					$cb();
				}
			}
		);
	}
}
