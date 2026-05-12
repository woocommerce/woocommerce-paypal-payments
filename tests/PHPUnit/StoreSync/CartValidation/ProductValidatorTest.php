<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use Mockery;
use WooCommerce\PayPalCommerce\StoreSync\Config\IngestionConfiguration;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ProductValidator
 */
class ProductValidatorTest extends ValidationTest {

	private ProductValidator $validator;

	/** @var \Mockery\MockInterface */
	private $configuration;

	public function setUp(): void {
		parent::setUp();

		$this->configuration = Mockery::mock( IngestionConfiguration::class );
		$this->validator     = new ProductValidator( $this->configuration );
	}

	/**
	 * GIVEN a cart containing one purchasable, valid product
	 * WHEN validate() is called
	 * THEN an empty array is returned (no issues)
	 */
	public function test_validate_valid_product_returns_no_issues(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'is_purchasable' )->andReturn( true );
		$product->allows( 'is_downloadable' )->andReturn( false );
		$product->allows( 'is_type' )->andReturn( true );
		$product->allows( 'get_status' )->andReturn( 'publish' );
		$product->allows( 'get_name' )->andReturn( 'Valid Product' );
		$product->allows( 'get_id' )->andReturn( 42 );

		$this->configuration
			->allows( 'get_valid_product_filters' )
			->andReturn(
				array(
					'downloadable' => false,
					'status'       => array( 'publish' ),
					'type'         => array( 'simple', 'variable', 'variation' ),
				)
			);

		$item = $this->make_store_item( 0, $product, true, 'USD', '42', 2 );

		$cart   = $this->create_cart( '42', 2, 'Valid Product' );
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item ) )
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * GIVEN a cart containing a product that is not purchasable
	 * WHEN validate() is called
	 * THEN a non-empty array is returned indicating the item is invalid
	 */
	public function test_validate_non_purchasable_product_returns_non_empty_result(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'is_purchasable' )->andReturn( false );
		$product->allows( 'get_name' )->andReturn( 'Unavailable Product' );
		$product->allows( 'get_id' )->andReturn( 99 );

		// Configuration must NOT be consulted when the product is not purchasable.
		$this->configuration->shouldNotReceive( 'get_valid_product_filters' );

		$item = $this->make_store_item( 0, $product, true, 'USD', '99', 1 );

		$cart   = $this->create_cart( '99', 1, 'Unavailable Product' );
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item ) )
		);

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * GIVEN a cart containing a downloadable product when downloads are disabled
	 * WHEN validate() is called
	 * THEN a non-empty array is returned indicating the item is invalid
	 */
	public function test_validate_downloadable_product_when_not_supported_returns_non_empty_result(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'is_purchasable' )->andReturn( true );
		$product->allows( 'is_downloadable' )->andReturn( true );
		$product->allows( 'get_name' )->andReturn( 'Digital Goods' );
		$product->allows( 'get_id' )->andReturn( 55 );

		$this->configuration
			->allows( 'get_valid_product_filters' )
			->andReturn(
				array(
					'downloadable' => false,
					'status'       => array( 'publish' ),
					'type'         => array( 'simple' ),
				)
			);

		$item = $this->make_store_item( 0, $product, true, 'USD', '55', 1 );

		$cart   = $this->create_cart( '55', 1, 'Digital Goods' );
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item ) )
		);

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * GIVEN a cart that already carries an INVENTORY_ISSUE validation issue
	 * WHEN validate() is called
	 * THEN null is returned immediately without inspecting any product
	 */
	public function test_validate_skips_when_inventory_issue_already_present(): void {
		$this->configuration->shouldNotReceive( 'get_valid_product_filters' );

		$pre_existing_issue =
			ValidationIssue::create_item_out_of_stock( 'Pre-existing inventory problem' );

		$validation = new StoreValidation();
		$validation->add( $pre_existing_issue );

		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $this->create_cart(), $validation, array() )
		);

		$this->assertNull( $result );
	}

	/**
	 * GIVEN a cart with multiple items where only one has an unsupported product type
	 * WHEN validate() is called
	 * THEN only the invalid item is in the returned array
	 */
	public function test_validate_returns_only_invalid_items_from_mixed_cart(): void {
		$valid_product = Mockery::mock( 'WC_Product' );
		$valid_product->allows( 'is_purchasable' )->andReturn( true );
		$valid_product->allows( 'is_downloadable' )->andReturn( false );
		$valid_product->allows( 'is_type' )->andReturn( true );
		$valid_product->allows( 'get_status' )->andReturn( 'publish' );
		$valid_product->allows( 'get_name' )->andReturn( 'Simple Widget' );
		$valid_product->allows( 'get_id' )->andReturn( 10 );

		$invalid_product = Mockery::mock( 'WC_Product' );
		$invalid_product->allows( 'is_purchasable' )->andReturn( true );
		$invalid_product->allows( 'is_downloadable' )->andReturn( false );
		$invalid_product->allows( 'is_type' )->andReturn( false ); // unsupported type
		$invalid_product->allows( 'get_status' )->andReturn( 'publish' );
		$invalid_product->allows( 'get_name' )->andReturn( 'Subscription Box' );
		$invalid_product->allows( 'get_id' )->andReturn( 20 );

		$this->configuration
			->allows( 'get_valid_product_filters' )
			->andReturn(
				array(
					'downloadable' => false,
					'status'       => array( 'publish' ),
					'type'         => array( 'simple', 'variable', 'variation' ),
				)
			);

		$valid_item   = $this->make_store_item( 0, $valid_product, true, 'USD', '10', 1 );
		$invalid_item = $this->make_store_item( 1, $invalid_product, true, 'USD', '20', 1 );

		$cart   = $this->create_cart();
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $valid_item, $invalid_item ) )
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
	}
}
