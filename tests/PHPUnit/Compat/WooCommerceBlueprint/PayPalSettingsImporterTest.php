<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\Settings\DTO\PayLaterMessagingDTO;
use WooCommerce\PayPalCommerce\Settings\Service\DataSanitizer;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint\PayPalSettingsImporter
 */
class PayPalSettingsImporterTest extends TestCase {

	/**
	 * Options written during a test run.
	 *
	 * @var array<string, mixed>
	 */
	private array $written = array();

	/**
	 * Location keys passed to the sanitizer, per option.
	 *
	 * @var array<string, array<int, string>>
	 */
	private array $hydrated = array();

	/**
	 * @var DataSanitizer|Mockery\MockInterface
	 */
	private $sanitizer;

	public function setUp(): void {
		parent::setUp();

		$this->written = array();

		when( 'get_option' )->alias(
			function ( $name, $default = false ) {
				return $this->written[ $name ] ?? $default;
			}
		);

		when( 'update_option' )->alias(
			function ( $name, $value ) {
				$this->written[ $name ] = $value;

				return true;
			}
		);

		$this->hydrated  = array();
		$this->sanitizer = Mockery::mock( DataSanitizer::class );
		$this->sanitizer->shouldReceive( 'sanitize_location_style' )
			->andReturnUsing(
				function ( $value, $key ) {
					$this->hydrated['styling'][] = $key;

					return Mockery::mock( LocationStylingDTO::class );
				}
			);
		$this->sanitizer->shouldReceive( 'sanitize_paylater_messaging' )
			->andReturnUsing(
				function ( $value, $key ) {
					$this->hydrated['paylater'][] = $key;

					return Mockery::mock( PayLaterMessagingDTO::class );
				}
			);
	}

	/**
	 * @param array<string, mixed> $options Options to import.
	 * @return \Automattic\WooCommerce\Blueprint\StepProcessorResult
	 */
	private function import( array $options ) {
		$importer = new PayPalSettingsImporter( $this->sanitizer );

		$schema = json_decode(
			(string) wp_json_encode(
				array(
					'step'    => 'setPayPalSettings',
					'options' => $options,
				)
			)
		);

		return $importer->process( $schema );
	}

	/**
	 * The importer owns PayPal's own options only; everything else in a Blueprint
	 * belongs to another step's processor.
	 *
	 * @test
	 */
	public function test_options_outside_the_allowlist_are_never_written(): void {
		$this->import(
			array(
				'users_can_register'           => 1,
				'default_role'                 => 'editor',
				'siteurl'                      => 'https://other.example',
				'woocommerce-ppcp-data-common' => array( 'client_id' => '' ),
			)
		);

		self::assertArrayNotHasKey( 'users_can_register', $this->written );
		self::assertArrayNotHasKey( 'default_role', $this->written );
		self::assertArrayNotHasKey( 'siteurl', $this->written );
		self::assertArrayHasKey( 'woocommerce-ppcp-data-common', $this->written );
	}

	/**
	 * Styling is stored as typed DTOs, so a JSON payload of plain arrays has to be
	 * hydrated before it is written or the data model cannot load it back.
	 *
	 * @test
	 */
	public function test_styling_locations_are_hydrated_into_dtos(): void {
		$this->import(
			array(
				'woocommerce-ppcp-data-styling' => array(
					'cart'    => array( 'color' => 'gold' ),
					'product' => array( 'color' => 'blue' ),
				),
			)
		);

		$written = $this->written['woocommerce-ppcp-data-styling'];

		self::assertInstanceOf( LocationStylingDTO::class, $written['cart'] );
		self::assertInstanceOf( LocationStylingDTO::class, $written['product'] );
		self::assertSame( array( 'cart', 'product' ), $this->hydrated['styling'] );
	}

	/**
	 * @test
	 */
	public function test_paylater_messaging_locations_are_hydrated(): void {
		$this->import(
			array(
				'woocommerce-ppcp-data-paylater-messaging' => array(
					'cart'     => array( 'enabled' => true ),
					'checkout' => array( 'enabled' => false ),
				),
			)
		);

		$written = $this->written['woocommerce-ppcp-data-paylater-messaging'];

		self::assertInstanceOf( PayLaterMessagingDTO::class, $written['cart'] );
		self::assertInstanceOf( PayLaterMessagingDTO::class, $written['checkout'] );
		self::assertSame( array( 'cart', 'checkout' ), $this->hydrated['paylater'] );
	}

