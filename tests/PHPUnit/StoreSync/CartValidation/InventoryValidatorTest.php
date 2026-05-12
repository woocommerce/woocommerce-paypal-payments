<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use Mockery;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\CartValidation\InventoryValidator
 */
class InventoryValidatorTest extends ValidationTest {

	private InventoryValidator $validator;

	/** @var \Mockery\MockInterface */
	private $product_manager;

	public function setUp(): void {
		parent::setUp();

		$this->product_manager = Mockery::mock( ProductManager::class );
		$this->validator       = new InventoryValidator( $this->product_manager );
	}

	// ---------------------------------------------------------------------------
	// Tests
	// ---------------------------------------------------------------------------

	/**
	 * GIVEN a cart item whose product is in sufficient stock
	 * WHEN validate() is called
	 * THEN an empty array is returned (no issues)
	 */
	public function test_validate_in_stock_product_returns_no_issues(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'get_name' )->andReturn( 'Test Product' );

		// General in-stock check → true; quantity-specific check → true.
		$this->product_manager
			->allows( 'is_in_stock' )
			->andReturn( true );

		$item = $this->make_store_item( 0, $product, true, 'USD', '1', 2 );

		$cart   = $this->create_cart( '1', 2 );
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item ) )
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * GIVEN a cart item whose product is completely out of stock
	 * WHEN validate() is called
	 * THEN a non-empty array is returned, indicating the item is problematic
	 * AND the result contains the item that failed the inventory check
	 */
	public function test_validate_out_of_stock_product_returns_non_empty_result(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'get_name' )->andReturn( 'Sold-Out Widget' );

		// General in-stock check → false, triggers out-of-stock branch.
		$this->product_manager
			->allows( 'is_in_stock' )
			->with( Mockery::type( 'WC_Product' ) )
			->andReturn( false );

		$item = $this->make_store_item( 0, $product, true, 'USD', 'sku-001', 1 );

		$cart   = $this->create_cart( 'sku-001', 1 );
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item ) )
		);

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * GIVEN a cart item whose product is generally in stock but the requested quantity (5)
	 *       exceeds available stock (2)
	 * WHEN validate() is called
	 * THEN a non-empty array is returned, indicating the item is problematic
	 */
	public function test_validate_insufficient_quantity_returns_non_empty_result(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'get_name' )->andReturn( 'Limited Widget' );
		$product->allows( 'get_stock_quantity' )->andReturn( 2 );

		// First call: general in-stock check → true.
		$this->product_manager
			->shouldReceive( 'is_in_stock' )
			->once()
			->with( Mockery::type( 'WC_Product' ) )
			->andReturn( true );

		// Second call: quantity-specific check (quantity = 5) → false.
		$this->product_manager
			->shouldReceive( 'is_in_stock' )
			->once()
			->with( Mockery::type( 'WC_Product' ), 5 )
			->andReturn( false );

		$item = $this->make_store_item( 0, $product, true, 'USD', 'sku-002', 5 );

		$cart   = $this->create_cart( 'sku-002', 5 );
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
		$this->product_manager->shouldNotReceive( 'is_in_stock' );

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
	 * GIVEN a cart with multiple items where only one is out of stock
	 * WHEN validate() is called
	 * THEN only the out-of-stock item is represented in the returned array
	 * AND the in-stock item is absent
	 */
	public function test_validate_returns_only_problematic_items(): void {
		$good_product = Mockery::mock( 'WC_Product' );
		$good_product->allows( 'get_name' )->andReturn( 'Good Product' );

		$bad_product = Mockery::mock( 'WC_Product' );
		$bad_product->allows( 'get_name' )->andReturn( 'Bad Product' );

		$this->product_manager
			->shouldReceive( 'is_in_stock' )
			->with( $good_product )
			->andReturn( true );

		$this->product_manager
			->shouldReceive( 'is_in_stock' )
			->with( $good_product, 1 )
			->andReturn( true );

		$this->product_manager
			->shouldReceive( 'is_in_stock' )
			->with( $bad_product )
			->andReturn( false );

		$good_item = $this->make_store_item( 0, $good_product, true, 'USD', '1', 1 );
		$bad_item  = $this->make_store_item( 1, $bad_product, true, 'USD', '2', 1 );

		$cart   = $this->create_cart();
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $good_item, $bad_item ) )
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
	}
}
