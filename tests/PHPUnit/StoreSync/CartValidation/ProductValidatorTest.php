<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use Mockery;
use WooCommerce\PayPalCommerce\StoreSync\Config\IngestionConfiguration;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\CartValidation\ProductValidator
 */
class ProductValidatorTest extends ValidationTest {

	private ProductValidator $validator;

	/** @var \Mockery\MockInterface */
	private $product_manager;

	/** @var \Mockery\MockInterface */
	private $configuration;

	public function setUp(): void {
		parent::setUp();

		$this->product_manager = Mockery::mock( ProductManager::class );
		$this->configuration   = Mockery::mock( IngestionConfiguration::class );
		$this->validator       =
			new ProductValidator( $this->product_manager, $this->configuration );
	}

	/**
	 * GIVEN a cart containing one item whose product_id does not exist in the WooCommerce catalog
	 * WHEN validate() is called
	 * THEN exactly one ValidationIssue is returned
	 * AND the issue has code INVENTORY_ISSUE, type INVALID_DATA and targets items[0]
	 * AND the context array contains one entry with specific_issue = 'ITEM_NOT_FOUND'
	 */
	public function test_validate_product_not_found_returns_issue_with_item_not_found_context(): void {
		$this->product_manager
			->shouldReceive( 'find_product' )
			->once()
			->andReturn( null );

		// Configuration must NOT be consulted when the product is not found.
		$this->configuration
			->shouldNotReceive( 'get_valid_product_filters' );

		$cart = $this->create_cart( 'sku-99', 1, 'Ghost Product' );

		$result = $this->validator->validate( $cart );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( ValidationIssue::class, $result[0] );

		$issue_data = $result[0]->to_array();
		$this->assertValidationIssue( $issue_data, 'INVENTORY_ISSUE', 'INVALID_DATA', 'items[0]' );

		$this->assertIssueContext( $issue_data, 'ITEM_NOT_FOUND' );
	}

	/**
	 * GIVEN a cart containing one item whose product is found, purchasable, not downloadable,
	 *       has a supported type and a valid status
	 * WHEN validate() is called
	 * THEN an empty array is returned (no issues)
	 */
	public function test_validate_in_stock_valid_product_returns_no_issues(): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->shouldReceive( 'is_purchasable' )->andReturn( true );
		$product->shouldReceive( 'is_downloadable' )->andReturn( false );
		$product->shouldReceive( 'is_type' )->andReturn( true );
		$product->shouldReceive( 'get_status' )->andReturn( 'publish' );

		$this->product_manager
			->shouldReceive( 'find_product' )
			->once()
			->andReturn( $product );

		$this->configuration
			->shouldReceive( 'get_valid_product_filters' )
			->once()
			->andReturn(
				array(
					'downloadable' => false,
					'status'       => array( 'publish' ),
					'type'         => array( 'simple', 'variable', 'variation' ),
				)
			);

		$cart = $this->create_cart( '42', 2, 'Valid Product' );

		$result = $this->validator->validate( $cart );

		// An empty issues array is a valid "no problems found" result.
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
		$this->configuration->shouldNotReceive( 'get_valid_product_filters' );

		$pre_existing_issue =
			ValidationIssue::create_item_out_of_stock( 'Pre-existing inventory problem' );

		$cart = $this->create_cart()->with_validation_issues( $pre_existing_issue );

		$result = $this->validator->validate( $cart );

		$this->assertNull( $result );
	}
}
