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
		$this->configuration->allows( 'get_valid_product_filters' )
			->andReturn( array(
				'status'       => ProductStatus::PUBLISH,
				'type'         => $this->product_types,
				'downloadable' => false,
			) );
		$this->configuration->allows( 'get_sync_batch_size' )->andReturn( $this->batch_size );
		$this->configuration->allows( 'get_expired_product_timestamp' )
			->andReturn( $this->expired_timestamp );

		$this->provider = new IngestionBatchProvider( $this->configuration );

		// Mock WordPress date functions
		when( 'gmdate' )->alias( function ( $format, $timestamp = null ) {
			if ( $timestamp === null ) {
				$timestamp = time();
			}

			return gmdate( $format, $timestamp );
		} );

		when( 'strtotime' )->alias( 'strtotime' );
	}

	public function test_get_batch_returns_never_synced_products_first(): void {
		// Arrange
		$never_synced_ids = array( 1, 2, 3, 4, 5 );
		$stale_ids        = array( 6, 7, 8, 9, 10 );

		// Mock wc_get_products calls
		when( 'wc_get_products' )->alias( function ( $args ) use ( $never_synced_ids, $stale_ids ) {
			// First call - products never synced
			if ( isset( $args['meta_query'][0]['key'] ) &&
				$args['meta_query'][0]['key'] === '_ppcp_agentic_last_sync' &&
				$args['meta_query'][0]['compare'] === 'NOT EXISTS' ) {
				$this->assertEquals( ProductStatus::PUBLISH, $args['status'] );
				$this->assertEquals( $this->product_types, $args['type'] );
				$this->assertFalse( $args['downloadable'] );
				$this->assertEquals( $this->batch_size, $args['limit'] );
				$this->assertEquals( 'ids', $args['return'] );

				return $never_synced_ids;
			}

			// Second call - stale products
			if ( isset( $args['meta_query'][0]['key'] ) &&
				$args['meta_query'][0]['key'] === '_ppcp_agentic_last_sync' &&
				$args['meta_query'][0]['compare'] === '<' ) {
				$this->assertEquals( 5, $args['limit'] ); // 10 - 5 already found
				$this->assertEquals( 'DATETIME', $args['meta_query'][0]['type'] );
				$this->assertEquals( 'meta_value', $args['orderby'] );
				$this->assertEquals( 'ASC', $args['order'] );
				$this->assertEquals( '_ppcp_agentic_last_sync', $args['meta_key'] );

				return $stale_ids;
			}

			return array();
		} );

		// Act
		$result = $this->provider->get_batch();

		// Assert
		$this->assertEquals( array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 ), $result );
	}

	public function test_get_batch_respects_limit_with_never_synced_products(): void {
		// Arrange - configure batch size of 5
		$config = Mockery::mock( IngestionConfiguration::class );
		$config->allows( 'get_valid_product_filters' )->andReturn( array(
			'status'       => ProductStatus::PUBLISH,
			'type'         => $this->product_types,
			'downloadable' => false,
		) );
		$config->allows( 'get_sync_batch_size' )->andReturn( 5 );
		$config->allows( 'get_expired_product_timestamp' )->andReturn( $this->expired_timestamp );

		$provider         = new IngestionBatchProvider( $config );
		$never_synced_ids = array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 );

		when( 'wc_get_products' )->alias( function ( $args ) use ( $never_synced_ids ) {
			if ( isset( $args['meta_query'][0]['key'] ) &&
				$args['meta_query'][0]['key'] === '_ppcp_agentic_last_sync' &&
				$args['meta_query'][0]['compare'] === 'NOT EXISTS' ) {
				$this->assertEquals( 5, $args['limit'] );

				return array_slice( $never_synced_ids, 0, 5 );
			}

			return array();
		} );

		// Act
		$result = $provider->get_batch();

		// Assert
		$this->assertEquals( array( 1, 2, 3, 4, 5 ), $result );
		$this->assertCount( 5, $result );
	}

	public function test_get_batch_returns_stale_products_when_no_never_synced(): void {
		// Arrange
		$stale_product_ids = array( 11, 12, 13, 14, 15 );

		when( 'wc_get_products' )->alias( function ( $args ) use ( $stale_product_ids ) {
			// First call - no never synced products
			if ( isset( $args['meta_query'][0]['key'] ) &&
				$args['meta_query'][0]['key'] === '_ppcp_agentic_last_sync' &&
				$args['meta_query'][0]['compare'] === 'NOT EXISTS' ) {
				return array();
			}

			// Second call - stale products
			if ( isset( $args['meta_query'][0]['key'] ) &&
				$args['meta_query'][0]['key'] === '_ppcp_agentic_last_sync' &&
				$args['meta_query'][0]['compare'] === '<' ) {
				$this->assertEquals( $this->batch_size, $args['limit'] ); // Full batch size since no products found yet
				$this->assertEquals( 'DATETIME', $args['meta_query'][0]['type'] );
				$this->assertEquals( 'meta_value', $args['orderby'] );
				$this->assertEquals( 'ASC', $args['order'] );
				$this->assertEquals( '_ppcp_agentic_last_sync', $args['meta_key'] );

				return $stale_product_ids;
			}

			return array();
		} );

		// Act
		$result = $this->provider->get_batch();

		// Assert
		$this->assertEquals( array( 11, 12, 13, 14, 15 ), $result );
	}

	public function test_get_batch_returns_stale_products_when_no_other_products(): void {
		// Arrange
		$stale_product_ids = array( 21, 22, 23, 24 );

		when( 'wc_get_products' )->alias( function ( $args ) use ( $stale_product_ids ) {
			// First call - no never synced products
			if ( isset( $args['meta_query'][0]['key'] ) &&
				$args['meta_query'][0]['key'] === '_ppcp_agentic_last_sync' &&
				$args['meta_query'][0]['compare'] === 'NOT EXISTS' ) {
				$this->assertEquals( $this->batch_size, $args['limit'] );

				return array();
			}

			// Second call - stale products
			if ( isset( $args['meta_query'][0]['key'] ) &&
				$args['meta_query'][0]['key'] === '_ppcp_agentic_last_sync' &&
				$args['meta_query'][0]['compare'] === '<' ) {
				$this->assertEquals( $this->batch_size, $args['limit'] );
				$this->assertEquals( 'DATETIME', $args['meta_query'][0]['type'] );

				// Verify stale date calculation uses configured timestamp
				$expected_stale_date = gmdate( 'Y-m-d H:i:s', $this->expired_timestamp );
				$this->assertEquals( $expected_stale_date, $args['meta_query'][0]['value'] );

				// Verify ordering
				$this->assertEquals( 'meta_value', $args['orderby'] );
				$this->assertEquals( 'ASC', $args['order'] );
				$this->assertEquals( '_ppcp_agentic_last_sync', $args['meta_key'] );

				return $stale_product_ids;
			}

			return array();
		} );

		// Act
		$result = $this->provider->get_batch();

		// Assert
		$this->assertEquals( $stale_product_ids, $result );
	}

	public function test_get_batch_returns_empty_array_when_no_products(): void {
		// Arrange
		when( 'wc_get_products' )->justReturn( array() );

		// Act
		$result = $this->provider->get_batch();

		// Assert
		$this->assertEquals( array(), $result );
	}

	public function test_get_batch_uses_correct_product_query_parameters(): void {
		// Arrange
		$call_count = 0;

		when( 'wc_get_products' )->alias( function ( $args ) use ( &$call_count ) {
			$call_count ++;

			// Common assertions for all calls
			$this->assertEquals( ProductStatus::PUBLISH, $args['status'] );
			$this->assertEquals( $this->product_types, $args['type'] );
			$this->assertFalse( $args['downloadable'] );
			$this->assertEquals( 'ids', $args['return'] );

			// Return different results to trigger both queries
			if ( $call_count === 1 ) {
				// First call - never synced products
				$this->assertEquals( $this->batch_size, $args['limit'] );

				return array( 1, 2, 3 );
			} else {
				// Second call - stale products
				$this->assertEquals( 7, $args['limit'] ); // 10 - 3 already found
				$this->assertEquals( 'meta_value', $args['orderby'] );
				$this->assertEquals( 'ASC', $args['order'] );
				$this->assertEquals( '_ppcp_agentic_last_sync', $args['meta_key'] );

				return array( 4, 5, 6 );
			}
		} );

		// Act
		$result = $this->provider->get_batch();

		// Assert
		$this->assertEquals( 2, $call_count );
		$this->assertEquals( array( 1, 2, 3, 4, 5, 6 ), $result );
	}

	public function test_get_batch_with_custom_stale_timeout(): void {
		// Arrange - configure custom expired timestamp (30 days ago)
		$custom_expired_timestamp = strtotime( '-30 days' );
		$config                   = Mockery::mock( IngestionConfiguration::class );
		$config->allows( 'get_valid_product_filters' )->andReturn( array(
			'status'       => ProductStatus::PUBLISH,
			'type'         => $this->product_types,
			'downloadable' => false,
		) );
		$config->allows( 'get_sync_batch_size' )->andReturn( 10 );
		$config->allows( 'get_expired_product_timestamp' )->andReturn( $custom_expired_timestamp );

		$custom_provider = new IngestionBatchProvider( $config );

		when( 'wc_get_products' )->alias( function ( $args ) use ( $custom_expired_timestamp ) {
			// Skip to stale products check
			if ( isset( $args['meta_query'][0]['key'] ) &&
				$args['meta_query'][0]['key'] === '_ppcp_agentic_last_sync' &&
				$args['meta_query'][0]['compare'] === '<' ) {

				$expected_stale_date = gmdate( 'Y-m-d H:i:s', $custom_expired_timestamp );
				$this->assertEquals( $expected_stale_date, $args['meta_query'][0]['value'] );

				return array( 100, 101, 102 );
			}

			return array();
		} );

		// Act
		$result = $custom_provider->get_batch();

		// Assert
		$this->assertContains( 100, $result );
		$this->assertContains( 101, $result );
		$this->assertContains( 102, $result );
	}

	public function test_get_batch_with_custom_product_types(): void {
		// Arrange - configure custom product types
		$custom_types = array( 'simple', 'variable', 'grouped' );
		$config       = Mockery::mock( IngestionConfiguration::class );
		$config->allows( 'get_valid_product_filters' )->andReturn( array(
			'status'       => ProductStatus::PUBLISH,
			'type'         => $custom_types,
			'downloadable' => false,
		) );
		$config->allows( 'get_sync_batch_size' )->andReturn( 10 );
		$config->allows( 'get_expired_product_timestamp' )->andReturn( $this->expired_timestamp );

		$custom_provider = new IngestionBatchProvider( $config );

		when( 'wc_get_products' )->alias( function ( $args ) use ( $custom_types ) {
			$this->assertEquals( $custom_types, $args['type'] );

			return array( 1, 2, 3 );
		} );

		// Act
		$result = $custom_provider->get_batch();

		// Assert
		$this->assertNotEmpty( $result );
	}

	public function test_get_batch_handles_mixed_results(): void {
		// Arrange - configure batch size of 15
		$config = Mockery::mock( IngestionConfiguration::class );
		$config->allows( 'get_valid_product_filters' )->andReturn( array(
			'status'       => ProductStatus::PUBLISH,
			'type'         => $this->product_types,
			'downloadable' => false,
		) );
		$config->allows( 'get_sync_batch_size' )->andReturn( 15 );
		$config->allows( 'get_expired_product_timestamp' )->andReturn( $this->expired_timestamp );

		$provider = new IngestionBatchProvider( $config );

		when( 'wc_get_products' )->alias( function ( $args ) {
			// First call - never synced products
			if ( isset( $args['meta_query'][0]['key'] ) &&
				$args['meta_query'][0]['key'] === '_ppcp_agentic_last_sync' &&
				$args['meta_query'][0]['compare'] === 'NOT EXISTS' ) {
				return array( 1, 2, 3, 4, 5 );
			}

			// Second call - stale products
			if ( isset( $args['meta_query'][0]['key'] ) &&
				$args['meta_query'][0]['key'] === '_ppcp_agentic_last_sync' &&
				$args['meta_query'][0]['compare'] === '<' ) {
				$this->assertEquals( 10, $args['limit'] ); // 15 - 5
				$this->assertEquals( 'DATETIME', $args['meta_query'][0]['type'] );
				$this->assertEquals( 'meta_value', $args['orderby'] );
				$this->assertEquals( 'ASC', $args['order'] );
				$this->assertEquals( '_ppcp_agentic_last_sync', $args['meta_key'] );

				return array( 6, 7, 8, 9, 10, 11, 12, 13, 14, 15 );
			}

			return array();
		} );

		// Act
		$result = $provider->get_batch();

		// Assert
		$this->assertCount( 15, $result );
		$this->assertEquals( range( 1, 15 ), $result );
	}

	public function test_get_batch_stops_when_limit_reached_after_fresh_products(): void {
		// Arrange - configure batch size of 7
		$config = Mockery::mock( IngestionConfiguration::class );
		$config->allows( 'get_valid_product_filters' )->andReturn( array(
			'status'       => ProductStatus::PUBLISH,
			'type'         => $this->product_types,
			'downloadable' => false,
		) );
		$config->allows( 'get_sync_batch_size' )->andReturn( 7 );
		$config->allows( 'get_expired_product_timestamp' )->andReturn( $this->expired_timestamp );

		$provider = new IngestionBatchProvider( $config );

		when( 'wc_get_products' )->alias( function ( $args ) {
			// First call - never synced products (returns full batch)
			if ( isset( $args['meta_query'][0]['key'] ) &&
				$args['meta_query'][0]['key'] === '_ppcp_agentic_last_sync' &&
				$args['meta_query'][0]['compare'] === 'NOT EXISTS' ) {
				$this->assertEquals( 7, $args['limit'] );

				return array( 1, 2, 3, 4, 5, 6, 7 );
			}

			// This should not be called since batch is already full
			$this->fail( 'Should not query for stale products when limit is reached' );
		} );

		// Act
		$result = $provider->get_batch();

		// Assert
		$this->assertCount( 7, $result );
		$this->assertEquals( array( 1, 2, 3, 4, 5, 6, 7 ), $result );
	}
}
