<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Merchant;

use Mockery;
use WooCommerce;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;
use stdClass;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadataProvider
 */
class MerchantMetadataProviderTest extends TestCase {

	private WooCommerce $wc;
	private GeneralSettings $general_settings;
	private MerchantMetadataProvider $testee;

	public function setUp(): void {
		parent::setUp();

		$this->wc               = Mockery::mock( WooCommerce::class );
		$this->general_settings = $this->createStub( GeneralSettings::class );
	}

	/**
	 * GIVEN a connected merchant with complete store information
	 * WHEN metadata is requested
	 * THEN all merchant and store metadata is populated correctly
	 */
	public function test_get_metadata_returns_complete_merchant_metadata_when_merchant_connected(): void {
		$this->setup_connected_merchant( 'CA' );
		$this->setup_store_metadata( 'Test Store', 'https://example.com', 'USD', 'US' );

		$metadata = $this->testee->get_metadata();

		$this->assertInstanceOf( MerchantMetadata::class, $metadata );
		$this->assertSame( 'Test Store', $metadata->store_name );
		$this->assertSame( 'https://example.com', $metadata->store_url );
		$this->assertSame( 'US', $metadata->store_country );
		$this->assertSame( 'USD', $metadata->currency );
		$this->assertSame( 'MERCHANT123', $metadata->paypal_merchant_id );
		$this->assertSame( 'https://example.com', $metadata->catalog_url );
		$this->assertSame( 'CA', $metadata->merchant_country );
	}

	/**
	 * GIVEN a merchant that is not connected to PayPal
	 * WHEN metadata is requested
	 * THEN store metadata is populated, but PayPal merchant ID is empty
	 */
	public function test_get_metadata_returns_empty_merchant_id_when_merchant_not_connected(): void {
		$this->setup_disconnected_merchant( 'US' );
		$this->setup_store_metadata( 'Disconnected Store', 'https://disconnected.com', 'EUR', 'DE' );

		$metadata = $this->testee->get_metadata();

		$this->assertSame( 'Disconnected Store', $metadata->store_name );
		$this->assertSame( '', $metadata->paypal_merchant_id );
		$this->assertSame( 'DE', $metadata->store_country );
		$this->assertSame( 'EUR', $metadata->currency );
		$this->assertSame( 'US', $metadata->merchant_country );
	}

	/**
	 * GIVEN a site URL with a trailing slash
	 * WHEN metadata is requested
	 * THEN the trailing slash is removed from store_url and catalog_url to create canonical URLs
	 * AND both URLs are identical
	 *
	 * @dataProvider trailing_slash_provider
	 */
	public function test_get_metadata_normalizes_urls_by_removing_trailing_slash(
		string $site_url,
		string $expected_url
	): void {
		$this->setup_connected_merchant( 'US' );
		$this->setup_store_metadata( 'Store', $site_url, 'USD', 'US' );

		$metadata = $this->testee->get_metadata();

		$this->assertSame( $expected_url, $metadata->store_url );
		$this->assertSame( $expected_url, $metadata->catalog_url );
		$this->assertSame( $metadata->store_url, $metadata->catalog_url, 'store_url and catalog_url must be identical' );
	}

	public function trailing_slash_provider(): array {
		return [
			'url with trailing slash'            => [
				'site_url'     => 'https://example.com/',
				'expected_url' => 'https://example.com',
			],
			'url without trailing slash'         => [
				'site_url'     => 'https://example.com',
				'expected_url' => 'https://example.com',
			],
			'url with multiple trailing slashes' => [
				'site_url'     => 'https://example.com///',
				'expected_url' => 'https://example.com',
			],
		];
	}

	/**
	 * GIVEN merchants from different countries with different store configurations
	 * WHEN metadata is requested
	 * THEN the correct country codes and currencies are populated
	 * AND merchant_country differs from store_country when PayPal account is in a different country
	 *
	 * @dataProvider merchant_configuration_provider
	 */
	public function test_get_metadata_handles_different_merchant_and_store_countries(
		string $merchant_country,
		string $store_country,
		string $currency
	): void {
		$this->setup_connected_merchant( $merchant_country );
		$this->setup_store_metadata( 'Global Store', 'https://store.example', $currency, $store_country );

		$metadata = $this->testee->get_metadata();

		$this->assertSame( $merchant_country, $metadata->merchant_country );
		$this->assertSame( $store_country, $metadata->store_country );
		$this->assertSame( $currency, $metadata->currency );
	}

	public function merchant_configuration_provider(): array {
		return [
			'US merchant with Canadian store' => [
				'merchant_country' => 'US',
				'store_country'    => 'CA',
				'currency'         => 'CAD',
			],
			'Canadian merchant with US store' => [
				'merchant_country' => 'CA',
				'store_country'    => 'US',
				'currency'         => 'USD',
			],
			'UK merchant with UK store'       => [
				'merchant_country' => 'GB',
				'store_country'    => 'GB',
				'currency'         => 'GBP',
			],
			'German merchant with EU store'   => [
				'merchant_country' => 'DE',
				'store_country'    => 'DE',
				'currency'         => 'EUR',
			],
		];
	}

	/**
	 * Sets up a connected merchant with the given country.
	 */
	private function setup_connected_merchant( string $merchant_country ): void {
		$merchant_connection = new MerchantConnectionDTO(
			true,
			'API_USER123',
			'API_KEY123',
			'MERCHANT123',
			'test@example.com',
			$merchant_country
		);

		$this->general_settings->method( 'get_merchant_data' )
			->willReturn( $merchant_connection );
		$this->general_settings->method( 'is_merchant_connected' )
			->willReturn( true );
	}

	/**
	 * Sets up a disconnected merchant with the given country.
	 */
	private function setup_disconnected_merchant( string $merchant_country ): void {
		$merchant_connection = new MerchantConnectionDTO(
			false,
			'',
			'',
			'',
			'',
			$merchant_country
		);

		$this->general_settings->method( 'get_merchant_data' )
			->willReturn( $merchant_connection );
		$this->general_settings->method( 'is_merchant_connected' )
			->willReturn( false );
	}

	/**
	 * Sets up WordPress and WooCommerce store metadata.
	 */
	private function setup_store_metadata(
		string $store_name,
		string $site_url,
		string $currency,
		string $store_country
	): void {
		$store_currency = Mockery::mock( StoreCurrencyValue::class );
		$store_currency->allows( 'value' )->andReturn( $currency );
		$this->testee = new MerchantMetadataProvider(
			$this->wc,
			$this->general_settings,
			$store_currency
		);

		when( 'get_bloginfo' )->justReturn( $store_name );
		when( 'get_site_url' )->justReturn( $site_url );
		when( 'untrailingslashit' )->alias(
			function ( $url ) {
				return rtrim( $url, '/' );
			}
		);
		when( 'rest_url' )->justReturn( $site_url . '/wp-json/wc/v3/agentic' );

		$wc_countries = Mockery::mock( 'WC_Countries' );
		$wc_countries->allows( 'get_base_country' )->andReturn( $store_country );

		$this->wc->countries = $wc_countries;
	}
}
