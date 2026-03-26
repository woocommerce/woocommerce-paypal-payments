<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Merchant;

use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadata
 */
class MerchantMetadataTest extends TestCase {

	/**
	 * @dataProvider metadata_scenarios_provider
	 */
	public function test_constructor_sets_all_properties(
		string $store_name,
		string $store_url,
		string $api_base_url,
		string $store_country,
		string $currency,
		string $paypal_merchant_id,
		string $catalog_url,
		string $merchant_country
	): void {
		$metadata = new MerchantMetadata(
			$store_name,
			$store_url,
			$api_base_url,
			$store_country,
			$currency,
			$paypal_merchant_id,
			$catalog_url,
			$merchant_country
		);

		$this->assertSame( $store_name, $metadata->store_name );
		$this->assertSame( $store_url, $metadata->store_url );
		$this->assertSame( $api_base_url, $metadata->api_base_url );
		$this->assertSame( $store_country, $metadata->store_country );
		$this->assertSame( $currency, $metadata->currency );
		$this->assertSame( $paypal_merchant_id, $metadata->paypal_merchant_id );
		$this->assertSame( $catalog_url, $metadata->catalog_url );
		$this->assertSame( $merchant_country, $metadata->merchant_country );
	}

	public function metadata_scenarios_provider(): array {
		return array(
			'typical_us_store'   => array(
				'Test Store',
				'https://example.com',
				'https://example.com/wp-json/wc/store/v1',
				'US',
				'USD',
				'MERCHANT123',
				'https://example.com/catalog.json',
				'US',
			),
			'uk_store'           => array(
				'UK Shop',
				'https://shop.example.co.uk',
				'https://shop.example.co.uk/wp-json/wc/store/v1',
				'GB',
				'GBP',
				'MERCHANT456',
				'https://shop.example.co.uk/feed',
				'GB',
			),
			'minimal_data'       => array(
				'',
				'',
				'',
				'',
				'',
				'',
				'',
				'',
			),
			'single_char_values' => array(
				'S',
				'U',
				'A',
				'U',
				'D',
				'M',
				'C',
				'M',
			),
		);
	}
}
