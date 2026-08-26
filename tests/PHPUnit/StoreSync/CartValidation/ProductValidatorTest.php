<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use Mockery;
use WooCommerce\PayPalCommerce\StoreSync\Enums\ResolutionAction;
use WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductFilter;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ProductValidator
 */
class ProductValidatorTest extends ValidationTest {

	private ProductValidator $validator;

	/** @var \Mockery\MockInterface */
	private $product_filter;

	public function setUp(): void {
		parent::setUp();

		$this->product_filter = Mockery::mock( ProductFilter::class );
		$this->product_filter->allows( 'criteria_violation' )->andReturn( null )->byDefault();
		$this->product_filter->allows( 'passes_exclusion_filter' )->andReturn( true )->byDefault();

		$this->validator = new ProductValidator( $this->product_filter );
	}

	/**
	 * GIVEN a cart containing one purchasable product that passes all filter criteria
	 * WHEN validate() is called
	 * THEN an empty array is returned (no issues)
	 */
	public function test_validate_valid_product_returns_no_issues(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'is_purchasable' )->andReturn( true );
		$product->allows( 'get_name' )->andReturn( 'Valid Product' );
		$product->allows( 'get_id' )->andReturn( 42 );

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
	 * THEN an invalid-product issue is returned
	 * AND the issue carries both a "remove item" and a "suggest alternative" resolution
	 */
	public function test_validate_non_purchasable_product_returns_invalid_product_issue(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'is_purchasable' )->andReturn( false );
		$product->allows( 'get_name' )->andReturn( 'Unavailable Product' );
		$product->allows( 'get_id' )->andReturn( 99 );

		$item = $this->make_store_item( 0, $product, true, 'USD', '99', 1 );

		$cart   = $this->create_cart( '99', 1, 'Unavailable Product' );
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item ) )
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		$data = $result[0]->to_array();
		$this->assertValidationIssue( $data, 'INVENTORY_ISSUE', 'INVALID_DATA', 'items[0]', "not available for purchase" );
		$this->assertResolutionOption( $data, ResolutionAction::REMOVE_ITEM );
		$this->assertResolutionOption( $data, ResolutionAction::SUGGEST_ALTERNATIVE );
	}

	/**
	 * GIVEN a cart containing a purchasable product that violates the ingestion criteria
	 * WHEN validate() is called
	 * THEN a non-empty array with an invalid-product issue is returned
	 * AND the developer message matches the specific criteria violation
	 * AND no resolution option is attached
	 *
	 * @dataProvider criteria_violation_provider
	 */
	public function test_validate_product_violating_criteria_returns_invalid_product_issue(
		string $violation,
		string $expected_message_substring
	): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'is_purchasable' )->andReturn( true );
		$product->allows( 'get_name' )->andReturn( 'Digital Goods' );
		$product->allows( 'get_id' )->andReturn( 55 );

		$this->product_filter->allows( 'criteria_violation' )->andReturn( $violation );

		$item = $this->make_store_item( 0, $product, true, 'USD', '55', 1 );

		$cart   = $this->create_cart( '55', 1, 'Digital Goods' );
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item ) )
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		$data = $result[0]->to_array();
		$this->assertValidationIssue( $data, 'INVENTORY_ISSUE', 'INVALID_DATA', 'items[0]', $expected_message_substring );
		$this->assertArrayNotHasKey( 'resolution_options', $data );
	}

	public function criteria_violation_provider(): array {
		return array(
			'downloadable product is rejected'         => array( 'downloadable', "Downloadable product '55' is not supported" ),
			'unsupported product type is rejected'     => array( 'type', "Product '55' is not supported (unsupported product type)" ),
			'unsupported product status is rejected'   => array( 'status', "Product '55' is not supported (product has an unsupported status)" ),
		);
	}

	/**
	 * GIVEN a purchasable product that passes the criteria check but is excluded by a
	 *       third-party exclusion filter
	 * WHEN validate() is called
	 * THEN an invalid-product issue with a "remove item" resolution is returned
	 * AND the product is marked as processed exactly once
	 */
	public function test_validate_excluded_product_returns_invalid_product_issue_and_marks_processed(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'is_purchasable' )->andReturn( true );
		$product->allows( 'get_name' )->andReturn( 'Excluded Product' );
		$product->allows( 'get_id' )->andReturn( 77 );

		$this->product_filter->allows( 'passes_exclusion_filter' )->andReturn( false );
		$this->product_filter->expects( 'mark_processed' )->once()->with( $product );

		$item = $this->make_store_item( 0, $product, true, 'USD', '77', 1 );

		$cart   = $this->create_cart( '77', 1, 'Excluded Product' );
		$result = $this->validator->validate(
			$this->wrap_in_store_cart( $cart, null, array( $item ) )
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );

		$data = $result[0]->to_array();
		$this->assertValidationIssue( $data, 'INVENTORY_ISSUE', 'INVALID_DATA', 'items[0]', "Product '77' is not supported" );
		$this->assertResolutionOption( $data, ResolutionAction::REMOVE_ITEM );
	}

	/**
	 * GIVEN a cart that already carries an INVENTORY_ISSUE validation issue
	 * WHEN validate() is called
	 * THEN null is returned immediately without inspecting any product
	 */
	public function test_validate_skips_when_inventory_issue_already_present(): void {
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
	 * GIVEN a cart with multiple items where only one violates the ingestion criteria
	 * WHEN validate() is called
	 * THEN only the invalid item is in the returned array
	 */
	public function test_validate_returns_only_invalid_items_from_mixed_cart(): void {
		$valid_product = Mockery::mock( 'WC_Product' );
		$valid_product->allows( 'is_purchasable' )->andReturn( true );
		$valid_product->allows( 'get_name' )->andReturn( 'Simple Widget' );
		$valid_product->allows( 'get_id' )->andReturn( 10 );

		$invalid_product = Mockery::mock( 'WC_Product' );
		$invalid_product->allows( 'is_purchasable' )->andReturn( true );
		$invalid_product->allows( 'get_name' )->andReturn( 'Subscription Box' );
		$invalid_product->allows( 'get_id' )->andReturn( 20 );

		$this->product_filter->allows( 'criteria_violation' )->with( $valid_product )->andReturn( null );
		$this->product_filter->allows( 'criteria_violation' )->with( $invalid_product )->andReturn( 'type' );

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
