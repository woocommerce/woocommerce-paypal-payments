<?php
declare( strict_types=1 );

namespace PHPUnit\Settings\Service;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PartnerAttribution;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PaymentLevelEligibility;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\Service\AgenticBetaBannerEligibility;
use WooCommerce\PayPalCommerce\Settings\Service\ScriptDataHandler;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Settings\Service\ScriptDataHandler
 */
class ScriptDataHandlerTest extends TestCase {

	/**
	 * Temp .asset.php fixture files required by the code under test, keyed by asset name.
	 *
	 * @var array<string, string>
	 */
	private array $asset_fixture_paths = array();

	public function setUp(): void {
		parent::setUp();

		foreach ( array( 'index.js', 'styles.css' ) as $asset_name ) {
			$path = tempnam( sys_get_temp_dir(), 'ppcp-asset' );
			file_put_contents( $path, "<?php return array( 'dependencies' => array(), 'version' => '1.0.0' );" );
			$this->asset_fixture_paths[ $asset_name ] = $path;
		}
	}

	public function tearDown(): void {
		foreach ( $this->asset_fixture_paths as $path ) {
			@unlink( $path );
		}

		parent::tearDown();
	}

	/**
	 * GIVEN the request is not the PayPal settings screen, even though it shares
	 * the WooCommerce settings hook suffix, or the hook suffix itself is unrelated
	 * WHEN localize_scripts() runs
	 * THEN no admin bundle script or style is registered or enqueued
	 *
	 * @dataProvider guard_short_circuit_provider
	 */
	public function test_localize_scripts_skips_enqueue_when_guard_fails( string $hook_suffix, bool $is_plugin_settings_page ): void {
		expect( 'wp_register_script' )->never();
		expect( 'wp_enqueue_script' )->never();
		expect( 'wp_register_style' )->never();
		expect( 'wp_enqueue_style' )->never();

		$asset_getter = Mockery::mock( AssetGetter::class );
		$asset_getter->shouldNotReceive( 'get_asset_php_path' );

		$handler = $this->create_handler(
			array(
				'asset_getter'             => $asset_getter,
				'is_plugin_settings_page'  => $is_plugin_settings_page,
			)
		);

		$handler->localize_scripts( $hook_suffix );

		$this->addToAssertionCount( 1 );
	}

	public function guard_short_circuit_provider(): array {
		return array(
			'shared WooCommerce settings hook, but not the PayPal plugin screen' => array( 'woocommerce_page_wc-settings', false ),
			'PayPal plugin screen flag set, but on an unrelated admin page'       => array( 'plugins.php', true ),
		);
	}

	/**
	 * GIVEN the request is the PayPal plugin's own settings screen
	 * WHEN localize_scripts() runs
	 * THEN the admin settings bundle is enqueued under its public handle
	 */
	public function test_localize_scripts_enqueues_admin_bundle_on_plugin_settings_page(): void {
		when( 'do_action' )->justReturn( null );
		when( 'wp_register_script' )->justReturn( true );
		when( 'wp_set_script_translations' )->justReturn( true );
		when( 'wp_register_style' )->justReturn( true );
		when( 'wp_enqueue_style' )->justReturn( true );
		when( 'wp_localize_script' )->justReturn( true );
		when( 'wp_dequeue_script' )->justReturn( true );
		when( 'admin_url' )->returnArg();
		when( 'get_option' )->alias(
			static function ( $key, $default = null ) {
				return $default;
			}
		);

		$enqueued_handles = array();
		when( 'wp_enqueue_script' )->alias(
			static function ( $handle ) use ( &$enqueued_handles ) {
				$enqueued_handles[] = $handle;
				return true;
			}
		);

		$asset_getter = Mockery::mock( AssetGetter::class );
		$asset_getter->shouldReceive( 'get_asset_php_path' )->andReturnUsing(
			fn ( string $asset_name ) => $this->asset_fixture_paths[ $asset_name ]
		);
		$asset_getter->shouldReceive( 'get_asset_url' )->andReturn( 'https://example.com/asset.js' );
		$asset_getter->shouldReceive( 'get_static_asset_url' )->andReturn( 'https://example.com/images/' );

		$handler = $this->create_handler(
			array(
				'asset_getter'            => $asset_getter,
				'paylater_is_available'   => false,
				'is_plugin_settings_page' => true,
			)
		);

		$handler->localize_scripts( 'woocommerce_page_wc-settings' );

		$this->assertContains( 'ppcp-admin-settings', $enqueued_handles );
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function create_handler( array $overrides = array() ): ScriptDataHandler {
		$payment_level_eligibility = Mockery::mock( PaymentLevelEligibility::class );
		$payment_level_eligibility->shouldReceive( 'is_eligible' )->andReturn( false );

		$agentic_beta_banner_eligibility = Mockery::mock( AgenticBetaBannerEligibility::class );
		$agentic_beta_banner_eligibility->shouldReceive( 'is_eligible' )->andReturn( false );

		$defaults = array(
			'asset_getter'                     => Mockery::mock( AssetGetter::class ),
			'paylater_is_available'            => false,
			'store_country'                    => 'US',
			'merchant_id'                       => 'merchant-1',
			'button_language_choices'          => array(),
			'partner_attribution'              => Mockery::mock( PartnerAttribution::class ),
			'settings_provider'                => Mockery::mock( SettingsProvider::class ),
			'payment_level_eligibility'        => $payment_level_eligibility,
			'is_bcdc_override_flag_enabled'    => false,
			'agentic_beta_banner_eligibility'  => $agentic_beta_banner_eligibility,
			'is_sdk_v6_active'                 => false,
			'is_plugin_settings_page'          => false,
		);

		$args = array_merge( $defaults, $overrides );

		return new ScriptDataHandler(
			$args['asset_getter'],
			$args['paylater_is_available'],
			$args['store_country'],
			$args['merchant_id'],
			$args['button_language_choices'],
			$args['partner_attribution'],
			$args['settings_provider'],
			$args['payment_level_eligibility'],
			$args['is_bcdc_override_flag_enabled'],
			$args['agentic_beta_banner_eligibility'],
			$args['is_sdk_v6_active'],
			$args['is_plugin_settings_page']
		);
	}
}
