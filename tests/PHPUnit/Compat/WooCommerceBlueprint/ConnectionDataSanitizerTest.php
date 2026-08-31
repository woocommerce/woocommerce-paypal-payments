<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

use ReflectionClass;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint\ConnectionDataSanitizer
 */
class ConnectionDataSanitizerTest extends TestCase {

	private ConnectionDataSanitizer $sut;

	public function setUp(): void {
		parent::setUp();

		$this->sut = new ConnectionDataSanitizer();
	}

	/**
	 * A fully populated common option, as exported from a connected store.
	 */
	private function connected_common_option(): array {
		return array(
			'use_sandbox'           => true,
			'use_manual_connection' => true,
			'is_send_only_country'  => true,
			'merchant_connected'    => true,
			'sandbox_merchant'      => true,
			'merchant_id'           => 'MERCHANT123',
			'merchant_email'        => 'merchant@example.com',
			'merchant_country'      => 'US',
			'client_id'             => 'client-id-value',
			'client_secret'         => 'client-secret-value',
			'seller_type'           => 'business',
			'wc_installation_path'  => 'core_profiler',
		);
	}

	/**
	 * The credentials and identifiers named in the security report must never
	 * survive a default export.
	 *
	 * @test
	 */
	public function test_credentials_are_removed(): void {
		$result = $this->sut->sanitize(
			array( ConnectionDataSanitizer::OPTION_COMMON => $this->connected_common_option() )
		);

		$common = $result[ ConnectionDataSanitizer::OPTION_COMMON ];

		self::assertSame( '', $common['client_id'] );
		self::assertSame( '', $common['client_secret'] );
		self::assertSame( '', $common['merchant_id'] );
		self::assertSame( '', $common['merchant_email'] );
	}

	/**
	 * merchant_country and wc_installation_path are not recomputed when the target
	 * store connects, so a stale value would silently mis-gate payment methods and
	 * lock the store into the branded-only experience.
	 *
	 * @test
	 */
	public function test_configuration_the_target_store_cannot_recompute_is_removed(): void {
		$result = $this->sut->sanitize(
			array( ConnectionDataSanitizer::OPTION_COMMON => $this->connected_common_option() )
		);

		$common = $result[ ConnectionDataSanitizer::OPTION_COMMON ];

		self::assertSame( '', $common['merchant_country'] );
		self::assertSame( '', $common['wc_installation_path'] );
	}

	/**
	 * @test
	 */
	public function test_connection_state_is_left_self_consistent(): void {
		$result = $this->sut->sanitize(
			array( ConnectionDataSanitizer::OPTION_COMMON => $this->connected_common_option() )
		);

		$common = $result[ ConnectionDataSanitizer::OPTION_COMMON ];

		self::assertFalse( $common['merchant_connected'] );
		self::assertFalse( $common['sandbox_merchant'] );
		self::assertSame( 'unknown', $common['seller_type'] );
		self::assertFalse( $common['use_sandbox'] );
		self::assertFalse( $common['use_manual_connection'] );
	}

	/**
	 * is_send_only_country describes the target store, not the exporting merchant,
	 * and GeneralSettings recomputes it on every load.
	 *
	 * @test
	 */
	public function test_is_send_only_country_is_preserved(): void {
		$result = $this->sut->sanitize(
			array( ConnectionDataSanitizer::OPTION_COMMON => $this->connected_common_option() )
		);

		self::assertTrue( $result[ ConnectionDataSanitizer::OPTION_COMMON ]['is_send_only_country'] );
	}

