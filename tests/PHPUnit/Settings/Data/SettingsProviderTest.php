<?php

declare( strict_types=1 );

namespace PHPUnit\Settings\Data;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\Data\FastlaneSettings;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\PayLaterMessagingSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\Data\StylingSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class SettingsProviderTest extends TestCase {

	private const EXPECTED_VALUE_STRING = 'EXPECTED_VALUE';
	private const EXPECTED_VALUE_BOOL   = true;
	private const EXPECTED_VALUE_ARRAY  = array();
	private const EXPECTED_VALUE_INT    = 2;

	private GeneralSettings $general_settings;
	private OnboardingProfile $onboarding_profile;
	private PaymentSettings $payment_settings;
	private SettingsModel $settings_model;
	private StylingSettings $styling_settings;
	private FastlaneSettings $fastlane_settings;
	private PayLaterMessagingSettings $paylater_messaging_settings;

	public function setUp(): void {
		$this->general_settings            = Mockery::mock( GeneralSettings::class );
		$this->onboarding_profile          = Mockery::mock( OnboardingProfile::class );
		$this->payment_settings            = Mockery::mock( PaymentSettings::class );
		$this->settings_model              = Mockery::mock( SettingsModel::class );
		$this->styling_settings            = Mockery::mock( StylingSettings::class );
		$this->fastlane_settings           = Mockery::mock( FastlaneSettings::class );
		$this->paylater_messaging_settings = Mockery::mock( PayLaterMessagingSettings::class );

		$this->provider = new SettingsProvider(
			$this->general_settings,
			$this->onboarding_profile,
			$this->payment_settings,
			$this->settings_model,
			$this->styling_settings,
			$this->fastlane_settings,
			$this->paylater_messaging_settings
		);
	}

	/**
	 * Test SettingsProvider delegates the calls to the model.
	 *
	 * @dataProvider settings_method_provider
	 *
	 * @param string $provider_method The method name in the SettingsProvider class.
	 * @param string $model The model mapped in the provider.
	 * @param string $model_method The method name of the mocked model.
	 * @param mixed  $expected_value The expected return value of the mocked model method.
	 */
	public function test_settings_method_delegation(
		string $provider_method,
		string $model_method,
		$expected_value,
		string $model
	): void {
		// Access the mocked model (i.e $this->general_settings)
		$target_mock = $this->$model;

		//The model should receive the model method call and return the expected value.
		$target_mock->shouldReceive( $model_method )->andReturn( $expected_value );

		// Call the method in the provider class.
		$result = $this->provider->$provider_method();

		$this->assertSame( $expected_value, $result );
	}

	/**
	 * Data provider for SettingsProvider class.
	 */
	public function settings_method_provider(): array {
		return array_merge(
			$this->get_model_data( $this->get_general_settings_data(), 'general_settings' ),
			$this->get_model_data( $this->get_onboarding_profile_data(), 'onboarding_profile' ),
			$this->get_model_data( $this->get_payment_settings_data(), 'payment_settings' ),
			$this->get_model_data( $this->get_settings_model_data(), 'settings_model' ),
			$this->get_model_data( $this->get_styling_settings_data(), 'styling_settings' ),
			$this->get_model_data( $this->get_fastlane_settings_data(), 'fastlane_settings' ),
			$this->get_model_data( $this->get_paylater_messaging_settings_data(), 'paylater_messaging_settings' ),
		);
	}

	/**
	 * Attach a model into the test data.
	 *
	 * @param array  $data
	 * @param string $model
	 *
	 * @return array
	 */
	private function get_model_data( array $data, string $model ): array {
		return array_map(
			function ( array $method_data ) use ( $model ) {
				$method_data['model'] = $model;

				return $method_data;
			},
			$data
		);
	}

