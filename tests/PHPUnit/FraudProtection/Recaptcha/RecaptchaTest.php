<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\FraudProtection\Recaptcha;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\FraudProtection\PersistentCounter;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class RecaptchaTest extends TestCase {
	use MockeryPHPUnitIntegration;

	private $recaptcha_options = array(
		'guest_only'    => '',
		'site_key_v3'   => 'v3-site-key',
		'secret_key_v3' => 'v3-secret-key',
		'site_key_v2'   => 'v2-site-key',
		'secret_key_v2' => 'v2-secret-key',
		'v2_theme'      => 'light',
	);

	public function setUp(): void {
		parent::setUp();

		when( 'is_user_logged_in' )->justReturn( false );
		when( 'has_block' )->justReturn( false );
		when( 'is_add_payment_method_page' )->justReturn( false );
	}

	/**
	 * Builds a Recaptcha instance configured so should_use_recaptcha() is true
	 * (valid v2/v3 keys, integration enabled), so tests only need to control
	 * the location-gating logic under test.
	 *
	 * @param string[] $payment_methods The gateway IDs reCAPTCHA protects, mirroring the
	 *                                  `fraud-protection.recaptcha.payment-methods` service.
	 */
	private function make_testee( SettingsStatus $settings_status, array $payment_methods = array() ): Recaptcha {
		$integration = Mockery::mock( RecaptchaIntegration::class );
		$integration->enabled = 'yes';
		$integration->shouldReceive( 'get_option' )->andReturnUsing(
			function ( string $key, $default = false ) {
				return $this->recaptcha_options[ $key ] ?? $default;
			}
		);

		$asset_getter = Mockery::mock( AssetGetter::class );
		$asset_getter->shouldReceive( 'get_asset_url' )->andReturn( 'https://example.com/recaptcha-handler.js' );

		$logger = Mockery::mock( LoggerInterface::class );
		$rejection_counter = Mockery::mock( PersistentCounter::class );

		return new Recaptcha(
			$integration,
			$payment_methods,
			$asset_getter,
			'1.0.0',
			$logger,
			$rejection_counter,
			$settings_status
		);
	}

	/**
	 * Stubs WC()->payment_gateways->get_available_payment_gateways() to return the given
	 * gateway IDs as available, for has_protected_gateway_on_current_page() to check against.
	 *
	 * @param string[] $available_gateway_ids
	 */
	private function stub_available_gateways( array $available_gateway_ids = array() ): void {
		$payment_gateways = Mockery::mock();
		$payment_gateways->shouldReceive( 'get_available_payment_gateways' )->andReturn(
			array_fill_keys( $available_gateway_ids, true )
		);

		$woocommerce = Mockery::mock();
		$woocommerce->payment_gateways = $payment_gateways;

		when( 'WC' )->justReturn( $woocommerce );
	}

	public function test_enqueue_scripts_skips_on_unsupported_page(): void {
		when( 'is_product' )->justReturn( false );
		when( 'is_cart' )->justReturn( false );
		when( 'is_checkout' )->justReturn( false );

		$settings_status = Mockery::mock( SettingsStatus::class );
		$settings_status->shouldNotReceive( 'is_smart_button_enabled_for_location' );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_recaptcha_should_enqueue', false, null )
			->andReturn( false );
		expect( 'wp_enqueue_script' )->never();

		$this->make_testee( $settings_status )->enqueue_scripts();
	}

	public function test_enqueue_scripts_skips_when_location_disabled(): void {
		when( 'is_product' )->justReturn( true );
		when( 'is_cart' )->justReturn( false );
		when( 'is_checkout' )->justReturn( false );

		$settings_status = Mockery::mock( SettingsStatus::class );
		$settings_status->shouldReceive( 'is_smart_button_enabled_for_location' )
			->with( 'product' )
			->andReturn( false );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_recaptcha_should_enqueue', false, 'product' )
			->andReturn( false );
		expect( 'wp_enqueue_script' )->never();

		$this->make_testee( $settings_status )->enqueue_scripts();
	}

	public function test_enqueue_scripts_runs_when_location_enabled(): void {
		when( 'is_product' )->justReturn( true );
		when( 'is_cart' )->justReturn( false );
		when( 'is_checkout' )->justReturn( false );

		$settings_status = Mockery::mock( SettingsStatus::class );
		$settings_status->shouldReceive( 'is_smart_button_enabled_for_location' )
			->with( 'product' )
			->andReturn( true );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_recaptcha_should_enqueue', true, 'product' )
			->andReturn( true );
		expect( 'wp_enqueue_script' )->twice();
		expect( 'wp_localize_script' )->once();

		$this->make_testee( $settings_status )->enqueue_scripts();
	}

	public function test_enqueue_scripts_checks_block_express_location_on_block_checkout(): void {
		when( 'is_product' )->justReturn( false );
		when( 'is_cart' )->justReturn( false );
		when( 'is_checkout' )->justReturn( true );
		when( 'has_block' )->justReturn( true );

		$this->stub_available_gateways();

		$settings_status = Mockery::mock( SettingsStatus::class );
		$settings_status->shouldReceive( 'is_smart_button_enabled_for_location' )
			->with( 'checkout-block-express' )
			->andReturn( false );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_recaptcha_should_enqueue', false, 'checkout-block-express' )
			->andReturn( false );
		expect( 'wp_enqueue_script' )->never();

		$this->make_testee( $settings_status )->enqueue_scripts();
	}

	/**
	 * GIVEN the smart-button location is disabled for checkout (no wallet button)
	 * WHEN a reCAPTCHA-protected gateway (e.g. ACDC) is still available as a
	 *      selectable WC payment gateway at checkout
	 * THEN reCAPTCHA must still enqueue, since ACDC's availability is independent
	 *      of the smart-button location setting.
	 */
	public function test_enqueue_scripts_runs_when_protected_gateway_available_despite_disabled_location(): void {
		when( 'is_product' )->justReturn( false );
		when( 'is_cart' )->justReturn( false );
		when( 'is_checkout' )->justReturn( true );
		when( 'has_block' )->justReturn( false );

		$this->stub_available_gateways( array( CreditCardGateway::ID ) );

		$settings_status = Mockery::mock( SettingsStatus::class );
		$settings_status->shouldReceive( 'is_smart_button_enabled_for_location' )
			->with( 'checkout' )
			->andReturn( false );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_recaptcha_should_enqueue', true, 'checkout' )
			->andReturn( true );
		expect( 'wp_enqueue_script' )->twice();
		expect( 'wp_localize_script' )->once();

		$this->make_testee( $settings_status, array( CreditCardGateway::ID ) )->enqueue_scripts();
	}

	/**
	 * GIVEN the smart-button location is disabled for checkout
	 * WHEN no reCAPTCHA-protected gateway is available there either (e.g. only BACS)
	 * THEN reCAPTCHA must not enqueue.
	 */
	public function test_enqueue_scripts_skips_at_checkout_when_no_protected_gateway_available(): void {
		when( 'is_product' )->justReturn( false );
		when( 'is_cart' )->justReturn( false );
		when( 'is_checkout' )->justReturn( true );
		when( 'has_block' )->justReturn( false );

		$this->stub_available_gateways( array( 'bacs' ) );

		$settings_status = Mockery::mock( SettingsStatus::class );
		$settings_status->shouldReceive( 'is_smart_button_enabled_for_location' )
			->with( 'checkout' )
			->andReturn( false );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_recaptcha_should_enqueue', false, 'checkout' )
			->andReturn( false );
		expect( 'wp_enqueue_script' )->never();

		$this->make_testee( $settings_status, array( CreditCardGateway::ID ) )->enqueue_scripts();
	}

	/**
	 * GIVEN the current page is the My Account "Add payment method" page (no smart-button
	 *       location applies there at all)
	 * WHEN a reCAPTCHA-protected gateway is available there (e.g. AXO rendering can_render_dcc())
	 * THEN reCAPTCHA must still enqueue.
	 */
	public function test_enqueue_scripts_runs_on_add_payment_method_page_when_protected_gateway_available(): void {
		when( 'is_product' )->justReturn( false );
		when( 'is_cart' )->justReturn( false );
		when( 'is_checkout' )->justReturn( false );
		when( 'is_add_payment_method_page' )->justReturn( true );

		$this->stub_available_gateways( array( 'ppcp-axo-gateway' ) );

		$settings_status = Mockery::mock( SettingsStatus::class );
		$settings_status->shouldNotReceive( 'is_smart_button_enabled_for_location' );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_recaptcha_should_enqueue', true, null )
			->andReturn( true );
		expect( 'wp_enqueue_script' )->twice();
		expect( 'wp_localize_script' )->once();

		$this->make_testee( $settings_status, array( 'ppcp-axo-gateway' ) )->enqueue_scripts();
	}

	public function test_enqueue_scripts_checks_classic_checkout_location(): void {
		when( 'is_product' )->justReturn( false );
		when( 'is_cart' )->justReturn( false );
		when( 'is_checkout' )->justReturn( true );
		when( 'has_block' )->justReturn( false );

		$settings_status = Mockery::mock( SettingsStatus::class );
		$settings_status->shouldReceive( 'is_smart_button_enabled_for_location' )
			->with( 'checkout' )
			->andReturn( true );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_recaptcha_should_enqueue', true, 'checkout' )
			->andReturn( true );
		expect( 'wp_enqueue_script' )->twice();
		expect( 'wp_localize_script' )->once();

		$this->make_testee( $settings_status )->enqueue_scripts();
	}

	public function test_filter_can_force_enqueue_on_a_disabled_location(): void {
		when( 'is_product' )->justReturn( true );
		when( 'is_cart' )->justReturn( false );
		when( 'is_checkout' )->justReturn( false );

		$settings_status = Mockery::mock( SettingsStatus::class );
		$settings_status->shouldReceive( 'is_smart_button_enabled_for_location' )
			->with( 'product' )
			->andReturn( false );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_recaptcha_should_enqueue', false, 'product' )
			->andReturn( true );
		expect( 'wp_enqueue_script' )->twice();
		expect( 'wp_localize_script' )->once();

		$this->make_testee( $settings_status )->enqueue_scripts();
	}

	public function test_render_v2_container_returns_empty_when_location_disabled(): void {
		when( 'is_product' )->justReturn( true );
		when( 'is_cart' )->justReturn( false );
		when( 'is_checkout' )->justReturn( false );

		$settings_status = Mockery::mock( SettingsStatus::class );
		$settings_status->shouldReceive( 'is_smart_button_enabled_for_location' )
			->with( 'product' )
			->andReturn( false );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_recaptcha_should_enqueue', false, 'product' )
			->andReturn( false );

		$this->assertSame( '', $this->make_testee( $settings_status )->render_v2_container() );
	}

	public function test_render_v2_container_returns_markup_when_location_enabled(): void {
		when( 'is_product' )->justReturn( true );
		when( 'is_cart' )->justReturn( false );
		when( 'is_checkout' )->justReturn( false );

		$settings_status = Mockery::mock( SettingsStatus::class );
		$settings_status->shouldReceive( 'is_smart_button_enabled_for_location' )
			->with( 'product' )
			->andReturn( true );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_recaptcha_should_enqueue', true, 'product' )
			->andReturn( true );

		$this->assertStringContainsString(
			'ppcp-recaptcha-v2-container',
			$this->make_testee( $settings_status )->render_v2_container()
		);
	}
}
