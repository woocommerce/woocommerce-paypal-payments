<?php
declare(strict_types=1);

namespace PHPUnit\PpcpSettings\Data;

use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class GeneralSettingsTest extends TestCase
{
	// -----------------------------------------------------------------------
	// Factory helper
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
	// Helpers
	// -----------------------------------------------------------------------

	private function bool_label(bool $value): string
	{
		return $value ? 'true' : 'false';
	}
}