	/**
	 * Test data for the GeneralSettings model.
	 * @return array
	 * @see GeneralSettings
	 */
	private function get_general_settings_data(): array {
		return array(
			array(
				'provider_method' => 'use_sandbox',
				'model_method'    => 'get_sandbox',
				'expected_value'  => self::EXPECTED_VALUE_BOOL
			),
			array(
				'provider_method' => 'woo_settings',
				'model_method'    => 'get_woo_settings',
				'expected_value'  => self::EXPECTED_VALUE_ARRAY
			),
			array(
				'provider_method' => 'merchant_data',
				'model_method'    => 'get_merchant_data',
				'expected_value'  => new MerchantConnectionDTO( true, '', '', '' ),
			),
			array(
				'provider_method' => 'sandbox_merchant',
				'model_method'    => 'is_sandbox_merchant',
				'expected_value'  => self::EXPECTED_VALUE_BOOL
			),
			array(
				'provider_method' => 'merchant_connected',
				'model_method'    => 'is_merchant_connected',
				'expected_value'  => self::EXPECTED_VALUE_BOOL
			),
			array(
				'provider_method' => 'business_seller',
				'model_method'    => 'is_business_seller',
				'expected_value'  => self::EXPECTED_VALUE_BOOL
			),
			array(
				'provider_method' => 'casual_seller',
				'model_method'    => 'is_casual_seller',
				'expected_value'  => self::EXPECTED_VALUE_BOOL
			),
			array(
				'provider_method' => 'merchant_id',
				'model_method'    => 'get_merchant_id',
				'expected_value'  => self::EXPECTED_VALUE_STRING
			),
			array(
				'provider_method' => 'merchant_email',
				'model_method'    => 'get_merchant_email',
				'expected_value'  => self::EXPECTED_VALUE_STRING
			),
			array(
				'provider_method' => 'merchant_country',
				'model_method'    => 'get_merchant_country',
				'expected_value'  => self::EXPECTED_VALUE_STRING
			),
			array(
				'provider_method' => 'own_brand_only',
				'model_method'    => 'own_brand_only',
				'expected_value'  => self::EXPECTED_VALUE_BOOL
			),
			array(
				'provider_method' => 'installation_path',
				'model_method'    => 'get_installation_path',
				'expected_value'  => self::EXPECTED_VALUE_STRING
			),
		);
	}

