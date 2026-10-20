<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint\PayPalSettingsExporter
 */
class PayPalSettingsExporterTest extends TestCase {

	private const CREDENTIAL_KEYS = array(
		'client_id',
		'client_secret',
		'merchant_id',
		'merchant_email',
	);

	public function setUp(): void {
		parent::setUp();

		$stored = array(
			'woocommerce-ppcp-data-common'      => array(
				'client_id'          => 'client-id-value',
				'client_secret'      => 'client-secret-value',
				'merchant_id'        => 'MERCHANT123',
				'merchant_email'     => 'merchant@example.com',
				'merchant_country'   => 'US',
				'merchant_connected' => true,
			),
			'woocommerce-ppcp-data-styling'     => array( 'cart' => array( 'shape' => 'pill' ) ),
			'woocommerce-ppcp-is-new-merchant'  => 1,
		);

		when( 'get_option' )->alias(
			function ( $name, $default = false ) use ( $stored ) {
				return $stored[ $name ] ?? $default;
			}
		);
	}

	private function exported_options( bool $include_connection ): array {
		$exporter = new PayPalSettingsExporter( new ConnectionDataSanitizer(), $include_connection );

		return $exporter->export()->prepare_json_array()['options'];
	}

	/**
	 * @test
	 */
	public function test_default_export_carries_no_credentials(): void {
		$common = $this->exported_options( false )['woocommerce-ppcp-data-common'];

		foreach ( self::CREDENTIAL_KEYS as $key ) {
			self::assertSame( '', $common[ $key ], "'{$key}' was not cleared from the default export" );
		}
	}

	/**
	 * @test
	 */
	public function test_opt_in_export_carries_the_credentials(): void {
		$common = $this->exported_options( true )['woocommerce-ppcp-data-common'];

		self::assertSame( 'client-id-value', $common['client_id'] );
		self::assertSame( 'client-secret-value', $common['client_secret'] );
		self::assertSame( 'MERCHANT123', $common['merchant_id'] );
		self::assertSame( 'merchant@example.com', $common['merchant_email'] );
	}

	/**
	 * Stripping the connection details must not strip the settings the export exists
	 * to carry.
	 *
	 * @test
	 */
	public function test_default_export_still_carries_the_settings(): void {
		self::assertSame(
			array( 'cart' => array( 'shape' => 'pill' ) ),
			$this->exported_options( false )['woocommerce-ppcp-data-styling']
		);
	}

	/**
	 * The legacy flag was removed from the allowlist: importing it as truthy makes
	 * MigrationManager permanently skip the legacy migrations on the target store.
	 *
	 * @test
	 */
	public function test_legacy_new_merchant_flag_is_never_exported(): void {
		self::assertArrayNotHasKey(
			'woocommerce-ppcp-is-new-merchant',
			$this->exported_options( false )
		);
		self::assertArrayNotHasKey(
			'woocommerce-ppcp-is-new-merchant',
			$this->exported_options( true )
		);
	}

	/**
	 * What an instance exports is fixed at construction, so the two registered
	 * exporters cannot be made to swap behaviour at runtime.
	 *
	 * @test
	 */
	public function test_the_exported_payload_follows_the_constructor_flag(): void {
		$default_common = $this->exported_options( false )['woocommerce-ppcp-data-common'];
		$opt_in_common  = $this->exported_options( true )['woocommerce-ppcp-data-common'];

		self::assertSame( '', $default_common['client_secret'] );
		self::assertSame( 'client-secret-value', $opt_in_common['client_secret'] );
	}

	/**
	 * Two exporters are distinguished purely by their selection identity. Asking for
	 * the plain step name must never resolve to the credential-bearing exporter.
	 *
	 * @test
	 */
	public function test_the_two_exporters_have_distinct_selection_identities(): void {
		$default  = new PayPalSettingsExporter( new ConnectionDataSanitizer(), false );
		$with_conn = new PayPalSettingsExporter( new ConnectionDataSanitizer(), true );

		self::assertSame( 'paypalSettings', $default->get_alias() );
		self::assertSame( 'paypalSettingsWithConnection', $with_conn->get_alias() );

		self::assertNotSame( $default->get_alias(), $with_conn->get_alias() );
		self::assertNotSame( $default->get_step_name(), $with_conn->get_step_name() );
	}

	/**
	 * Both exporters emit the same step, so the importer and the file format are
	 * unchanged by the opt-in.
	 *
	 * @test
	 */
	public function test_both_exporters_emit_the_same_step(): void {
		$default_step = ( new PayPalSettingsExporter( new ConnectionDataSanitizer(), false ) )->export();
		$opt_in_step  = ( new PayPalSettingsExporter( new ConnectionDataSanitizer(), true ) )->export();

		self::assertInstanceOf( SetPayPalSettings::class, $default_step );
		self::assertInstanceOf( SetPayPalSettings::class, $opt_in_step );
		self::assertSame(
			$default_step->prepare_json_array()['step'],
			$opt_in_step->prepare_json_array()['step']
		);
	}

	/**
	 * Exporting requires both capabilities, not either one.
	 *
	 * @dataProvider capability_combinations
	 * @test
	 *
	 * @param bool $manage_woocommerce Whether the user can manage WooCommerce.
	 * @param bool $manage_options     Whether the user can manage options.
	 * @param bool $expected           Expected result of the capability check.
	 */
	public function test_export_requires_both_capabilities( bool $manage_woocommerce, bool $manage_options, bool $expected ): void {
		when( 'current_user_can' )->alias(
			function ( string $capability ) use ( $manage_woocommerce, $manage_options ): bool {
				if ( 'manage_woocommerce' === $capability ) {
					return $manage_woocommerce;
				}

				if ( 'manage_options' === $capability ) {
					return $manage_options;
				}

				return false;
			}
		);

		foreach ( array( false, true ) as $include_connection ) {
			$exporter = new PayPalSettingsExporter( new ConnectionDataSanitizer(), $include_connection );

			self::assertSame( $expected, $exporter->check_step_capabilities() );
		}
	}

	/**
	 * @return array<string, array{bool, bool, bool}>
	 */
	public function capability_combinations(): array {
		return array(
			'both capabilities'      => array( true, true, true ),
			'only manage_woocommerce' => array( true, false, false ),
			'only manage_options'    => array( false, true, false ),
			'neither'                => array( false, false, false ),
		);
	}
}
