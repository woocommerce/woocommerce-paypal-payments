<?php

declare( strict_types=1 );

namespace PHPUnit\Settings\Data;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\TestCase;

class SettingsProviderTest extends TestCase {

	private const EXPECTED_VALUE_STRING = 'EXPECTED_VALUE';
	private const EXPECTED_VALUE_BOOL = true;
	private const EXPECTED_VALUE_ARRAY = array();
	private const EXPECTED_VALUE_INT = 2;

	public function setUp(): void {
		// Mock Models
		$this->general_settings = Mockery::mock( GeneralSettings::class );
		$this->onboarding_profile = Mockery::mock( OnboardingProfile::class );

		$this->provider = new SettingsProvider(
			$this->general_settings,
			$this->onboarding_profile
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
		);
	}

	/**
	 * Attach a model into the test data.
	 *
	 * @param array $data
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
	 * @see GeneralSettings
	 * @return array
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
				'expected_value'  => new MerchantConnectionDTO(true, '','',''),
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
		);
	}

	/**
	 * Test data for the OnboardingProfile model.
	 * @see OnboardingProfile
	 * @return array
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
}
