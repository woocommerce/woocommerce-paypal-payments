<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductDTO
 */
class ProductDTOTest extends TestCase {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns a DTO constructed with all required fields and all optional fields null.
	 */
	private function make_minimal_dto(): ProductDTO {
		return new ProductDTO(
			'https://shop.example.com',
			'10',
			'10',
			'Widget',
			'https://shop.example.com/widget',
			'https://shop.example.com/img.jpg',
			'A widget description',
			'9.99 USD',
			'in stock'
		);
	}

	/**
	 * Returns a DTO with every argument supplied (required + all optional).
	 */
	private function make_full_dto(): ProductDTO {
		return new ProductDTO(
			'https://shop.example.com',
			'10',
			'5',
			'Widget - Red Large',
			'https://shop.example.com/widget-red-large',
			'https://shop.example.com/img-red.jpg',
			'A red large widget',
			'12.00 USD',
			'in stock',
			'SKU-001',
			'9.99 USD',
			'Clothing',
			'red',
			'large',
			'unisex'
		);
	}

	// -------------------------------------------------------------------------
	// Wire-format contract: required keys
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a DTO built with only required arguments (all optionals null)
	 * WHEN to_array() is called
	 * THEN all nine required keys are present with their exact values
	 *
	 * @dataProvider required_key_provider
	 */
	public function test_required_key_present_with_correct_value( string $key, string $expected ): void {
		$result = $this->make_minimal_dto()->to_array();

		$this->assertArrayHasKey( $key, $result );
		$this->assertSame( $expected, $result[ $key ] );
	}

	public function required_key_provider(): array {
		return array(
			'id carries the product id'              => array( 'id', '10' ),
			'item_group_id carries the group id'     => array( 'item_group_id', '10' ),
			'title carries the product name'         => array( 'title', 'Widget' ),
			'link carries the product permalink'     => array( 'link', 'https://shop.example.com/widget' ),
			'image_link carries the image url'       => array( 'image_link', 'https://shop.example.com/img.jpg' ),
			'description carries the description'    => array( 'description', 'A widget description' ),
			'price carries the formatted price'      => array( 'price', '9.99 USD' ),
			'availability carries the stock status'  => array( 'availability', 'in stock' ),
			'merchantStoreUrl carries the store url' => array( 'merchantStoreUrl', 'https://shop.example.com' ),
		);
	}

	// -------------------------------------------------------------------------
	// Wire-format contract: camelCase outlier
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a DTO
	 * WHEN to_array() is called
	 * THEN the store URL key is 'merchantStoreUrl' (camelCase), not 'merchant_store_url'
	 */
	public function test_merchant_store_url_key_is_camel_case_not_snake_case(): void {
		$result = $this->make_minimal_dto()->to_array();

		$this->assertArrayHasKey( 'merchantStoreUrl', $result );
		$this->assertArrayNotHasKey( 'merchant_store_url', $result );
	}