	/**
	 * Resetting setup_done would un-gate set_defaults_for_new_merchant(), which
	 * overwrites the styling settings this export exists to carry.
	 *
	 * @test
	 */
	public function test_onboarding_mirrors_the_disconnect_state_and_keeps_setup_done(): void {
		$result = $this->sut->sanitize(
			array(
				ConnectionDataSanitizer::OPTION_ONBOARDING => array(
					'completed'            => true,
					'step'                 => 5,
					'is_casual_seller'     => false,
					'accept_card_payments' => true,
					'products'             => array( 'subscriptions' ),
					'setup_done'           => true,
					'gateways_synced'      => true,
					'gateways_refreshed'   => true,
				),
			)
		);

		$onboarding = $result[ ConnectionDataSanitizer::OPTION_ONBOARDING ];

		self::assertFalse( $onboarding['completed'] );
		self::assertSame( 0, $onboarding['step'] );
		self::assertFalse( $onboarding['gateways_synced'] );
		self::assertFalse( $onboarding['gateways_refreshed'] );

		self::assertTrue( $onboarding['setup_done'], 'setup_done must survive sanitization' );
		self::assertFalse( $onboarding['is_casual_seller'] );
		self::assertTrue( $onboarding['accept_card_payments'] );
		self::assertSame( array( 'subscriptions' ), $onboarding['products'] );
	}

	/**
	 * @test
	 */
	public function test_unrelated_options_are_untouched(): void {
		$options = array(
			'woocommerce-ppcp-data-styling' => array( 'cart' => array( 'shape' => 'pill' ) ),
			'woocommerce_venmo_settings'    => array( 'enabled' => 'yes' ),
		);

		self::assertSame( $options, $this->sut->sanitize( $options ) );
	}

	/**
	 * Sanitizing must not introduce keys the stored option never had, otherwise the
	 * import would write defaults the source store did not have either.
	 *
	 * @test
	 */
	public function test_absent_keys_are_not_added(): void {
		$result = $this->sut->sanitize(
			array( ConnectionDataSanitizer::OPTION_COMMON => array( 'client_id' => 'abc' ) )
		);

		self::assertSame(
			array( 'client_id' => '' ),
			$result[ ConnectionDataSanitizer::OPTION_COMMON ]
		);
	}

	/**
	 * @test
	 */
	public function test_non_array_option_values_are_ignored(): void {
		$options = array(
			ConnectionDataSanitizer::OPTION_COMMON     => 'unexpected-scalar',
			ConnectionDataSanitizer::OPTION_ONBOARDING => null,
		);

		self::assertSame( $options, $this->sut->sanitize( $options ) );
	}

	/**
	 * Drift guard: CONNECTION_DEFAULTS duplicates values owned by the protected
	 * GeneralSettings::get_defaults(), so this fails if the model changes one.
	 *
	 * @test
	 */
	public function test_connection_defaults_match_the_general_settings_defaults(): void {
		$reflection = new ReflectionClass( GeneralSettings::class );
		$method     = $reflection->getMethod( 'get_defaults' );
		$method->setAccessible( true );

		$model_defaults = $method->invoke( $reflection->newInstanceWithoutConstructor() );

		foreach ( ConnectionDataSanitizer::CONNECTION_DEFAULTS as $key => $neutral_value ) {
			self::assertArrayHasKey(
				$key,
				$model_defaults,
				"GeneralSettings no longer defines '{$key}'"
			);
			self::assertSame(
				$model_defaults[ $key ],
				$neutral_value,
				"Neutral value for '{$key}' no longer matches GeneralSettings::get_defaults()"
			);
		}
	}

	/**
	 * Reverse of the guard above: sanitize() only touches keys it knows about, so a
	 * new merchant field must be neutralised or explicitly declared safe to export.
	 *
	 * @test
	 */
	public function test_every_merchant_field_is_either_sanitized_or_knowingly_exported(): void {
		// Recomputed from the target store on every load, so exporting it is harmless.
		$exported_on_purpose = array( 'is_send_only_country' );

		$reflection = new ReflectionClass( GeneralSettings::class );
		$method     = $reflection->getMethod( 'get_defaults' );
		$method->setAccessible( true );

		$model_defaults = $method->invoke( $reflection->newInstanceWithoutConstructor() );

		$unaccounted = array_diff(
			array_keys( $model_defaults ),
			array_keys( ConnectionDataSanitizer::CONNECTION_DEFAULTS ),
			$exported_on_purpose
		);

		self::assertSame(
			array(),
			$unaccounted,
			'GeneralSettings defines field(s) the sanitizer does not handle: '
				. implode( ', ', $unaccounted )
				. '. Add each to ConnectionDataSanitizer::CONNECTION_DEFAULTS, or to '
				. '$exported_on_purpose here once confirmed safe to share.'
		);
	}
}
