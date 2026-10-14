<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use Mockery;
use WC_Product;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager
 */
class ProductManagerTest extends TestCase {

	/**
	 * @var ProductManager
	 */
	private $testee;

	public function setUp(): void {
		parent::setUp();

		when( 'wp_strip_all_tags' )->alias( 'strip_tags' );

		$this->testee = new ProductManager( Mockery::mock( StoreCurrencyValue::class ) );
	}

	private function create_product_with_description( string $description ): WC_Product {
		$product = Mockery::mock( WC_Product::class );
		$product->allows( 'get_description' )->andReturn( $description );

		return $product;
	}

	/**
	 * GIVEN a product whose description holds no visible text
	 * WHEN the description is prepared for ingestion
	 * THEN it is an empty string, so the product is treated as having no description
	 *
	 * @dataProvider invisible_description_provider
	 */
	public function test_get_product_description_returns_empty_string_when_description_has_no_visible_text( string $description ): void {
		$product = $this->create_product_with_description( $description );

		$this->assertSame( '', $this->testee->get_product_description( $product ) );
	}

	public function invisible_description_provider(): array {
		return array(
			'paragraph with only a non-breaking space' => array( '<p>&nbsp;</p>' ),
			'only an ideographic space (U+3000)'       => array( "\u{3000}" ),
			'only a byte order mark (U+FEFF)'          => array( "\u{FEFF}" ),
		);
	}

	/**
	 * GIVEN a product description and a list of fallback descriptions
	 * WHEN the description is prepared for ingestion
	 * THEN the first candidate with visible text is used
	 * AND candidates without visible text are skipped
	 * AND the result is empty when no candidate has visible text
	 *
	 * @dataProvider description_fallback_provider
	 */
	public function test_get_product_description_uses_first_candidate_with_visible_text( string $description, array $fallbacks, string $expected ): void {
		$product = $this->create_product_with_description( $description );

		$this->assertSame( $expected, $this->testee->get_product_description( $product, ...$fallbacks ) );
	}

	public function description_fallback_provider(): array {
		return array(
			'own description wins over fallbacks'      => array( 'Own text', array( 'Fallback text' ), 'Own text' ),
			'non-breaking space falls through'         => array( '&nbsp;', array( 'Fallback text' ), 'Fallback text' ),
			'empty paragraph falls through'            => array( '<p></p>', array( 'Fallback text' ), 'Fallback text' ),
			'first fallback with text wins'            => array( '', array( '', '<p>&nbsp;</p>', 'First text', 'Second text' ), 'First text' ),
			'markup in the winning fallback is removed' => array( '', array( '<p>Fallback <b>text</b></p>' ), 'Fallback text' ),
			'all candidates blank gives empty string'  => array( '&nbsp;', array( '<p></p>', '' ), '' ),
		);
	}

	/**
	 * GIVEN a product description with typographic characters
	 * WHEN the description is prepared for ingestion
	 * THEN the characters stay unchanged
	 */
	public function test_get_product_description_keeps_typographic_characters(): void {
		$description = 'Caf' . "\u{E9}" . ' ' . "\u{2014}" . ' ' . "\u{201C}" . 'fresh' . "\u{201D}";
		$product     = $this->create_product_with_description( $description );

		$this->assertSame( $description, $this->testee->get_product_description( $product ) );
	}

	/**
	 * GIVEN a filter that replaces the description with whitespace only
	 * WHEN the description is prepared for ingestion
	 * THEN it is an empty string
	 */
	public function test_get_product_description_returns_empty_string_when_filter_returns_only_whitespace(): void {
		$product = $this->create_product_with_description( 'Real description' );

		expectApplied( 'woocommerce_paypal_payments_store_sync_item_description' )
			->once()
			->andReturn( " \u{A0}\u{3000} \n" );

		$this->assertSame( '', $this->testee->get_product_description( $product ) );
	}
}
