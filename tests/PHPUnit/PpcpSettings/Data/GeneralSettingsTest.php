<?php
declare(strict_types=1);

namespace PHPUnit\PpcpSettings\Data;

use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Enum\InstallationPathEnum;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class GeneralSettingsTest extends TestCase
{
	public function setUp(): void
	{
		parent::setUp();
		when('sanitize_key')->returnArg();
	}

	// -----------------------------------------------------------------------
	// Factory helpers
	// -----------------------------------------------------------------------

	/**
	 * Instantiate GeneralSettings with a specific seller_type persisted in the DB.
	 *
	 * AbstractDataModel::__construct() calls get_option() once, so we set that
	 * expectation here and return an options array containing only the key under
	 * test.  All other settings fall back to their declared defaults.
	 *
	 * @param string $seller_type One of SellerTypeEnum::BUSINESS / PERSONAL / UNKNOWN.
	 */
	private function make_general_settings(string $seller_type): GeneralSettings
	{
		expect('get_option')
			->once()
			->with('woocommerce-ppcp-data-common', [])
			->andReturn(['seller_type' => $seller_type]);

		return new GeneralSettings('US', 'USD', false);
	}

	/**
	 * Instantiate GeneralSettings with a specific installation path and country.
	 *
	 * @param string $installation_path One of InstallationPathEnum constants.
	 * @param string $country           WooCommerce store country code.
	 */
	private function make_settings_with_path(string $installation_path, string $country = 'US'): GeneralSettings
	{
		expect('get_option')
			->once()
			->with('woocommerce-ppcp-data-common', [])
			->andReturn(['wc_installation_path' => $installation_path]);

		return new GeneralSettings($country, 'USD', false);
	}

	// -----------------------------------------------------------------------
	// Data provider
	// -----------------------------------------------------------------------

	/**
	 * @return array<string, array{seller_type: string, is_business: bool, is_casual: bool}>
	 */
	public function seller_type_provider(): array
	{
		return [
			'business seller: confirmed business account' => [
				'seller_type' => SellerTypeEnum::BUSINESS,
				'is_business' => true,
				'is_casual'   => false,
			],
			'personal seller: casual individual account' => [
				'seller_type' => SellerTypeEnum::PERSONAL,
				'is_business' => false,
				'is_casual'   => true,
			],
			'unknown seller type: treated as casual to prevent retry loop' => [
				'seller_type' => SellerTypeEnum::UNKNOWN,
				'is_business' => false,
				'is_casual'   => true,
			],
		];
	}

	// -----------------------------------------------------------------------
	// Tests
	// -----------------------------------------------------------------------

	/**
	 * GIVEN a merchant whose seller_type is stored in the database
	 * WHEN is_business_seller() and is_casual_seller() are queried
	 * THEN each method returns the value that matches the merchant's account type
	 * AND unknown seller types are treated as casual (not business) to avoid
	 *     triggering an unresolvable seller-type detection retry loop
	 *
	 * @dataProvider seller_type_provider
	 */
	public function testSellerTypeClassificationMatchesStoredValue(
		string $seller_type,
		bool $is_business,
		bool $is_casual
	): void {
		$settings = $this->make_general_settings($seller_type);

		$this->assertSame(
			$is_business,
			$settings->is_business_seller(),
			"is_business_seller() should return {$this->bool_label($is_business)} for seller_type '{$seller_type}'"
		);

		$this->assertSame(
			$is_casual,
			$settings->is_casual_seller(),
			"is_casual_seller() should return {$this->bool_label($is_casual)} for seller_type '{$seller_type}'"
		);
	}

	// -----------------------------------------------------------------------
	// Data providers
	// -----------------------------------------------------------------------

	/**
	 * @return array<string, array{installation_path: string, country: string, expected: bool}>
	 */
	public function own_brand_only_provider(): array
	{
		return [
			'core-profiler path + WCPay country → branded-only' => [
				'installation_path' => InstallationPathEnum::CORE_PROFILER,
				'country'           => 'US',
				'expected'          => true,
			],
			'payment-settings path + WCPay country → branded-only' => [
				'installation_path' => InstallationPathEnum::PAYMENT_SETTINGS,
				'country'           => 'US',
				'expected'          => true,
			],
			'direct path + WCPay country → not branded-only' => [
				'installation_path' => InstallationPathEnum::DIRECT,
				'country'           => 'US',
				'expected'          => false,
			],
			'core-profiler path + non-WCPay country → not branded-only' => [
				'installation_path' => InstallationPathEnum::CORE_PROFILER,
				'country'           => 'JP',
				'expected'          => false,
			],
		];
	}

	// -----------------------------------------------------------------------
	// Tests
	// -----------------------------------------------------------------------

	/**
	 * GIVEN a store installed via a specific path in a given country
	 * WHEN own_brand_only() is called
	 * THEN it returns true only for branded installation paths in WCPay-eligible countries.
	 *
	 * This guards the "Register Apple Domain" (and other ACDC/wallet) todos: capabilities
	 * for Apple Pay, Google Pay, and ACDC are set to false when own_brand_only() is true,
	 * so those todos never appear in branded-only mode.
	 *
	 * @dataProvider own_brand_only_provider
	 */
	public function testOwnBrandOnlyReflectsInstallationPathAndCountry(
		string $installation_path,
		string $country,
		bool $expected
	): void {
		$settings = $this->make_settings_with_path($installation_path, $country);

		$this->assertSame(
			$expected,
			$settings->own_brand_only(),
			"own_brand_only() should return {$this->bool_label($expected)} "
			. "for path '{$installation_path}' and country '{$country}'"
		);
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function bool_label(bool $value): string
	{
		return $value ? 'true' : 'false';
	}
}
