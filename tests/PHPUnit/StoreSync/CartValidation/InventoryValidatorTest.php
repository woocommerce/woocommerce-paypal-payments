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
	 * GIVEN a cart item whose product is found and is in sufficient stock
	 * WHEN validate() is called
	 * THEN an empty array is returned (no issues)
	 */
	public function test_validate_in_stock_product_returns_no_issues(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'get_name' )->andReturn( 'Test Product' );

		$this->product_manager
			->shouldReceive( 'find_product' )
			->once()
			->andReturn( $product );

		// First call: general in-stock check → true.
		// Second call: quantity-specific in-stock check → true.
		$this->product_manager
			->shouldReceive( 'is_in_stock' )
			->twice()
			->andReturn( true );

		$cart   = $this->create_cart( '1', 2 );
		$result = $this->validator->validate( $this->wrap_in_store_cart( $cart ) );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * GIVEN a cart item whose product is found but completely out of stock
	 * WHEN validate() is called
	 * THEN exactly one ValidationIssue is returned
	 * AND the issue has code INVENTORY_ISSUE, type BUSINESS_RULE and targets items[0]
	 * AND the context array has one entry with specific_issue = 'ITEM_OUT_OF_STOCK'
	 * AND the context entry contains an item_id matching the cart item's item_id
	 */
	public function test_validate_out_of_stock_product_returns_issue_with_context(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'get_name' )->andReturn( 'Sold-Out Widget' );

		$this->product_manager
			->shouldReceive( 'find_product' )
			->once()
			->andReturn( $product );

		// First is_in_stock call (general) → false, triggers out-of-stock branch.
		$this->product_manager
			->shouldReceive( 'is_in_stock' )
			->once()
			->withArgs( array( $product ) )
			->andReturn( false );

		$cart   = $this->create_cart( 'sku-001', 1 );
		$result = $this->validator->validate( $this->wrap_in_store_cart( $cart ) );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertValidationIssue( $issue_data, 'INVENTORY_ISSUE', 'BUSINESS_RULE', 'items[0]' );

		$context = $this->assertIssueContext( $issue_data, 'ITEM_OUT_OF_STOCK' );
		$this->assertArrayHasKey( 'item_id', $context );
		$this->assertSame( 'sku-001', $context['item_id'] );
	}

	/**
	 * GIVEN a cart item whose product is found, is generally in stock, but the
	 *       requested quantity (5) exceeds available stock (2)
	 * WHEN validate() is called
	 * THEN exactly one ValidationIssue is returned
	 * AND the issue has code INVENTORY_ISSUE, type BUSINESS_RULE and targets items[0]
	 * AND the context array has one entry with specific_issue = 'INSUFFICIENT_INVENTORY'
	 * AND context[0]['available_quantity'] === 2
	 * AND context[0]['requested_quantity'] === 5
	 */
	public function test_validate_insufficient_quantity_returns_issue_with_context(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'get_name' )->andReturn( 'Limited Widget' );
		$product->shouldReceive( 'get_stock_quantity' )->andReturn( 2 );

		$this->product_manager
			->shouldReceive( 'find_product' )
			->once()
			->andReturn( $product );

		// First call: general in-stock check (no quantity arg) → true.
		$this->product_manager
			->shouldReceive( 'is_in_stock' )
			->once()
			->withArgs( array( $product ) )
			->andReturn( true );

		// Second call: quantity-specific check (quantity = 5) → false.
		$this->product_manager
			->shouldReceive( 'is_in_stock' )
			->once()
			->withArgs( array( $product, 5 ) )
			->andReturn( false );

		$cart   = $this->create_cart( 'sku-002', 5 );
		$result = $this->validator->validate( $this->wrap_in_store_cart( $cart ) );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertValidationIssue( $issue_data, 'INVENTORY_ISSUE', 'BUSINESS_RULE', 'items[0]' );

		$context = $this->assertIssueContext( $issue_data, 'INSUFFICIENT_INVENTORY' );
		$this->assertArrayHasKey( 'available_quantity', $context );
		$this->assertSame( 2, $context['available_quantity'] );
		$this->assertArrayHasKey( 'requested_quantity', $context );
		$this->assertSame( 5, $context['requested_quantity'] );
	}

	/**
	 * GIVEN a cart item whose product cannot be found in WooCommerce
	 * WHEN validate() is called
	 * THEN an empty array is returned — inventory validator defers unknown products
	 *      to the ProductValidator
	 */
	public function test_validate_product_not_found_returns_no_issue(): void {
		$this->product_manager
			->shouldReceive( 'find_product' )
			->once()
			->andReturn( null );

		$this->product_manager->shouldNotReceive( 'is_in_stock' );

		$cart   = $this->create_cart( 'ghost-sku', 1 );
		$result = $this->validator->validate( $this->wrap_in_store_cart( $cart ) );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * GIVEN a cart that already carries an INVENTORY_ISSUE validation issue
	 * WHEN validate() is called
	 * THEN null is returned immediately without inspecting any product
	 */
	public function test_validate_skips_when_inventory_issue_already_present(): void {
		$this->product_manager->shouldNotReceive( 'find_product' );
		$this->product_manager->shouldNotReceive( 'is_in_stock' );

		$pre_existing_issue =
			ValidationIssue::create_item_out_of_stock( 'Pre-existing inventory problem' );

		$validation = new StoreValidation();
		$validation->add( $pre_existing_issue );

		$result = $this->validator->validate( $this->wrap_in_store_cart( $this->create_cart(), $validation ) );

		$this->assertNull( $result );
	}
}
