<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\TestCase;
use WC_Product;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use Mockery;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductsPayload
 */
class ProductsPayloadTest extends TestCase {

	private function make_product_manager_stub(): ProductManager {
		$currency = Mockery::mock( StoreCurrencyValue::class );
		$currency->allows( 'value' )->andReturn( 'USD' );

		return new ProductManager( $currency );
	}

	public function test_transform_simple_product(): void {
		$product_id = 123;
		$product    = Mockery::mock( WC_Product_Simple::class );

		// Mock product methods
		$product->shouldReceive( 'get_id' )->andReturn( $product_id );
		$product->shouldReceive( 'get_type' )->andReturn( 'simple' );
		$product->shouldReceive( 'is_type' )->with( 'variable' )->andReturn( false );
		$product->shouldReceive( 'get_name' )->andReturn( 'Test Product' );
		$product->shouldReceive( 'get_permalink' )->andReturn( 'https://example.com/product/test' );
		$product->shouldReceive( 'get_image_id' )->andReturn( 456 );
		$product->shouldReceive( 'get_description' )->andReturn( 'Full description' );
		$product->shouldReceive( 'get_short_description' )->andReturn( 'Short description' );
		$product->shouldReceive( 'get_price' )->andReturn( '29.99' );
		$product->shouldReceive( 'get_stock_status' )->andReturn( 'instock' );
		$product->shouldReceive( 'get_sku' )->andReturn( 'SKU-123' );
		$product->shouldReceive( 'get_sale_price' )->andReturn( '19.99' );

		// Mock WordPress functions
		when( 'wc_get_product' )->justReturn( $product );
		when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/image.jpg' );
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );
		when( 'wc_get_product_category_list' )->justReturn( 'Electronics, Gadgets' );
		when( 'wp_strip_all_tags' )->returnArg( 1 );


		$payload = new ProductsPayload(
			'https://example.com',
			array( $product_id ),
			$this->make_product_manager_stub()
		);
		$result  = $payload->get_array();

