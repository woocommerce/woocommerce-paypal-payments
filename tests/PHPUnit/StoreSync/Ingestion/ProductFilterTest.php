<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use Automattic\WooCommerce\Enums\ProductStatus;
use Mockery;
use Psr\Log\LoggerInterface;
use WC_Product;
use WooCommerce\PayPalCommerce\TestCase;

use function Brain\Monkey\Actions\expectDone;
use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductFilter
 */
class ProductFilterTest extends TestCase {

	/**
	 * @var LoggerInterface|Mockery\MockInterface
	 */
	private $logger;

	/**
	 * @var ProductFilter
	 */
	private $product_filter;

	public function setUp(): void {
		parent::setUp();

		$this->logger = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		$this->product_filter = new ProductFilter( $this->logger );
	}

	/**
	 * GIVEN the ProductFilter
	 * WHEN query_filters() is called
	 * THEN it returns the coarse eligibility criteria for wc_get_products()
	 */
	public function test_query_filters_returns_coarse_eligibility_criteria(): void {
		$result = $this->product_filter->query_filters();

		$this->assertSame(
			array(
				'status'       => ProductStatus::PUBLISH,
				'type'         => ProductFilter::SUPPORTED_PRODUCT_TYPES,
				'downloadable' => false,
			),
			$result
		);
	}

	/**
	 * GIVEN a product in a particular state
	 * WHEN criteria_violation() is called
	 * THEN the first violated rule is returned, or null when all criteria are met
	 *
	 * @dataProvider criteria_violation_provider
	 */
	public function test_criteria_violation(
		bool $is_downloadable,
		bool $is_supported_type,
		string $status,
		?string $expected
	): void {
		$product = Mockery::mock( 'WC_Product' );
		$product->allows( 'is_downloadable' )->andReturn( $is_downloadable );
		$product->allows( 'is_type' )->with( ProductFilter::SUPPORTED_PRODUCT_TYPES )->andReturn( $is_supported_type );
		$product->allows( 'get_status' )->andReturn( $status );

		$result = $this->product_filter->criteria_violation( $product );

		$this->assertSame( $expected, $result );
	}

	public function criteria_violation_provider(): array {
		return array(
			'downloadable product is rejected'                        => array( true, true, ProductStatus::PUBLISH, 'downloadable' ),
			'unsupported product type is rejected'                    => array( false, false, ProductStatus::PUBLISH, 'type' ),
			'non-published product is rejected'                       => array( false, true, 'draft', 'status' ),
			'published, supported, non-downloadable product is kept'  => array( false, true, ProductStatus::PUBLISH, null ),
			'downloadable takes precedence over wrong type and status' => array( true, false, 'draft', 'downloadable' ),
		);
	}

	/**
	 * GIVEN no third party overrides the exclusion filter
	 * WHEN passes_exclusion_filter() is called
	 * THEN the product is kept (true)
	 */
	public function test_passes_exclusion_filter_keeps_product_when_no_override(): void {
		$product = Mockery::mock( 'WC_Product' );

		expectApplied( 'woocommerce_paypal_payments_store_sync_exclude_product' )
			->once()
			->with( false, $product )
			->andReturn( false );

		$result = $this->product_filter->passes_exclusion_filter( $product );

		$this->assertTrue( $result );
	}

	/**
	 * GIVEN a third party excludes the product via the filter
	 * WHEN passes_exclusion_filter() is called
	 * THEN the product is excluded (false)
	 */
	public function test_passes_exclusion_filter_excludes_product_when_overridden(): void {
		$product = Mockery::mock( 'WC_Product' );

		expectApplied( 'woocommerce_paypal_payments_store_sync_exclude_product' )
			->once()
			->with( false, $product )
			->andReturn( true );

		$result = $this->product_filter->passes_exclusion_filter( $product );

		$this->assertFalse( $result );
	}

	/**
	 * GIVEN a product that was just synced or decided ineligible
	 * WHEN mark_processed() is called
	 * THEN the product's processed-at meta is written with the current timestamp
	 * AND the meta change is persisted
	 */
	public function test_mark_processed_writes_current_timestamp_and_saves_meta(): void {
		$this->expectNotToPerformAssertions();

		when( 'time' )->justReturn( 1700000000 );

		$product = Mockery::mock( WC_Product::class );
		$product->allows( 'get_id' )->andReturn( 42 );
		$product->expects( 'update_meta_data' )
			->once()
			->with( ProductFilter::META_KEY, '1700000000' );
		$product->expects( 'save_meta_data' )->once();

		$this->product_filter->mark_processed( $product );
	}

	/**
	 * GIVEN a product whose eligibility should be re-evaluated
	 * WHEN release() is called
	 * THEN the processed-at meta is deleted
	 * AND the meta change is persisted
	 */
	public function test_release_deletes_processed_meta_and_saves(): void {
		$this->expectNotToPerformAssertions();

		$product = Mockery::mock( WC_Product::class );
		$product->expects( 'delete_meta_data' )
			->once()
			->with( ProductFilter::META_KEY );
		$product->expects( 'save_meta_data' )->once();

		$this->product_filter->release( $product );
	}

	/**
	 * GIVEN a store with products carrying the processed-at marker
	 * WHEN invalidate_all() is called
	 * THEN the processed-at meta is deleted for every product
	 * AND the eligibility-invalidated action is broadcast
	 */
	public function test_invalidate_all_clears_marker_and_broadcasts_action(): void {
		$deleted_key = null;
		when( 'delete_post_meta_by_key' )->alias(
			function ( $key ) use ( &$deleted_key ) {
				$deleted_key = $key;
				return true;
			}
		);

		expectDone( 'woocommerce_paypal_payments_store_sync_eligibility_invalidated' )->once();

		$this->product_filter->invalidate_all();

		$this->assertSame( ProductFilter::META_KEY, $deleted_key );
	}
}