	/**
	 * A credentials-free export must import without erroring, otherwise the safe
	 * default is unusable.
	 *
	 * @test
	 */
	public function test_a_credential_free_payload_imports_without_errors(): void {
		$result = $this->import(
			array(
				'woocommerce-ppcp-data-common'     => array(
					'client_id'      => '',
					'client_secret'  => '',
					'merchant_id'    => '',
					'merchant_email' => '',
				),
				'woocommerce-ppcp-data-onboarding' => array(
					'completed'  => false,
					'setup_done' => true,
				),
			)
		);

		self::assertTrue( $result->is_success() );
		self::assertSame( array(), $result->get_messages( 'error' ) );
		self::assertSame( '', $this->written['woocommerce-ppcp-data-common']['client_id'] );
		self::assertTrue( $this->written['woocommerce-ppcp-data-onboarding']['setup_done'] );
	}

	/**
	 * Options are written whole rather than merged, so a settings-only payload
	 * would otherwise blank the credentials of a store that is already connected.
	 *
	 * @test
	 */
	public function test_a_settings_only_import_leaves_an_existing_connection_alone(): void {
		$this->written['woocommerce-ppcp-data-common']     = array(
			'client_id'          => 'TARGET-ID',
			'client_secret'      => 'TARGET-SECRET',
			'merchant_connected' => true,
		);
		$this->written['woocommerce-ppcp-data-onboarding'] = array(
			'completed'  => true,
			'setup_done' => true,
		);

		$this->import(
			array(
				'woocommerce-ppcp-data-common'     => array(
					'client_id'          => '',
					'client_secret'      => '',
					'merchant_connected' => false,
				),
				'woocommerce-ppcp-data-onboarding' => array(
					'completed'  => false,
					'setup_done' => true,
				),
			)
		);

		self::assertSame( 'TARGET-ID', $this->written['woocommerce-ppcp-data-common']['client_id'] );
		self::assertSame( 'TARGET-SECRET', $this->written['woocommerce-ppcp-data-common']['client_secret'] );
		self::assertTrue( $this->written['woocommerce-ppcp-data-common']['merchant_connected'] );
		self::assertTrue( $this->written['woocommerce-ppcp-data-onboarding']['completed'] );
	}

	/**
	 * @test
	 */
	public function test_an_opt_in_import_replaces_the_existing_connection(): void {
		$this->written['woocommerce-ppcp-data-common'] = array(
			'client_id'     => 'TARGET-ID',
			'client_secret' => 'TARGET-SECRET',
		);

		$this->import(
			array(
				'woocommerce-ppcp-data-common' => array(
					'client_id'     => 'SOURCE-ID',
					'client_secret' => 'SOURCE-SECRET',
				),
			)
		);

		self::assertSame( 'SOURCE-ID', $this->written['woocommerce-ppcp-data-common']['client_id'] );
		self::assertSame( 'SOURCE-SECRET', $this->written['woocommerce-ppcp-data-common']['client_secret'] );
	}

	/**
	 * @test
	 */
	public function test_a_payload_without_options_is_rejected(): void {
		$importer = new PayPalSettingsImporter( $this->sanitizer );

		$result = $importer->process( (object) array( 'step' => 'setPayPalSettings' ) );

		self::assertFalse( $result->is_success() );
		self::assertNotEmpty( $result->get_messages( 'error' ) );
		self::assertSame( array(), $this->written );
	}

	/**
	 * @test
	 */
	public function test_the_import_requires_both_capabilities(): void {
		$importer = new PayPalSettingsImporter( $this->sanitizer );
		$schema   = (object) array( 'step' => 'setPayPalSettings' );

		when( 'current_user_can' )->justReturn( true );
		self::assertTrue( $importer->check_step_capabilities( $schema ) );

		when( 'current_user_can' )->alias(
			function ( string $capability ): bool {
				return 'manage_woocommerce' === $capability;
			}
		);
		self::assertFalse( $importer->check_step_capabilities( $schema ) );

		when( 'current_user_can' )->justReturn( false );
		self::assertFalse( $importer->check_step_capabilities( $schema ) );
	}
}
