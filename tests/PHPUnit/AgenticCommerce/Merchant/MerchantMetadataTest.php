<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Merchant;

use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers MerchantMetadata
 */
class MerchantMetadataTest extends TestCase {

	public function test_constructor_sets_all_properties(): void {
		$metadata = new MerchantMetadata(
			'Test Store',
			'https://example.com',
			'US',
			'USD',
			'MERCHANT123',
			'https://example.com/catalog.json'
		);

		$this->assertSame( 'Test Store', $metadata->store_name );
		$this->assertSame( 'https://example.com', $metadata->store_url );
		$this->assertSame( 'US', $metadata->country );
		$this->assertSame( 'USD', $metadata->currency );
		$this->assertSame( 'MERCHANT123', $metadata->paypal_merchant_id );
		$this->assertSame( 'https://example.com/catalog.json', $metadata->catalog_url );
	}

	public function test_properties_are_publicly_accessible(): void {
		$metadata = new MerchantMetadata(
			'Store Name',
			'https://store.example',
			'GB',
			'GBP',
			'MERCHANT456',
			'https://store.example/feed'
		);

		$this->assertSame( 'Store Name', $metadata->store_name );
		$this->assertSame( 'https://store.example', $metadata->store_url );
		$this->assertSame( 'GB', $metadata->country );
		$this->assertSame( 'GBP', $metadata->currency );
		$this->assertSame( 'MERCHANT456', $metadata->paypal_merchant_id );
		$this->assertSame( 'https://store.example/feed', $metadata->catalog_url );
	}
}
