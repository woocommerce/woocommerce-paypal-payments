<?php
declare( strict_types=1 );

namespace PHPUnit\Settings\Service;

use Mockery;
use ReflectionMethod;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDefinition;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\Data\StylingSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\ConfigurationFlagsDTO;
use WooCommerce\PayPalCommerce\Settings\Service\SettingsDataManager;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Settings\Service\SettingsDataManager
 */
class SettingsDataManagerTest extends TestCase {

	private PaymentSettings $payment_methods;
	private SettingsProvider $settings_provider;
	private OnboardingProfile $onboarding_profile;
	private SettingsDataManager $sut;

	/**
	 * Tracks whether payment_methods::save() was invoked, since the default
	 * stub below accepts every call regardless of test.
	 */
	private bool $payment_methods_saved = false;

	public function setUp(): void {
		parent::setUp();

		when( 'do_action' )->justReturn( null );

		$methods_definition = Mockery::mock( PaymentMethodsDefinition::class );
		// Includes 'pay-later' so the skip in toggle_payment_gateways()'s disable loop is exercised.
		$methods_definition->shouldReceive( 'group_paypal_methods' )->andReturn(
			array(
				array( 'id' => PayPalGateway::ID ),
				array( 'id' => 'venmo' ),
				array( 'id' => 'pay-later' ),
			)
		);
		$methods_definition->shouldReceive( 'group_card_methods' )->andReturn( array() );
		$methods_definition->shouldReceive( 'group_apms' )->andReturn( array() );

		$this->payment_methods = Mockery::mock( PaymentSettings::class );
		$this->payment_methods->shouldReceive( 'set_fastlane_display_watermark' )->andReturnNull();
		$this->payment_methods->shouldReceive( 'save' )->andReturnUsing(
			function (): void {
				$this->payment_methods_saved = true;
			}
		);

		$this->settings_provider = Mockery::mock( SettingsProvider::class );

		$this->onboarding_profile = Mockery::mock( OnboardingProfile::class );

		$this->sut = new SettingsDataManager(
			$methods_definition,
			$this->onboarding_profile,
			Mockery::mock( GeneralSettings::class ),
			Mockery::mock( SettingsModel::class ),
			Mockery::mock( StylingSettings::class ),
			$this->payment_methods,
			array(),
			$this->settings_provider
		);
	}

	/**
	 * Invokes the protected toggle_payment_gateways() method.
	 *
	 * The method is protected because it is an internal step of the onboarding
	 * defaults flow; reflection lets the test drive it directly with specific
	 * configuration flags without going through the whole onboarding process.
	 */
	private function toggle_payment_gateways( ConfigurationFlagsDTO $flags ): void {
		$method = new ReflectionMethod( SettingsDataManager::class, 'toggle_payment_gateways' );
		$method->setAccessible( true );

		$method->invoke( $this->sut, $flags );
	}

	/**
	 * Invokes the protected apply_payment_methods() method.
	 *
	 * The method is protected because it is an internal step of the onboarding
	 * defaults flow; reflection lets the test drive it directly without going
	 * through apply_configuration(), which would also touch styling and
	 * pay later messaging.
	 */
	private function apply_payment_methods( ConfigurationFlagsDTO $flags ): void {
		$method = new ReflectionMethod( SettingsDataManager::class, 'apply_payment_methods' );
		$method->setAccessible( true );

		$method->invoke( $this->sut, $flags );
	}