	/**
	 * Test data for the OnboardingProfile model.
	 * @return array
	 * @see OnboardingProfile
	 */
	private function get_onboarding_profile_data(): array {
		return array(
			array(
				'provider_method' => 'onboarding_completed',
				'model_method'    => 'get_completed',
				'expected_value'  => self::EXPECTED_VALUE_BOOL
			),
			array(
				'provider_method' => 'onboarding_step',
				'model_method'    => 'get_step',
				'expected_value'  => self::EXPECTED_VALUE_INT
			),
			array(
				'provider_method' => 'accept_card_payments',
				'model_method'    => 'get_accept_card_payments',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'products',
				'model_method'    => 'get_products',
				'expected_value'  => self::EXPECTED_VALUE_ARRAY,
			),
			array(
				'provider_method' => 'flags',
				'model_method'    => 'get_flags',
				'expected_value'  => self::EXPECTED_VALUE_ARRAY,
			),
			array(
				'provider_method' => 'setup_done',
				'model_method'    => 'is_setup_done',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'gateways_synced',
				'model_method'    => 'is_gateways_synced',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'gateways_refreshed',
				'model_method'    => 'is_gateways_refreshed',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
		);
	}

	/**
	 * Test data for the PaymentSettings model.
	 * @return array
	 * @see PaymentSettings
	 */
	private function get_payment_settings_data(): array {
		return array(
			array(
				'provider_method' => 'show_paypal_logo',
				'model_method'    => 'get_paypal_show_logo',
				'expected_value'  => self::EXPECTED_VALUE_BOOL
			),
			array(
				'provider_method' => 'show_cardholder_name',
				'model_method'    => 'get_cardholder_name',
				'expected_value'  => self::EXPECTED_VALUE_BOOL
			),
			array(
				'provider_method' => 'show_fastlane_watermark',
				'model_method'    => 'get_fastlane_display_watermark',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'venmo_enabled',
				'model_method'    => 'get_venmo_enabled',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'paylater_enabled',
				'model_method'    => 'get_paylater_enabled',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
		);
	}

	/**
	 * Test data for the SettingsModel model.
	 * @return array
	 * @see SettingsModel
	 */
	private function get_settings_model_data(): array {
		return array(
			array(
				'provider_method' => 'invoice_prefix',
				'model_method'    => 'get_invoice_prefix',
				'expected_value'  => self::EXPECTED_VALUE_STRING
			),
			array(
				'provider_method' => 'brand_name',
				'model_method'    => 'get_brand_name',
				'expected_value'  => self::EXPECTED_VALUE_STRING
			),
			array(
				'provider_method' => 'soft_descriptor',
				'model_method'    => 'get_soft_descriptor',
				'expected_value'  => self::EXPECTED_VALUE_STRING,
			),
			array(
				'provider_method' => 'subtotal_adjustment',
				'model_method'    => 'get_subtotal_adjustment',
				'expected_value'  => self::EXPECTED_VALUE_STRING,
			),
			array(
				'provider_method' => 'landing_page',
				'model_method'    => 'get_landing_page',
				'expected_value'  => self::EXPECTED_VALUE_STRING,
			),
			array(
				'provider_method' => 'button_language',
				'model_method'    => 'get_button_language',
				'expected_value'  => self::EXPECTED_VALUE_STRING,
			),
			array(
				'provider_method' => 'three_d_secure',
				'model_method'    => 'get_three_d_secure',
				'expected_value'  => self::EXPECTED_VALUE_STRING,
			),
			array(
				'provider_method' => 'three_d_secure_enum',
				'model_method'    => 'get_three_d_secure_enum',
				'expected_value'  => self::EXPECTED_VALUE_STRING,
			),
			array(
				'provider_method' => 'authorize_only',
				'model_method'    => 'get_authorize_only',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'capture_virtual_orders',
				'model_method'    => 'get_capture_virtual_orders',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'save_paypal_and_venmo',
				'model_method'    => 'get_save_paypal_and_venmo',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'instant_payments_only',
				'model_method'    => 'get_instant_payments_only',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'enable_contact_module',
				'model_method'    => 'get_enable_contact_module',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'save_card_details',
				'model_method'    => 'get_save_card_details',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'enable_pay_now',
				'model_method'    => 'get_enable_pay_now',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'enable_logging',
				'model_method'    => 'get_enable_logging',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
			array(
				'provider_method' => 'disabled_cards',
				'model_method'    => 'get_disabled_cards',
				'expected_value'  => self::EXPECTED_VALUE_ARRAY,
			),
			array(
				'provider_method' => 'stay_updated',
				'model_method'    => 'get_stay_updated',
				'expected_value'  => self::EXPECTED_VALUE_BOOL,
			),
		);
	}

	/**
	 * Test data for the StylingSettings model.
	 * @return array
	 * @see StylingSettings
	 */
	private function get_styling_settings_data(): array {
		$styling_dto = new LocationStylingDTO();

		return array(
			array(
				'provider_method' => 'styling_cart',
				'model_method'    => 'get_cart',
				'expected_value'  => $styling_dto
			),
			array(
				'provider_method' => 'styling_classic_checkout',
				'model_method'    => 'get_classic_checkout',
				'expected_value'  => $styling_dto
			),
			array(
				'provider_method' => 'styling_express_checkout',
				'model_method'    => 'get_express_checkout',
				'expected_value'  => $styling_dto,
			),
			array(
				'provider_method' => 'styling_mini_cart',
				'model_method'    => 'get_mini_cart',
				'expected_value'  => $styling_dto,
			),
			array(
				'provider_method' => 'styling_product',
				'model_method'    => 'get_product',
				'expected_value'  => $styling_dto,
			),
		);
	}

	/**
	 * Test data for the FastlaneSettings model.
	 * @return array
	 * @see FastlaneSettings
	 */
	private function get_fastlane_settings_data(): array {
		return array(
			array(
				'provider_method' => 'fastlane_name_on_card',
				'model_method'    => 'get_name_on_card',
				'expected_value'  => self::EXPECTED_VALUE_STRING,
			),
			array(
				'provider_method' => 'fastlane_root_styles',
				'model_method'    => 'get_root_styles',
				'expected_value'  => self::EXPECTED_VALUE_ARRAY,
			),
			array(
				'provider_method' => 'fastlane_input_styles',
				'model_method'    => 'get_input_styles',
				'expected_value'  => self::EXPECTED_VALUE_ARRAY,
			),
		);
	}

	/**
	 * Test data for the PayLaterMessagingSettings model.
	 * @return array
	 * @see PayLaterMessagingSettings
	 */
	private function get_paylater_messaging_settings_data(): array {
		return array(
			array(
				'provider_method' => 'pay_later_messaging_locations',
				'model_method'    => 'get_messaging_locations',
				'expected_value'  => self::EXPECTED_VALUE_ARRAY,
			),
		);
	}

	/**
	 * @dataProvider capture_on_status_change_cases
	 */
	public function test_capture_on_status_change( bool $db_value, ?bool $filter_override, bool $expected ): void {
		$this->payment_settings
			->shouldReceive( 'get_capture_on_status_change' )
			->andReturn( $db_value );

		expect( 'apply_filters' )
			->once()
			->with( 'woocommerce_paypal_payments_capture_on_status_change', $db_value )
			->andReturn( $filter_override ?? $db_value );

		$result = $this->provider->capture_on_status_change();

		$this->assertEquals( $expected, $result );
	}

	public function capture_on_status_change_cases(): array {
		return [
			'default'              => [
				'db_value'        => true,
				'filter_override' => null,
				'expected'        => true,
			],
			'disable by migration' => [
				'db_value'        => false,
				'filter_override' => null,
				'expected'        => false,
			],
			'disable by filter'    => [
				'db_value'        => true,
				'filter_override' => false,
				'expected'        => false,
			],
			'enable by filter'     => [
				'db_value'        => false,
				'filter_override' => true,
				'expected'        => true,
			],
		];
	}
}