		$this->assertCount( 1, $result );
		$this->assertEquals( array(
			'id'               => '123',
			'title'            => 'Test Product',
			'link'             => 'https://example.com/product/test',
			'image_link'       => 'https://example.com/image.jpg',
			'description'      => 'Full description',
			'price'            => '29.99 USD',
			'availability'     => 'in stock',
			'merchantStoreUrl' => 'https://example.com',
			'mpn'              => 'SKU-123',
			'sale_price'       => '19.99 USD',
			'product_type'     => 'Electronics, Gadgets',
		), $result[0] );
	}

	public function test_transform_variable_product_with_variations(): void {
		$parent_id     = 100;
		$variation1_id = 101;
		$variation2_id = 102;

		// Mock parent variable product - must implement WC_Product interface
		$parent_product = Mockery::mock( WC_Product_Variable::class . ', ' . WC_Product::class );
		$parent_product->shouldReceive( 'get_type' )->andReturn( 'variable' );
		$parent_product->shouldReceive( 'is_type' )->with( 'variable' )->andReturn( true );
		$parent_product->shouldReceive( 'get_children' )->andReturn( array(
			$variation1_id,
			$variation2_id,
		) );
		$parent_product->shouldReceive( 'get_id' )->andReturn( $parent_id );
		$parent_product->shouldReceive( 'get_image_id' )->andReturn( 200 );
		$parent_product->shouldReceive( 'get_description' )->andReturn( 'Parent description' );

		// Mock variation 1
		$variation1 = Mockery::mock( WC_Product_Variation::class );
		$variation1->shouldReceive( 'is_purchasable' )->andReturn( true );
		$variation1->shouldReceive( 'get_id' )->andReturn( $variation1_id );
		$variation1->shouldReceive( 'get_name' )->andReturn( 'Test Product - Red, Large' );
		$variation1->shouldReceive( 'get_permalink' )
			->andReturn( 'https://example.com/product/test?attribute_color=red&attribute_size=large' );
		$variation1->shouldReceive( 'get_image_id' )->andReturn( 0 );
		$variation1->shouldReceive( 'get_parent_id' )->andReturn( $parent_id );
		$variation1->shouldReceive( 'get_description' )->andReturn( '' );
		$variation1->shouldReceive( 'get_short_description' )->andReturn( '' );
		$variation1->shouldReceive( 'get_price' )->andReturn( '35.00' );
		$variation1->shouldReceive( 'get_stock_status' )->andReturn( 'instock' );
		$variation1->shouldReceive( 'get_sku' )->andReturn( 'SKU-101' );
		$variation1->shouldReceive( 'get_sale_price' )->andReturn( '' );
		$variation1->shouldReceive( 'get_variation_attributes' )->andReturn( array(
			'attribute_color' => 'red',
			'attribute_size'  => 'large',
		) );

		// Mock variation 2
		$variation2 = Mockery::mock( WC_Product_Variation::class );
		$variation2->shouldReceive( 'is_purchasable' )->andReturn( true );
		$variation2->shouldReceive( 'get_id' )->andReturn( $variation2_id );
		$variation2->shouldReceive( 'get_name' )->andReturn( 'Test Product - Blue, Medium' );
		$variation2->shouldReceive( 'get_permalink' )
			->andReturn( 'https://example.com/product/test?attribute_color=blue&attribute_size=medium' );
		$variation2->shouldReceive( 'get_image_id' )->andReturn( 201 );
		$variation2->shouldReceive( 'get_parent_id' )->andReturn( $parent_id );
		$variation2->shouldReceive( 'get_description' )->andReturn( 'Variation description' );
		$variation2->shouldReceive( 'get_short_description' )->andReturn( '' );
		$variation2->shouldReceive( 'get_price' )->andReturn( '32.00' );
		$variation2->shouldReceive( 'get_stock_status' )->andReturn( 'outofstock' );
		$variation2->shouldReceive( 'get_sku' )->andReturn( 'SKU-102' );
		$variation2->shouldReceive( 'get_sale_price' )->andReturn( '28.00' );
		$variation2->shouldReceive( 'get_variation_attributes' )->andReturn( array(
			'attribute_color' => 'blue',
			'attribute_size'  => 'medium',
		) );

		// Mock WordPress functions with closure to capture variables
		when( 'wc_get_product' )->alias( function ( $id ) use ( $parent_id, $variation1_id, $variation2_id, $parent_product, $variation1, $variation2 ) {
			if ( $id === $parent_id ) {
				return $parent_product;
			} elseif ( $id === $variation1_id ) {
				return $variation1;
			} elseif ( $id === $variation2_id ) {
				return $variation2;
			}

			return false;
		} );

		when( 'wp_get_attachment_image_url' )->alias( function ( $id ) {
			if ( $id === 200 ) {
				return 'https://example.com/parent-image.jpg';
			} elseif ( $id === 201 ) {
				return 'https://example.com/variation2-image.jpg';
			}

			return false;
		} );

		when( 'get_woocommerce_currency' )->justReturn( 'USD' );
		when( 'wc_get_product_category_list' )->justReturn( 'Clothing' );
		when( 'wp_strip_all_tags' )->returnArg( 1 );


		$payload = new ProductsPayload(
			'https://example.com',
			array( $parent_id ),
			$this->make_product_manager_stub()
		);
		$result  = $payload->get_array();

		$this->assertCount( 2, $result );

		// Check variation 1
		$this->assertEquals( array(
			'id'               => '101',
			'item_group_id'    => '100',
			'title'            => 'Test Product - Red, Large',
			'link'             => 'https://example.com/product/test?attribute_color=red&attribute_size=large',
			'image_link'       => 'https://example.com/parent-image.jpg',
			'description'      => 'Parent description',
			'price'            => '35.00 USD',
			'availability'     => 'in stock',
			'merchantStoreUrl' => 'https://example.com',
			'color'            => 'red',
			'size'             => 'large',
			'mpn'              => 'SKU-101',
			'product_type'     => 'Clothing',
		), $result[0] );

		// Check variation 2
		$this->assertEquals( array(
			'id'               => '102',
			'item_group_id'    => '100',
			'title'            => 'Test Product - Blue, Medium',
			'link'             => 'https://example.com/product/test?attribute_color=blue&attribute_size=medium',
			'image_link'       => 'https://example.com/variation2-image.jpg',
			'description'      => 'Variation description',
			'price'            => '32.00 USD',
			'availability'     => 'out of stock',
			'merchantStoreUrl' => 'https://example.com',
			'color'            => 'blue',
			'size'             => 'medium',
			'mpn'              => 'SKU-102',
			'sale_price'       => '28.00 USD',
			'product_type'     => 'Clothing',
		), $result[1] );
	}

	public function test_skip_non_purchasable_variations(): void {
		$parent_id    = 100;
		$variation_id = 101;

		// Mock parent variable product - must implement WC_Product interface
		$parent_product = Mockery::mock( WC_Product_Variable::class . ', ' . WC_Product::class );
		$parent_product->shouldReceive( 'get_type' )->andReturn( 'variable' );
		$parent_product->shouldReceive( 'is_type' )->with( 'variable' )->andReturn( true );
		$parent_product->shouldReceive( 'get_children' )->andReturn( array( $variation_id ) );
		$parent_product->shouldReceive( 'get_id' )->andReturn( $parent_id ); // Add this line

		$variation = Mockery::mock( WC_Product_Variation::class );
		$variation->shouldReceive( 'is_purchasable' )->andReturn( false );

		when( 'wc_get_product' )->alias( function ( $id ) use ( $parent_product, $variation, $parent_id, $variation_id ) {
			if ( $id === $parent_id ) {
				return $parent_product;
			} elseif ( $id === $variation_id ) {
				return $variation;
			}

			return false;
		} );

		// Add the missing mock for wc_get_product_category_list
		when( 'wc_get_product_category_list' )->justReturn( '' );
		when( 'wp_strip_all_tags' )->justReturn( '' );

		$payload = new ProductsPayload(
			'https://example.com',
			array( $parent_id ),
			$this->make_product_manager_stub()
		);
		$result  = $payload->get_array();

		$this->assertCount( 0, $result );
	}

	public function test_handle_invalid_product_id(): void {
		when( 'wc_get_product' )->justReturn( false );

		$payload = new ProductsPayload( 'https://example.com',
			array( 999 ),
			$this->make_product_manager_stub()
		);
		$result  = $payload->get_array();

		$this->assertCount( 0, $result );
	}

	public function test_handle_empty_product_list(): void {
		$payload = new ProductsPayload(
			'https://example.com',
			array(),
			$this->make_product_manager_stub()
		);
		$result  = $payload->get_array();

		$this->assertIsArray( $result );
		$this->assertCount( 0, $result );
	}

	public function test_handle_missing_image(): void {
		$product_id = 123;
		$product    = Mockery::mock( WC_Product_Simple::class );

		$product->shouldReceive( 'get_id' )->andReturn( $product_id );
		$product->shouldReceive( 'get_type' )->andReturn( 'simple' );
		$product->shouldReceive( 'is_type' )->with( 'variable' )->andReturn( false );
		$product->shouldReceive( 'get_name' )->andReturn( 'Test Product' );
		$product->shouldReceive( 'get_permalink' )->andReturn( 'https://example.com/product/test' );
		$product->shouldReceive( 'get_image_id' )->andReturn( 0 );
		$product->shouldReceive( 'get_description' )->andReturn( 'Description' );
		$product->shouldReceive( 'get_short_description' )->andReturn( '' );
		$product->shouldReceive( 'get_price' )->andReturn( '10.00' );
		$product->shouldReceive( 'get_stock_status' )->andReturn( 'instock' );
		$product->shouldReceive( 'get_sku' )->andReturn( '' );
		$product->shouldReceive( 'get_sale_price' )->andReturn( '' );

		when( 'wc_get_product' )->justReturn( $product );
		when( 'wp_get_attachment_image_url' )->justReturn( false );
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );
		when( 'wc_get_product_category_list' )->justReturn( '' );
		when( 'wp_strip_all_tags' )->justReturn( '' );

		$payload = new ProductsPayload(
			'https://example.com',
			array( $product_id ),
			$this->make_product_manager_stub()
		);
		$result  = $payload->get_array();

		$this->assertCount( 1, $result );
		// Based on the implementation, image_link is always set (empty string if no image)
		$this->assertArrayHasKey( 'image_link', $result[0] );
		$this->assertEquals( '', $result[0]['image_link'] );
		$this->assertArrayNotHasKey( 'mpn', $result[0] );
		$this->assertArrayNotHasKey( 'sale_price', $result[0] );
		$this->assertArrayNotHasKey( 'product_type', $result[0] );
	}

	public function test_handle_product_with_no_price(): void {
		$product_id = 123;
		$product    = Mockery::mock( WC_Product_Simple::class );

		$product->shouldReceive( 'get_id' )->andReturn( $product_id );
		$product->shouldReceive( 'get_type' )->andReturn( 'simple' );
		$product->shouldReceive( 'is_type' )->with( 'variable' )->andReturn( false );
		$product->shouldReceive( 'get_name' )->andReturn( 'Test Product' );
		$product->shouldReceive( 'get_permalink' )->andReturn( 'https://example.com/product/test' );
		$product->shouldReceive( 'get_image_id' )->andReturn( 0 );
		$product->shouldReceive( 'get_description' )->andReturn( 'Description' );
		$product->shouldReceive( 'get_short_description' )->andReturn( '' );
		$product->shouldReceive( 'get_price' )->andReturn( '' );
		$product->shouldReceive( 'get_stock_status' )->andReturn( 'instock' );
		$product->shouldReceive( 'get_sku' )->andReturn( '' );
		$product->shouldReceive( 'get_sale_price' )->andReturn( '' );

		when( 'wc_get_product' )->justReturn( $product );
		when( 'wp_get_attachment_image_url' )->justReturn( false );
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );
		when( 'wc_get_product_category_list' )->justReturn( '' );
		when( 'wp_strip_all_tags' )->justReturn( '' );

		$payload = new ProductsPayload(
			'https://example.com',
			array( $product_id ),
			$this->make_product_manager_stub()
		);
		$result  = $payload->get_array();

		$this->assertCount( 1, $result );
		// Based on actual implementation, empty price returns empty string
		$this->assertEquals( '', $result[0]['price'] );
	}

	public function test_availability_mapping(): void {
		$test_cases = array(
			'instock'     => 'in stock',
			'outofstock'  => 'out of stock',
			'onbackorder' => 'backorder', // Corrected based on actual implementation
		);

		foreach ( $test_cases as $stock_status => $expected_availability ) {
			$product_id = 123;
			$product    = Mockery::mock( WC_Product_Simple::class );

			$product->shouldReceive( 'get_id' )->andReturn( $product_id );
			$product->shouldReceive( 'get_type' )->andReturn( 'simple' );
			$product->shouldReceive( 'is_type' )->with( 'variable' )->andReturn( false );
			$product->shouldReceive( 'get_name' )->andReturn( 'Test Product' );
			$product->shouldReceive( 'get_permalink' )
				->andReturn( 'https://example.com/product/test' );
			$product->shouldReceive( 'get_image_id' )->andReturn( 0 );
			$product->shouldReceive( 'get_description' )->andReturn( 'Description' );
			$product->shouldReceive( 'get_short_description' )->andReturn( '' );
			$product->shouldReceive( 'get_price' )->andReturn( '10.00' );
			$product->shouldReceive( 'get_stock_status' )->andReturn( $stock_status );
			$product->shouldReceive( 'get_sku' )->andReturn( '' );
			$product->shouldReceive( 'get_sale_price' )->andReturn( '' );

			when( 'wc_get_product' )->justReturn( $product );
			when( 'wp_get_attachment_image_url' )->justReturn( false );
			when( 'get_woocommerce_currency' )->justReturn( 'USD' );
			when( 'wc_get_product_category_list' )->justReturn( '' );
			when( 'wp_strip_all_tags' )->justReturn( '' );

			$payload = new ProductsPayload(
				'https://example.com',
				array( $product_id ),
				$this->make_product_manager_stub()
			);
			$result  = $payload->get_array();

			$this->assertEquals( $expected_availability, $result[0]['availability'] );
		}
	}

	public function test_product_with_all_optional_fields_missing(): void {
		$product_id = 123;
		$product    = Mockery::mock( WC_Product_Simple::class );

		$product->shouldReceive( 'get_id' )->andReturn( $product_id );
		$product->shouldReceive( 'get_type' )->andReturn( 'simple' );
		$product->shouldReceive( 'is_type' )->with( 'variable' )->andReturn( false );
		$product->shouldReceive( 'get_name' )->andReturn( 'Test Product' );
		$product->shouldReceive( 'get_permalink' )->andReturn( 'https://example.com/product/test' );
		$product->shouldReceive( 'get_image_id' )->andReturn( 0 );
		$product->shouldReceive( 'get_description' )->andReturn( 'Description' );
		$product->shouldReceive( 'get_short_description' )->andReturn( '' );
		$product->shouldReceive( 'get_price' )->andReturn( '10.00' );
		$product->shouldReceive( 'get_stock_status' )->andReturn( 'instock' );
		$product->shouldReceive( 'get_sku' )->andReturn( '' );
		$product->shouldReceive( 'get_sale_price' )->andReturn( '' );

		when( 'wc_get_product' )->justReturn( $product );
		when( 'wp_get_attachment_image_url' )->justReturn( false );
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );
		when( 'wc_get_product_category_list' )->justReturn( '' );
		when( 'wp_strip_all_tags' )->justReturn( '' );

		$payload = new ProductsPayload(
			'https://example.com',
			array( $product_id ),
			$this->make_product_manager_stub()
		);
		$result  = $payload->get_array();

		$this->assertCount( 1, $result );
		// Check required fields are present
		$this->assertArrayHasKey( 'id', $result[0] );
		$this->assertArrayHasKey( 'title', $result[0] );
		$this->assertArrayHasKey( 'link', $result[0] );
		$this->assertArrayHasKey( 'image_link', $result[0] );
		$this->assertArrayHasKey( 'description', $result[0] );
		$this->assertArrayHasKey( 'price', $result[0] );
		$this->assertArrayHasKey( 'availability', $result[0] );
		$this->assertArrayHasKey( 'merchantStoreUrl', $result[0] );

		// Check optional fields are not present
		$this->assertArrayNotHasKey( 'mpn', $result[0] );
		$this->assertArrayNotHasKey( 'sale_price', $result[0] );
		$this->assertArrayNotHasKey( 'product_type', $result[0] );
	}
}
