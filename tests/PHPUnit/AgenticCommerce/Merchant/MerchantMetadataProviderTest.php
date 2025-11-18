<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Merchant;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadataProvider
 */
class MerchantMetadataProviderTest extends TestCase {

	private GeneralSettings $general_settings;
	private MerchantMetadataProvider $testee;

	public function setUp(): void {
		parent::setUp();

		$this->general_settings = Mockery::mock( GeneralSettings::class );
		$this->testee           = new MerchantMetadataProvider( $this->general_settings );
	}

	public function test_get_metadata_returns_complete_merchant_metadata(): void {
		$merchant_connection = new MerchantConnectionDTO(
			true,
			'API_USER123',
			'API_KEY123',
			'MERCHANT123',
			'test@example.com'
		);

		$this->general_settings->allows( 'get_merchant_data' )
			->andReturn( $merchant_connection );

		when( 'get_bloginfo' )->justReturn( 'Test Store' );
		when( 'get_site_url' )->justReturn( 'https://example.com' );
		when( 'untrailingslashit' )->returnArg();
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$wc_countries = Mockery::mock( 'overload:WC_Countries' );
		$wc_countries->allows( 'get_base_country' )->andReturn( 'US' );

		when( 'WC' )->alias(
			function () use ( $wc_countries ) {
				$wc            = new \stdClass();
				$wc->countries = $wc_countries;

				return $wc;
			}
		);

		$metadata = $this->testee->get_metadata();

		$this->assertInstanceOf( MerchantMetadata::class, $metadata );
		$this->assertSame( 'Test Store', $metadata->store_name );
		$this->assertSame( 'https://example.com', $metadata->store_url );
		$this->assertSame( 'US', $metadata->country );
		$this->assertSame( 'USD', $metadata->currency );
		$this->assertSame( 'MERCHANT123', $metadata->paypal_merchant_id );
		$this->assertSame( 'https://example.com', $metadata->catalog_url );
	}

	public function test_canonical_url_removes_trailing_slash(): void {
		$merchant_connection = new MerchantConnectionDTO(
			true,
			'API_USER123',
			'API_KEY123',
			'MERCHANT123',
			'test@example.com'
		);

		$this->general_settings->allows( 'get_merchant_data' )
			->andReturn( $merchant_connection );

		when( 'get_bloginfo' )->justReturn( 'Store' );
		when( 'get_site_url' )->justReturn( 'https://example.com/' );
		when( 'untrailingslashit' )->alias(
			function ( $url ) {
				return rtrim( $url, '/' );
			}
		);
		when( 'get_woocommerce_currency' )->justReturn( 'EUR' );

		$wc_countries = Mockery::mock( 'overload:WC_Countries' );
		$wc_countries->allows( 'get_base_country' )->andReturn( 'DE' );

		when( 'WC' )->alias(
			function () use ( $wc_countries ) {
				$wc            = new \stdClass();
				$wc->countries = $wc_countries;

				return $wc;
			}
		);

		$metadata = $this->testee->get_metadata();

		$this->assertSame( 'https://example.com', $metadata->store_url );
		$this->assertSame( 'https://example.com', $metadata->catalog_url );
	}

	public function test_store_url_and_catalog_url_are_identical(): void {
		$merchant_connection = new MerchantConnectionDTO(
			true,
			'API_USER123',
			'API_KEY123',
			'MERCHANT123',
			'test@example.com'
		);

		$this->general_settings->allows( 'get_merchant_data' )
			->andReturn( $merchant_connection );

		when( 'get_bloginfo' )->justReturn( 'My Store' );
		when( 'get_site_url' )->justReturn( 'https://mystore.com' );
		when( 'untrailingslashit' )->returnArg();
		when( 'get_woocommerce_currency' )->justReturn( 'GBP' );

		$wc_countries = Mockery::mock( 'overload:WC_Countries' );
		$wc_countries->allows( 'get_base_country' )->andReturn( 'GB' );

		when( 'WC' )->alias(
			function () use ( $wc_countries ) {
				$wc            = new \stdClass();
				$wc->countries = $wc_countries;

				return $wc;
			}
		);

		$metadata = $this->testee->get_metadata();

		$this->assertSame( $metadata->store_url, $metadata->catalog_url );
	}

	public function test_uses_wordpress_and_woocommerce_functions(): void {
		$merchant_connection = new MerchantConnectionDTO(
			true,
			'API_USER123',
			'API_KEY123',
			'MERCHANT123',
			'test@example.com'
		);

		$this->general_settings->allows( 'get_merchant_data' )
			->andReturn( $merchant_connection );

		when( 'get_bloginfo' )->justReturn( 'WP Store' );
		when( 'get_site_url' )->justReturn( 'https://wpstore.test' );
		when( 'untrailingslashit' )->returnArg();
		when( 'get_woocommerce_currency' )->justReturn( 'CAD' );

		$wc_countries = Mockery::mock( 'overload:WC_Countries' );
		$wc_countries->allows( 'get_base_country' )->andReturn( 'CA' );

		when( 'WC' )->alias(
			function () use ( $wc_countries ) {
				$wc            = new \stdClass();
				$wc->countries = $wc_countries;

				return $wc;
			}
		);

		$metadata = $this->testee->get_metadata();

		$this->assertSame( 'WP Store', $metadata->store_name );
		$this->assertSame( 'CAD', $metadata->currency );
		$this->assertSame( 'CA', $metadata->country );
	}
}
