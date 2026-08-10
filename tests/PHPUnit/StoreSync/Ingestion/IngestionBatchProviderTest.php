<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use Automattic\WooCommerce\Enums\ProductStatus;
use WooCommerce\PayPalCommerce\StoreSync\Config\IngestionConfiguration;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Ingestion\IngestionBatchProvider
 */
class IngestionBatchProviderTest extends TestCase {

	/**
	 * @var IngestionConfiguration|Mockery\MockInterface
	 */
	private $configuration;

	/**
	 * @var ProductFilter|Mockery\MockInterface
	 */
	private $product_filter;

	/**
	 * @var array
	 */
	private $product_types = array( 'simple', 'variable' );

	/**
	 * @var int
	 */
	private $batch_size = 10;

	/**
	 * @var int
	 */
	private $expired_timestamp;

	/**
	 * @var IngestionBatchProvider
	 */
	private $provider;

	public function setUp(): void {
		parent::setUp();

		$this->expired_timestamp = strtotime( '-7 days' );

		$this->configuration = Mockery::mock( IngestionConfiguration::class );
		$this->configuration->allows( 'get_sync_batch_size' )->andReturn( $this->batch_size );
		$this->configuration->allows( 'get_expired_product_timestamp' )
			->andReturn( $this->expired_timestamp );

		$this->product_filter = Mockery::mock( ProductFilter::class );
		$this->product_filter->allows( 'query_filters' )
			->andReturn( array(
				'status'       => ProductStatus::PUBLISH,
				'type'         => $this->product_types,
				'downloadable' => false,
			) );
		$this->product_filter->allows( 'passes_exclusion_filter' )->andReturn( true );

		$this->provider = new IngestionBatchProvider( $this->configuration, $this->product_filter );

		when( 'strtotime' )->alias( 'strtotime' );
	}