	/**
	 * GIVEN Pay Later is not disabled by vaulting
	 * WHEN toggle_payment_gateways() runs on connect or reconnect
	 * THEN Pay Later is never toggled, preserving the merchant's own choice from apply_payment_methods()
	 *
	 * @dataProvider gateway_sync_flag_provider
	 */
	public function test_pay_later_untouched_when_not_disabled_by_vaulting(
		bool $is_business_seller,
		bool $use_card_payments,
		bool $use_subscriptions
	): void {
		$this->settings_provider
			->shouldReceive( 'pay_later_disabled_by_vaulting' )
			->andReturn( false );

		$toggled_states = array();
		$this->payment_methods
			->shouldReceive( 'toggle_method_state' )
			->andReturnUsing(
				static function ( string $method_id, bool $enabled ) use ( &$toggled_states ): void {
					$toggled_states[ $method_id ] = $enabled;
				}
			);

		$flags                     = new ConfigurationFlagsDTO();
		$flags->is_business_seller = $is_business_seller;
		$flags->use_card_payments  = $use_card_payments;
		$flags->use_subscriptions  = $use_subscriptions;

		$this->toggle_payment_gateways( $flags );

		$this->assertArrayNotHasKey( 'pay-later', $toggled_states );
	}

	public function gateway_sync_flag_provider(): array {
		return [
			'business seller, cards, subscriptions'       => [ true, true, true ],
			'business seller, cards, no subscriptions'    => [ true, true, false ],
			'business seller, no cards, subscriptions'    => [ true, false, true ],
			'business seller, no cards, no subscriptions' => [ true, false, false ],
			'casual seller, cards, subscriptions'         => [ false, true, true ],
			'casual seller, cards, no subscriptions'      => [ false, true, false ],
			'casual seller, no cards, subscriptions'      => [ false, false, true ],
			'casual seller, no cards, no subscriptions'   => [ false, false, false ],
		];
	}

	/**
	 * GIVEN Pay Later is disabled by vaulting
	 * WHEN toggle_payment_gateways() runs on connect or reconnect
	 * THEN Pay Later is turned off
	 *
	 * @dataProvider seller_type_provider
	 */
	public function test_pay_later_disabled_when_disabled_by_vaulting( bool $is_business_seller ): void {
		$this->settings_provider
			->shouldReceive( 'pay_later_disabled_by_vaulting' )
			->andReturn( true );

		$toggled_states = array();
		$this->payment_methods
			->shouldReceive( 'toggle_method_state' )
			->andReturnUsing(
				static function ( string $method_id, bool $enabled ) use ( &$toggled_states ): void {
					$toggled_states[ $method_id ] = $enabled;
				}
			);

		$flags                     = new ConfigurationFlagsDTO();
		$flags->is_business_seller = $is_business_seller;

		$this->toggle_payment_gateways( $flags );

		$this->assertFalse( $toggled_states['pay-later'] ?? true );
	}

	public function seller_type_provider(): array {
		return [
			'business seller' => [ true ],
			'casual seller'   => [ false ],
		];
	}

	/**
	 * GIVEN a new merchant completes onboarding
	 * WHEN apply_payment_methods() applies the "Payment Methods" tab defaults
	 * THEN Pay Later is enabled and the payment methods are persisted
	 */
	public function test_apply_payment_methods_enables_pay_later(): void {
		$toggled_states = array();
		$this->payment_methods
			->shouldReceive( 'toggle_method_state' )
			->andReturnUsing(
				static function ( string $method_id, bool $enabled ) use ( &$toggled_states ): void {
					$toggled_states[ $method_id ] = $enabled;
				}
			);

		$this->apply_payment_methods( new ConfigurationFlagsDTO() );

		$this->assertTrue( $toggled_states['pay-later'] ?? false );
		$this->assertTrue( $this->payment_methods_saved );
	}

	/**
	 * GIVEN a merchant that already completed onboarding reconnects
	 * WHEN set_defaults_for_new_merchant() runs
	 * THEN no payment method state is touched, keeping the merchant's own Pay Later choice
	 */
	public function test_set_defaults_for_new_merchant_keeps_pay_later_choice_on_reconnect(): void {
		$this->onboarding_profile->shouldReceive( 'is_setup_done' )->andReturn( true );

		$this->payment_methods->shouldNotReceive( 'toggle_method_state' );

		$this->sut->set_defaults_for_new_merchant( new ConfigurationFlagsDTO() );

		$this->addToAssertionCount( 1 );
	}
}