	// -------------------------------------------------------------------------
	// Wire-format contract: item_group_id carries whatever string was passed
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a DTO whose item_group_id was set to '42' (e.g. the product's own id for a simple product)
	 * WHEN to_array() is called
	 * THEN item_group_id in the output is '42'
	 */
	public function test_item_group_id_reflects_value_passed_to_constructor(): void {
		$dto    = new ProductDTO(
			'https://example.com', '42', '42', 'T', 'u', 'v', 'w', '1.00', 'in stock'
		);
		$result = $dto->to_array();

		$this->assertSame( '42', $result['item_group_id'] );
	}

	/**
	 * GIVEN a DTO whose item_group_id was set to '99' (e.g. a parent id for a variation)
	 * AND id was set to '200' (the variation's own id)
	 * WHEN to_array() is called
	 * THEN item_group_id is '99' and id is '200'
	 */
	public function test_item_group_id_can_differ_from_id(): void {
		$dto    = new ProductDTO(
			'https://example.com', '200', '99', 'T', 'u', 'v', 'w', '1.00', 'in stock'
		);
		$result = $dto->to_array();

		$this->assertSame( '200', $result['id'] );
		$this->assertSame( '99', $result['item_group_id'] );
	}

	// -------------------------------------------------------------------------
	// Wire-format contract: omit-when-empty for optional fields
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a DTO where all optional arguments are null
	 * WHEN to_array() is called
	 * THEN none of the six optional keys appear in the output
	 *
	 * @dataProvider optional_key_absent_provider
	 */
	public function test_optional_key_absent_when_null( string $key ): void {
		$result = $this->make_minimal_dto()->to_array();

		$this->assertArrayNotHasKey( $key, $result );
	}

	public function optional_key_absent_provider(): array {
		return array(
			'mpn absent when null'          => array( 'mpn' ),
			'sale_price absent when null'   => array( 'sale_price' ),
			'product_type absent when null' => array( 'product_type' ),
			'color absent when null'        => array( 'color' ),
			'size absent when null'         => array( 'size' ),
			'gender absent when null'       => array( 'gender' ),
		);
	}

	/**
	 * GIVEN a DTO where all optional arguments are provided with non-null values
	 * WHEN to_array() is called
	 * THEN all six optional keys appear in the output with the exact values passed
	 *
	 * @dataProvider optional_key_present_provider
	 */
	public function test_optional_key_present_when_non_null( string $key, string $expected ): void {
		$result = $this->make_full_dto()->to_array();

		$this->assertArrayHasKey( $key, $result );
		$this->assertSame( $expected, $result[ $key ] );
	}

	public function optional_key_present_provider(): array {
		return array(
			'mpn present with sku value'             => array( 'mpn', 'SKU-001' ),
			'sale_price present with formatted price' => array( 'sale_price', '9.99 USD' ),
			'product_type present with category'     => array( 'product_type', 'Clothing' ),
			'color present with color value'         => array( 'color', 'red' ),
			'size present with size value'           => array( 'size', 'large' ),
			'gender present with gender value'       => array( 'gender', 'unisex' ),
		);
	}

	// -------------------------------------------------------------------------
	// Minimal case: exactly the 9 required keys and no others
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a DTO with all optionals null
	 * WHEN to_array() is called
	 * THEN the output contains exactly 9 keys — no optional keys leaked in
	 */
	public function test_minimal_dto_contains_exactly_nine_keys(): void {
		$result = $this->make_minimal_dto()->to_array();

		$this->assertCount( 9, $result );
	}

	// -------------------------------------------------------------------------
	// Fully populated case: all 15 keys present
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a DTO where every constructor argument is supplied (required + all 6 optionals)
	 * WHEN to_array() is called
	 * THEN the output contains exactly 15 keys with the correct values
	 */
	public function test_fully_populated_dto_contains_all_fifteen_keys(): void {
		$result = $this->make_full_dto()->to_array();

		$this->assertCount( 15, $result );
		$this->assertSame( '10', $result['id'] );
		$this->assertSame( '5', $result['item_group_id'] );
		$this->assertSame( 'Widget - Red Large', $result['title'] );
		$this->assertSame( 'https://shop.example.com/widget-red-large', $result['link'] );
		$this->assertSame( 'https://shop.example.com/img-red.jpg', $result['image_link'] );
		$this->assertSame( 'A red large widget', $result['description'] );
		$this->assertSame( '12.00 USD', $result['price'] );
		$this->assertSame( 'in stock', $result['availability'] );
		$this->assertSame( 'https://shop.example.com', $result['merchantStoreUrl'] );
		$this->assertSame( 'SKU-001', $result['mpn'] );
		$this->assertSame( '9.99 USD', $result['sale_price'] );
		$this->assertSame( 'Clothing', $result['product_type'] );
		$this->assertSame( 'red', $result['color'] );
		$this->assertSame( 'large', $result['size'] );
		$this->assertSame( 'unisex', $result['gender'] );
	}
}
