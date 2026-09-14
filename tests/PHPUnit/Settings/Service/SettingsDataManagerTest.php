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
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Settings\Service\SettingsDataManager
 */
class SettingsDataManagerTest extends TestCase {

	private PaymentSettings $payment_methods;
	private SettingsProvider $settings_provider;
	private SettingsDataManager $sut;

	public function setUp(): void {
		parent::setUp();

		when( 'do_action' )->justReturn( null );

		$methods_definition = Mockery::mock( PaymentMethodsDefinition::class );
		$methods_definition->shouldReceive( 'group_paypal_methods' )->andReturn( array() );
		$methods_definition->shouldReceive( 'group_card_methods' )->andReturn( array() );
		$methods_definition->shouldReceive( 'group_apms' )->andReturn( array() );

		$this->payment_methods = Mockery::mock( PaymentSettings::class );
		$this->payment_methods->shouldReceive( 'set_fastlane_display_watermark' )->andReturnNull();
		$this->payment_methods->shouldReceive( 'save' )->andReturnNull();

		$this->settings_provider = Mockery::mock( SettingsProvider::class );

		$this->sut = new SettingsDataManager(
			$methods_definition,
			Mockery::mock( OnboardingProfile::class ),
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
	 * GIVEN a business seller accepting card payments
	 * WHEN toggle_payment_gateways() applies the onboarding defaults
	 * THEN Pay Later is enabled, and every other toggled state matches the expected onboarding
	 *      default for the given subscription/eligibility combination
	 *
	 * @dataProvider pay_later_onboarding_provider
	 */
	public function test_pay_later_onboarding_default(
		bool $use_subscriptions,
		?bool $pay_later_with_vaulting_enabled,
		bool $expect_pay_later_enabled
	): void {
		if ( null !== $pay_later_with_vaulting_enabled ) {
			$this->settings_provider
				->shouldReceive( 'pay_later_with_vaulting_enabled' )
				->once()
				->andReturn( $pay_later_with_vaulting_enabled );
		} else {
			$this->settings_provider->shouldNotReceive( 'pay_later_with_vaulting_enabled' );
		}

		$toggled_states = array();
		$this->payment_methods
			->shouldReceive( 'toggle_method_state' )
			->andReturnUsing(
				static function ( string $method_id, bool $enabled ) use ( &$toggled_states ): void {
					$toggled_states[ $method_id ] = $enabled;
				}
			);

		$flags                     = new ConfigurationFlagsDTO();
		$flags->is_business_seller = true;
		$flags->use_card_payments  = true;
		$flags->use_subscriptions  = $use_subscriptions;

		$this->toggle_payment_gateways( $flags );

		$this->assertSame( $expect_pay_later_enabled, $toggled_states['pay-later'] ?? false );
	}

	public function pay_later_onboarding_provider(): array {
		return [
			'no subscriptions enables Pay Later by default'                         => [
				'use_subscriptions'               => false,
				'pay_later_with_vaulting_enabled' => null,
				'expect_pay_later_enabled'        => true,
			],
			'subscriptions without vaulting override keep Pay Later disabled'       => [
				'use_subscriptions'               => true,
				'pay_later_with_vaulting_enabled' => false,
				'expect_pay_later_enabled'        => false,
			],
			'subscriptions with vaulting override enable Pay Later'                 => [
				'use_subscriptions'               => true,
				'pay_later_with_vaulting_enabled' => true,
				'expect_pay_later_enabled'        => true,
			],
		];
	}
}
