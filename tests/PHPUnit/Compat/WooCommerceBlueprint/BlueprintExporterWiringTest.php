<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\Service\DataSanitizer;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use function Brain\Monkey\Functions\when;

/**
 * Exercises the Blueprint exporters as modules/ppcp-compat/services.php actually
 * wires them, rather than by constructing them directly.
 *
 * The unit tests build `new PayPalSettingsExporter( $sanitizer, $flag )` themselves,
 * so they keep passing if both container entries are registered with the same flag.
 * That mistake either stops the opt-in export carrying credentials or makes every
 * export carry them, and nothing else in the suite would notice.
 *
 * @covers \WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint\PayPalSettingsExporter
 * @covers \WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint\PayPalBlueprintBootstrap
 */
class BlueprintExporterWiringTest extends TestCase {

	private const CREDENTIAL_KEYS = array(
		'client_id',
		'client_secret',
		'merchant_id',
		'merchant_email',
	);

	public function setUp(): void {
		parent::setUp();

		$stored = array(
			'woocommerce-ppcp-data-common' => array(
				'client_id'      => 'client-id-value',
				'client_secret'  => 'client-secret-value',
				'merchant_id'    => 'MERCHANT123',
				'merchant_email' => 'merchant@example.com',
			),
		);

		when( 'get_option' )->alias(
			function ( $name, $default = false ) use ( $stored ) {
				return $stored[ $name ] ?? $default;
			}
		);
	}

	/**
	 * Loads the real services.php and resolves ids through it, so the closures under
	 * test are the production ones.
	 *
	 * @param string $id Service id to resolve.
	 * @return mixed
	 */
	private function resolve( string $id ) {
		$services = require ROOT_DIR . '/modules/ppcp-compat/services.php';

		$container = Mockery::mock( ContainerInterface::class );
		$container->shouldReceive( 'get' )->andReturnUsing(
			function ( string $requested ) use ( &$services, &$container ) {
				if ( 'settings.service.sanitizer' === $requested ) {
					return Mockery::mock( DataSanitizer::class );
				}

				$factory = $services[ $requested ];

				return $factory( $container );
			}
		);

		return $services[ $id ]( $container );
	}

	/**
	 * @param PayPalSettingsExporter $exporter Exporter to run.
	 * @return array<string, mixed>
	 */
	private function exported_common( PayPalSettingsExporter $exporter ): array {
		return $exporter->export()->prepare_json_array()['options']['woocommerce-ppcp-data-common'];
	}

	/**
	 * @test
	 */
	public function test_the_default_service_strips_credentials(): void {
		$common = $this->exported_common(
			$this->resolve( 'compat.blueprint.paypal_settings_exporter' )
		);

		foreach ( self::CREDENTIAL_KEYS as $key ) {
			self::assertSame( '', $common[ $key ], "$key should be stripped by the default service" );
		}
	}

	/**
	 * @test
	 */
	public function test_the_opt_in_service_keeps_credentials(): void {
		$common = $this->exported_common(
			$this->resolve( 'compat.blueprint.paypal_settings_exporter_with_connection' )
		);

		foreach ( self::CREDENTIAL_KEYS as $key ) {
			self::assertNotSame( '', $common[ $key ], "$key should survive the opt-in service" );
		}
	}

	/**
	 * The assertion that fails if both container entries are given the same flag.
	 *
	 * @test
	 */
	public function test_the_two_services_are_wired_with_opposite_flags(): void {
		$default = $this->resolve( 'compat.blueprint.paypal_settings_exporter' );
		$opt_in  = $this->resolve( 'compat.blueprint.paypal_settings_exporter_with_connection' );

		self::assertSame( PayPalSettingsExporter::ALIAS, $default->get_alias() );
		self::assertSame( PayPalSettingsExporter::ALIAS_WITH_CONNECTION, $opt_in->get_alias() );

		$default_common = $this->exported_common( $default );
		$opt_in_common  = $this->exported_common( $opt_in );

		self::assertNotEquals(
			$default_common,
			$opt_in_common,
			'Both services produced the same payload, so they are wired with the same flag.'
		);
	}

	/**
	 * @test
	 */
	public function test_the_bootstrap_registers_both_exporters(): void {
		$bootstrap = $this->resolve( 'compat.blueprint.bootstrap' );

		$exporters = $bootstrap->register_exporters( array() );

		$aliases = array_map(
			static function ( $exporter ): string {
				return $exporter->get_alias();
			},
			$exporters
		);

		self::assertContains( PayPalSettingsExporter::ALIAS, $aliases );
		self::assertContains( PayPalSettingsExporter::ALIAS_WITH_CONNECTION, $aliases );
		self::assertCount( 2, $aliases );
	}

	/**
	 * @test
	 */
	public function test_the_bootstrap_preserves_exporters_from_other_plugins(): void {
		$bootstrap = $this->resolve( 'compat.blueprint.bootstrap' );
		$foreign   = Mockery::mock( 'Automattic\WooCommerce\Blueprint\Exporters\StepExporter' );

		$exporters = $bootstrap->register_exporters( array( $foreign ) );

		self::assertContains( $foreign, $exporters );
		self::assertCount( 3, $exporters );
	}
}
