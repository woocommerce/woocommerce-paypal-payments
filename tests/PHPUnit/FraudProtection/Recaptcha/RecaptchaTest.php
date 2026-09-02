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
	 * @param string[]             $payment_methods  The gateway IDs reCAPTCHA protects, mirroring the
	 *                                                `fraud-protection.recaptcha.payment-methods` service.
	 * @param array                $option_overrides Overrides merged onto the default RecaptchaIntegration options,
	 *                                                e.g. `['log_rejections' => 'yes']`.
	 * @param LoggerInterface|null $logger           The shared, globally-gated logger. Defaults to a
	 *                                                catch-all stub; pass a bare mock (no stubs) to assert
	 *                                                it is never called.
	 * @param LoggerInterface|null $rejection_logger  The reCAPTCHA-specific rejection logger, independent
	 *                                                of the plugin-wide logging setting.
	 * @param PersistentCounter|null $rejection_counter Tracks how many attempts were rejected.
	 */
	private function make_testee(
		SettingsStatus $settings_status,
		array $payment_methods = array(),
		array $option_overrides = array(),
		?LoggerInterface $logger = null,
		?LoggerInterface $rejection_logger = null,
		?PersistentCounter $rejection_counter = null
	): Recaptcha {
		$options = array_merge( $this->recaptcha_options, $option_overrides );

		$integration = Mockery::mock( RecaptchaIntegration::class );
		$integration->enabled = 'yes';
		$integration->shouldReceive( 'get_option' )->andReturnUsing(
			function ( string $key, $default = false ) use ( $options ) {
				return $options[ $key ] ?? $default;
			}
		);

		$asset_getter = Mockery::mock( AssetGetter::class );
		$asset_getter->shouldReceive( 'get_asset_url' )->andReturn( 'https://example.com/recaptcha-handler.js' );

		$logger            = $logger ?? Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();
		$rejection_logger  = $rejection_logger ?? Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();
		$rejection_counter = $rejection_counter ?? Mockery::mock( PersistentCounter::class )->shouldIgnoreMissing();

		return new Recaptcha(
			$integration,
			$payment_methods,
			$asset_getter,
			'1.0.0',
			$logger,
			$rejection_counter,
			$settings_status,
			$rejection_logger
		);
	}

	/**
	 * Stubs WC() with a session that reports a logged-in customer id and an empty cart.
	 *
	 * Giving verify_v3()/verify_v2() a customer id lets check_cached_verification() skip
	 * its "no customer identifier" branch, which logs to the shared logger — keeping the
	 * shared logger's only possible call site, for these tests, the one under test.
	 */
	private function stub_wc_session_and_cart(): void {
		$session = Mockery::mock();
		$session->shouldReceive( 'get_customer_id' )->andReturn( 42 );

		$woocommerce          = Mockery::mock();
		$woocommerce->session = $session;
		$woocommerce->cart    = null;

		when( 'WC' )->justReturn( $woocommerce );
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

	/**
	 * Stubs the API request/response plumbing so a v3 verify call is rejected
	 * (score/success below threshold), independent of which logger receives
	 * the resulting rejection log entry.
	 */
	private function stub_rejected_v3_verification(): void {
		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_post' )->justReturn( array() );
		when( 'wp_remote_retrieve_body' )->justReturn( json_encode( array( 'success' => false, 'score' => 0.1 ) ) );
		when( 'apply_filters' )->returnArg( 2 );
		when( 'wp_send_json_error' )->justReturn( null );
		when( 'get_transient' )->justReturn( false );

		$this->stub_wc_session_and_cart();
	}

	/**
	 * GIVEN reCAPTCHA's "Log rejected attempts" setting is on
	 * WHEN a v3 reCAPTCHA verification is rejected
	 * THEN the rejection is written to the dedicated rejection logger
	 * AND the shared, plugin-wide logger (which becomes a NullLogger unless the separate
	 *     "Enable logging" setting is on) never receives any call
	 * AND the logged context carries the request data and cart contents, but not a
	 *     'source' key
	 * AND the token, version and legacy 'g-recaptcha-response' fields are stripped from
	 *     the logged request data.
	 */
	public function test_v3_rejection_logs_to_rejection_logger_with_sanitized_context_and_never_touches_shared_logger(): void {
		$this->stub_rejected_v3_verification();

		$settings_status = Mockery::mock( SettingsStatus::class );

		// A bare mock with no stubbed methods: any unexpected call (e.g. to the shared
		// logger) makes Mockery fail the test.
		$shared_logger = Mockery::mock( LoggerInterface::class );

		$rejection_counter = Mockery::mock( PersistentCounter::class );
		$rejection_counter->shouldReceive( 'increment' )->once();

		$captured_context = null;
		$rejection_logger = Mockery::mock( LoggerInterface::class );
		$rejection_logger->shouldReceive( 'debug' )->once()->andReturnUsing(
			function ( string $message, array $context ) use ( &$captured_context ): void {
				$captured_context = $context;
			}
		);

		$testee = $this->make_testee(
			$settings_status,
			array(),
			array( 'log_rejections' => 'yes' ),
			$shared_logger,
			$rejection_logger,
			$rejection_counter
		);

		$testee->intercept_paypal_ajax(
			array(
				'ppcp_recaptcha_token'   => 'test-token',
				'ppcp_recaptcha_version' => 'v3',
				'g-recaptcha-response'   => 'legacy-token',
				'order_id'               => 123,
			)
		);

		$this->assertArrayHasKey( 'request', $captured_context );
		$this->assertArrayHasKey( 'cart', $captured_context );
		$this->assertArrayNotHasKey( 'source', $captured_context );
		$this->assertArrayNotHasKey( 'ppcp_recaptcha_token', $captured_context['request'] );
		$this->assertArrayNotHasKey( 'ppcp_recaptcha_version', $captured_context['request'] );
		$this->assertArrayNotHasKey( 'g-recaptcha-response', $captured_context['request'] );
		$this->assertSame( 123, $captured_context['request']['order_id'] );
	}

	/**
	 * GIVEN reCAPTCHA's "Log rejected attempts" setting is off
	 * WHEN a v3 reCAPTCHA verification is rejected
	 * THEN nothing is written to the rejection logger
	 * AND the rejection counter still increments, since counting happens before the
	 *     logging setting is checked.
	 */
	public function test_v3_rejection_does_not_log_when_log_rejections_disabled_but_still_increments_counter(): void {
		$this->stub_rejected_v3_verification();

		$settings_status = Mockery::mock( SettingsStatus::class );

		$rejection_counter = Mockery::mock( PersistentCounter::class );
		$rejection_counter->shouldReceive( 'increment' )->once();

		$rejection_logger = Mockery::mock( LoggerInterface::class );
		$rejection_logger->shouldNotReceive( 'debug' );

		$testee = $this->make_testee(
			$settings_status,
			array(),
			array( 'log_rejections' => 'no' ),
			null,
			$rejection_logger,
			$rejection_counter
		);

		$testee->intercept_paypal_ajax(
			array(
				'ppcp_recaptcha_token'   => 'test-token',
				'ppcp_recaptcha_version' => 'v3',
			)
		);
	}

	/**
	 * GIVEN reCAPTCHA's "Log rejected attempts" setting is on
	 * WHEN a v2 (not v3) reCAPTCHA verification is rejected
	 * THEN nothing is logged and the rejection counter does not increment, since only
	 *      v3 rejections are logged.
	 */
	public function test_v2_rejection_never_invokes_rejection_logging(): void {
		$this->stub_rejected_v3_verification();

		$settings_status = Mockery::mock( SettingsStatus::class );

		$rejection_counter = Mockery::mock( PersistentCounter::class );
		$rejection_counter->shouldNotReceive( 'increment' );

		$rejection_logger = Mockery::mock( LoggerInterface::class );
		$rejection_logger->shouldNotReceive( 'debug' );

		$testee = $this->make_testee(
			$settings_status,
			array(),
			array( 'log_rejections' => 'yes' ),
			null,
			$rejection_logger,
			$rejection_counter
		);

		$testee->intercept_paypal_ajax(
			array(
				'ppcp_recaptcha_token'   => 'test-token',
				'ppcp_recaptcha_version' => 'v2',
			)
		);
	}
}