	/**
	 * GIVEN a pool of never-synced products and a separate pool of stale products
	 * WHEN get_batch() is called
	 * THEN the never-synced products are returned first
	 * AND the stale products fill the remainder of the batch
	 */
	public function test_get_batch_returns_never_synced_products_first(): void {
		$never_synced_ids = array( 1, 2, 3, 4, 5 );
		$stale_ids        = array( 6, 7, 8, 9, 10 );

		when( 'wc_get_product' )->alias( function () {
			return Mockery::mock( 'WC_Product' );
		} );

		when( 'wc_get_products' )->alias( function ( $args ) use ( $never_synced_ids, $stale_ids ) {
			if ( ! isset( $args['orderby'] ) ) { // Fresh pass (the stale pass sets orderby).
				$available = array_values( array_diff( $never_synced_ids, $args['exclude'] ) );
				return array_slice( $available, 0, $args['limit'] );
			}

			if ( isset( $args['orderby'] ) ) { // Stale pass (orders by the processed-at meta).
				$available = array_values( array_diff( $stale_ids, $args['exclude'] ) );
				return array_slice( $available, 0, $args['limit'] );
			}

			return array();
		} );

		$result = $this->provider->get_batch();

		$this->assertEquals( array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ), $result );
	}

	/**
	 * GIVEN a batch size of 5 and a pool of 10 never-synced products
	 * WHEN get_batch() is called
	 * THEN only 5 products are returned, respecting the configured limit
	 */
	public function test_get_batch_respects_limit_with_never_synced_products(): void {
		$config = Mockery::mock( IngestionConfiguration::class );
		$config->allows( 'get_sync_batch_size' )->andReturn( 5 );
		$config->allows( 'get_expired_product_timestamp' )->andReturn( $this->expired_timestamp );

		$provider         = new IngestionBatchProvider( $config, $this->product_filter );
		$never_synced_ids = array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 );

		when( 'wc_get_product' )->alias( function () {
			return Mockery::mock( 'WC_Product' );
		} );

		when( 'wc_get_products' )->alias( function ( $args ) use ( $never_synced_ids ) {
			if ( ! isset( $args['orderby'] ) ) { // Fresh pass (the stale pass sets orderby).
				$available = array_values( array_diff( $never_synced_ids, $args['exclude'] ) );
				return array_slice( $available, 0, $args['limit'] );
			}

			return array();
		} );

		$result = $provider->get_batch();

		$this->assertEquals( array( 1, 2, 3, 4, 5 ), $result );
		$this->assertCount( 5, $result );
	}

	/**
	 * GIVEN no never-synced products and a pool of stale products
	 * WHEN get_batch() is called
	 * THEN the stale products fill the whole batch
	 */
	public function test_get_batch_returns_stale_products_when_no_never_synced(): void {
		$stale_product_ids = array( 11, 12, 13, 14, 15 );

		when( 'wc_get_product' )->alias( function () {
			return Mockery::mock( 'WC_Product' );
		} );

		when( 'wc_get_products' )->alias( function ( $args ) use ( $stale_product_ids ) {
			if ( isset( $args['orderby'] ) ) { // Stale pass (orders by the processed-at meta).
				$available = array_values( array_diff( $stale_product_ids, $args['exclude'] ) );
				return array_slice( $available, 0, $args['limit'] );
			}

			return array();
		} );

		$result = $this->provider->get_batch();

		$this->assertEquals( array( 11, 12, 13, 14, 15 ), $result );
	}

	/**
	 * GIVEN no products match any freshness clause
	 * WHEN get_batch() is called
	 * THEN an empty batch is returned
	 */
	public function test_get_batch_returns_empty_array_when_no_products(): void {
		when( 'wc_get_products' )->justReturn( array() );

		$result = $this->provider->get_batch();

		$this->assertEquals( array(), $result );
	}

	/**
	 * GIVEN a batch size of 15, 5 never-synced products, and 10 stale products
	 * WHEN get_batch() is called
	 * THEN the never-synced products come first and the stale products top up the rest
	 * AND the full batch of 15 is returned in that order
	 */
	public function test_get_batch_handles_mixed_results(): void {
		$config = Mockery::mock( IngestionConfiguration::class );
		$config->allows( 'get_sync_batch_size' )->andReturn( 15 );
		$config->allows( 'get_expired_product_timestamp' )->andReturn( $this->expired_timestamp );

		$provider   = new IngestionBatchProvider( $config, $this->product_filter );
		$fresh_pool = array( 1, 2, 3, 4, 5 );
		$stale_pool = array( 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 );

		when( 'wc_get_product' )->alias( function () {
			return Mockery::mock( 'WC_Product' );
		} );

		when( 'wc_get_products' )->alias( function ( $args ) use ( $fresh_pool, $stale_pool ) {
			if ( ! isset( $args['orderby'] ) ) { // Fresh pass (the stale pass sets orderby).
				$available = array_values( array_diff( $fresh_pool, $args['exclude'] ) );
				return array_slice( $available, 0, $args['limit'] );
			}

			if ( isset( $args['orderby'] ) ) { // Stale pass (orders by the processed-at meta).
				$available = array_values( array_diff( $stale_pool, $args['exclude'] ) );
				return array_slice( $available, 0, $args['limit'] );
			}

			return array();
		} );

		$result = $provider->get_batch();

		$this->assertCount( 15, $result );
		$this->assertEquals( range( 1, 15 ), $result );
	}

	/**
	 * GIVEN a batch size of 7 and 7 never-synced products available
	 * WHEN get_batch() is called
	 * THEN the batch is filled entirely from never-synced products
	 * AND no stale-products query is issued because the batch is already full
	 */
	public function test_get_batch_stops_when_limit_reached_after_fresh_products(): void {
		$config = Mockery::mock( IngestionConfiguration::class );
		$config->allows( 'get_sync_batch_size' )->andReturn( 7 );
		$config->allows( 'get_expired_product_timestamp' )->andReturn( $this->expired_timestamp );

		$provider   = new IngestionBatchProvider( $config, $this->product_filter );
		$fresh_pool = array( 1, 2, 3, 4, 5, 6, 7 );

		when( 'wc_get_product' )->alias( function () {
			return Mockery::mock( 'WC_Product' );
		} );

		when( 'wc_get_products' )->alias( function ( $args ) use ( $fresh_pool ) {
			if ( ! isset( $args['orderby'] ) ) { // Fresh pass (the stale pass sets orderby).
				$available = array_values( array_diff( $fresh_pool, $args['exclude'] ) );
				return array_slice( $available, 0, $args['limit'] );
			}

			// The stale clause must not be queried once the batch is already full.
			$this->fail( 'Should not query for stale products when limit is reached' );
		} );

		$result = $provider->get_batch();

		$this->assertCount( 7, $result );
		$this->assertEquals( array( 1, 2, 3, 4, 5, 6, 7 ), $result );
	}

	/**
	 * GIVEN a candidate id that wc_get_product() cannot load (e.g. deleted product)
	 * WHEN get_batch() collects eligible products
	 * THEN the un-loadable id is skipped and never appears in the batch
	 * AND the query loop terminates after a bounded number of wc_get_products() calls
	 *     (regression: an un-loadable id must still be excluded from later queries,
	 *     otherwise the same id would be returned by every subsequent query forever)
	 */
	public function test_get_batch_terminates_and_skips_unloadable_product_id(): void {
		$config = Mockery::mock( IngestionConfiguration::class );
		$config->allows( 'get_sync_batch_size' )->andReturn( 2 );
		$config->allows( 'get_expired_product_timestamp' )->andReturn( $this->expired_timestamp );

		$product_filter = Mockery::mock( ProductFilter::class );
		$product_filter->allows( 'query_filters' )->andReturn( array() );
		$product_filter->allows( 'passes_exclusion_filter' )->andReturn( true );

		$provider = new IngestionBatchProvider( $config, $product_filter );

		// The pool only ever contains id 100 (un-loadable) and id 1 (loadable).
		// wc_get_products() is stubbed to behave like the real query: it never
		// returns an id that is present in the "exclude" argument.
		$fresh_pool = array( 100, 1 );
		$call_count = 0;

		when( 'wc_get_products' )->alias( function ( $args ) use ( $fresh_pool, &$call_count ) {
			$call_count++;

			if ( ! isset( $args['orderby'] ) ) { // Fresh pass (the stale pass sets orderby).
				$available = array_values( array_diff( $fresh_pool, $args['exclude'] ) );
				return array_slice( $available, 0, $args['limit'] );
			}

			// Stale pass: no candidates.
			return array();
		} );

		when( 'wc_get_product' )->alias( function ( $id ) {
			return 100 === $id ? false : Mockery::mock( 'WC_Product' );
		} );

		$result = $provider->get_batch();

		$this->assertSame( array( 1 ), $result, 'The un-loadable id must not appear in the batch.' );
		$this->assertLessThanOrEqual(
			5,
			$call_count,
			'wc_get_products() must be called a bounded number of times (no infinite loop).'
		);
	}

	/**
	 * GIVEN a candidate pool where one product fails the exclusion filter
	 * WHEN get_batch() collects eligible products
	 * THEN the excluded product is parked via ProductFilter::mark_processed()
	 * AND is excluded from the batch
	 * AND the batch tops up from the remaining pool until the configured batch size is reached
	 */
	public function test_get_batch_parks_excluded_product_and_tops_up_from_remaining_pool(): void {
		$config = Mockery::mock( IngestionConfiguration::class );
		$config->allows( 'get_sync_batch_size' )->andReturn( 2 );
		$config->allows( 'get_expired_product_timestamp' )->andReturn( $this->expired_timestamp );

		$products = array();
		foreach ( array( 1, 2, 3 ) as $id ) {
			$products[ $id ] = Mockery::mock( 'WC_Product' );
		}

		$product_filter = Mockery::mock( ProductFilter::class );
		$product_filter->allows( 'query_filters' )->andReturn( array() );
		// Only product 1 is excluded by the filter.
		$product_filter->allows( 'passes_exclusion_filter' )->andReturnUsing(
			function ( $product ) use ( $products ) {
				return $product !== $products[1];
			}
		);
		$product_filter->expects( 'mark_processed' )
			->once()
			->with( $products[1], 'Excluded by filter' );

		$provider = new IngestionBatchProvider( $config, $product_filter );

		$fresh_pool = array( 1, 2, 3 );

		when( 'wc_get_products' )->alias( function ( $args ) use ( $fresh_pool ) {
			if ( ! isset( $args['orderby'] ) ) { // Fresh pass (the stale pass sets orderby).
				$available = array_values( array_diff( $fresh_pool, $args['exclude'] ) );
				return array_slice( $available, 0, $args['limit'] );
			}

			return array();
		} );

		when( 'wc_get_product' )->alias( function ( $id ) use ( $products ) {
			return $products[ $id ];
		} );

		$result = $provider->get_batch();

		// The batch is topped up to the full batch size of 2, skipping product 1.
		$this->assertSame( array( 2, 3 ), $result );
	}

	/**
	 * GIVEN the sync batch size is configured to 0
	 * WHEN get_batch() is called
	 * THEN an empty batch is returned
	 * AND no product query is ever issued
	 */
	public function test_get_batch_returns_empty_array_when_batch_size_is_zero(): void {
		$config = Mockery::mock( IngestionConfiguration::class );
		$config->allows( 'get_sync_batch_size' )->andReturn( 0 );
		$config->allows( 'get_expired_product_timestamp' )->andReturn( $this->expired_timestamp );

		$product_filter = Mockery::mock( ProductFilter::class );

		$provider = new IngestionBatchProvider( $config, $product_filter );

		when( 'wc_get_products' )->alias( function () {
			$this->fail( 'wc_get_products() must not be called when the batch size is 0.' );
		} );

		$result = $provider->get_batch();

		$this->assertSame( array(), $result );
	}
}
