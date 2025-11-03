<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion;

use WooCommerce\PayPalCommerce\TestCase;
use Psr\Log\LoggerInterface;
use Exception;
use WC_Product;
use Mockery;
use function Brain\Monkey\Functions\when;

/**
 * @covers SyncJob
 */
class SyncJobTest extends TestCase {

	/**
	 * @var LoggerInterface|Mockery\MockInterface
	 */
	private $logger;

	/**
	 * @var ProductsPayloadFactory|Mockery\MockInterface
	 */
	private $products_payload_factory;

	/**
	 * @var ProductsPayload|Mockery\MockInterface
	 */
	private $products_payload;

	/**
	 * @var string
	 */
	private $api_endpoint = 'https://api.example.com/sync';

	/**
	 * @var array
	 */
	private $product_ids = array( 1, 2, 3 );

	public function setUp(): void {
		parent::setUp();

		$this->logger = Mockery::mock( LoggerInterface::class );
		$this->products_payload_factory = Mockery::mock( ProductsPayloadFactory::class );
		$this->products_payload = Mockery::mock( ProductsPayload::class );

		// Mock WordPress functions
		when( 'wp_generate_uuid4' )->justReturn( 'test-batch-id-1234' );
		when( 'home_url' )->justReturn( 'https://example.com' );
		when( 'current_time' )->justReturn( '2024-01-01 12:00:00' );
	}

	public function test_successful_sync(): void {
		// Arrange
		$api_payload = array(
			array(
				'id' => '1',
				'title' => 'Product 1',
				'price' => '10.00 USD',
			),
			array(
				'id' => '2',
				'title' => 'Product 2',
				'price' => '20.00 USD',
			),
		);

		$this->products_payload_factory->shouldReceive( 'create' )
			->once()
			->with( $this->product_ids )
			->andReturn( $this->products_payload );

		$this->products_payload->shouldReceive( 'get_array' )
			->once()
			->andReturn( $api_payload );

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );

		$this->logger->shouldReceive( 'info' )
			->once()
			->with(
				'Agentic Sync Job test-batch-id-1234: Successfully synced 3 products',
				array( 'product_ids' => $this->product_ids )
			);

		// Mock successful API response
		when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'body' => '{"success": true}',
		) );

		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		when( 'wp_json_encode' )->alias( function( $data ) {
			return json_encode( $data );
		} );

		// Mock product updates
		$this->mockProductUpdates( $this->product_ids );

		// Act
		$sync_job = new SyncJob(
			$this->api_endpoint,
			$this->product_ids,
			$this->logger,
			$this->products_payload_factory
		);

		$sync_job->execute();

		// Assert - expectations are verified automatically by Mockery
		$this->assertTrue( true ); // Add assertion to avoid risky test warning
	}

	public function test_sync_with_empty_payload(): void {
		// Arrange
		$this->products_payload_factory->shouldReceive( 'create' )
			->once()
			->with( $this->product_ids )
			->andReturn( $this->products_payload );

		$this->products_payload->shouldReceive( 'get_array' )
			->once()
			->andReturn( array() );

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: No products' );

		// Act
		$sync_job = new SyncJob(
			$this->api_endpoint,
			$this->product_ids,
			$this->logger,
			$this->products_payload_factory
		);

		$sync_job->execute();

		// Assert - verify no API call was made
		$this->assertTrue( true ); // Add assertion to avoid risky test warning
	}

	public function test_sync_with_wp_error(): void {
		// Arrange
		$api_payload = array(
			array( 'id' => '1', 'title' => 'Product 1' ),
		);

		$this->products_payload_factory->shouldReceive( 'create' )
			->once()
			->with( $this->product_ids )
			->andReturn( $this->products_payload );

		$this->products_payload->shouldReceive( 'get_array' )
			->once()
			->andReturn( $api_payload );

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );

		$this->logger->shouldReceive( 'warning' )
			->once()
			->with(
				'Agentic Sync Job Connection timeout: Error',
				array(
					'product_count' => 3,
					'product_ids' => $this->product_ids,
				)
			);

		// Mock WP_Error response
		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )
			->once()
			->andReturn( 'Connection timeout' );

		when( 'wp_remote_post' )->justReturn( $wp_error );
		when( 'is_wp_error' )->justReturn( true );
		when( 'wp_json_encode' )->alias( function( $data ) {
			return json_encode( $data );
		} );

		// Mock product error updates
		$this->mockProductErrorUpdates( $this->product_ids, 'Connection timeout' );

		// Act & Assert
		$sync_job = new SyncJob(
			$this->api_endpoint,
			$this->product_ids,
			$this->logger,
			$this->products_payload_factory
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Agentic sync failed: Connection timeout' );

		$sync_job->execute();
	}

	public function test_sync_with_http_error(): void {
		// Arrange
		$api_payload = array(
			array( 'id' => '1', 'title' => 'Product 1' ),
		);

		$this->products_payload_factory->shouldReceive( 'create' )
			->once()
			->with( $this->product_ids )
			->andReturn( $this->products_payload );

		$this->products_payload->shouldReceive( 'get_array' )
			->once()
			->andReturn( $api_payload );

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );

		$this->logger->shouldReceive( 'warning' )
			->once()
			->with(
				'Agentic Sync Job HTTP 500: Internal Server Error: Error',
				array(
					'product_count' => 3,
					'product_ids' => $this->product_ids,
				)
			);

		// Mock HTTP error response
		when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code' => 500,
				'message' => 'Internal Server Error',
			),
			'body' => 'Internal Server Error',
		) );

		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( 500 );
		when( 'wp_remote_retrieve_body' )->justReturn( 'Internal Server Error' );
		when( 'wp_json_encode' )->alias( function( $data ) {
			return json_encode( $data );
		} );

		// Mock product error updates
		$this->mockProductErrorUpdates( $this->product_ids, 'HTTP 500: Internal Server Error' );

		// Act & Assert
		$sync_job = new SyncJob(
			$this->api_endpoint,
			$this->product_ids,
			$this->logger,
			$this->products_payload_factory
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Agentic sync failed: HTTP 500: Internal Server Error' );

		$sync_job->execute();
	}

	public function test_sync_with_400_bad_request(): void {
		// Arrange
		$api_payload = array(
			array( 'id' => '1', 'title' => 'Product 1' ),
		);

		$this->products_payload_factory->shouldReceive( 'create' )
			->once()
			->with( $this->product_ids )
			->andReturn( $this->products_payload );

		$this->products_payload->shouldReceive( 'get_array' )
			->once()
			->andReturn( $api_payload );

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );

		$this->logger->shouldReceive( 'warning' )
			->once()
			->with(
				'Agentic Sync Job HTTP 400: {"error": "Invalid product data"}: Error',
				array(
					'product_count' => 3,
					'product_ids' => $this->product_ids,
				)
			);

		// Mock HTTP 400 response
		when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code' => 400,
				'message' => 'Bad Request',
			),
			'body' => '{"error": "Invalid product data"}',
		) );

		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( 400 );
		when( 'wp_remote_retrieve_body' )->justReturn( '{"error": "Invalid product data"}' );
		when( 'wp_json_encode' )->alias( function( $data ) {
			return json_encode( $data );
		} );

		// Mock product error updates
		$this->mockProductErrorUpdates( $this->product_ids, 'HTTP 400: {"error": "Invalid product data"}' );

		// Act & Assert
		$sync_job = new SyncJob(
			$this->api_endpoint,
			$this->product_ids,
			$this->logger,
			$this->products_payload_factory
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Agentic sync failed: HTTP 400: {"error": "Invalid product data"}' );

		$sync_job->execute();
	}

	public function test_sync_marks_products_correctly(): void {
		// Arrange
		$api_payload = array(
			array( 'id' => '1', 'title' => 'Product 1' ),
		);

		$this->products_payload_factory->shouldReceive( 'create' )
			->once()
			->with( $this->product_ids )
			->andReturn( $this->products_payload );

		$this->products_payload->shouldReceive( 'get_array' )
			->once()
			->andReturn( $api_payload );

		$this->logger->shouldReceive( 'info' )->twice();

		// Mock successful API response
		when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'body' => '{"success": true}',
		) );

		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		when( 'wp_json_encode' )->alias( function( $data ) {
			return json_encode( $data );
		} );

		// Mock products and verify metadata updates
		$products = array();
		foreach ( $this->product_ids as $product_id ) {
			$product = Mockery::mock( WC_Product::class );

			$product->shouldReceive( 'update_meta_data' )
				->once()
				->with( '_ppcp_agentic_last_sync', '2024-01-01 12:00:00' );

			$product->shouldReceive( 'delete_meta_data' )
				->once()
				->with( '_ppcp_agentic_needs_sync' );

			$product->shouldReceive( 'delete_meta_data' )
				->once()
				->with( '_ppcp_agentic_sync_error' );

			$product->shouldReceive( 'save_meta_data' )
				->once();

			$products[ $product_id ] = $product;
		}

		when( 'wc_get_product' )->alias( function( $id ) use ( $products ) {
			return isset( $products[ $id ] ) ? $products[ $id ] : false;
		} );

		// Act
		$sync_job = new SyncJob(
			$this->api_endpoint,
			$this->product_ids,
			$this->logger,
			$this->products_payload_factory
		);

		$sync_job->execute();

		// Assert
		$this->assertTrue( true ); // Add assertion to avoid risky test warning
	}

	public function test_sync_with_invalid_product_id(): void {
		// Arrange
		$api_payload = array(
			array( 'id' => '999', 'title' => 'Invalid Product' ),
		);

		$invalid_product_ids = array( 999 );

		$this->products_payload_factory->shouldReceive( 'create' )
			->once()
			->with( $invalid_product_ids )
			->andReturn( $this->products_payload );

		$this->products_payload->shouldReceive( 'get_array' )
			->once()
			->andReturn( $api_payload );

		$this->logger->shouldReceive( 'info' )->twice();

		// Mock successful API response
		when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code' => 200,
				'message' => 'OK',
			),
			'body' => '{"success": true}',
		) );

		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		when( 'wp_json_encode' )->alias( function( $data ) {
			return json_encode( $data );
		} );

		// Mock wc_get_product to return false for invalid ID
		when( 'wc_get_product' )->justReturn( false );

		// Act
		$sync_job = new SyncJob(
			$this->api_endpoint,
			$invalid_product_ids,
			$this->logger,
			$this->products_payload_factory
		);

		// Should not throw exception even if product not found
		$sync_job->execute();

		// Assert
		$this->assertTrue( true ); // Add assertion to avoid risky test warning
	}

	public function test_api_request_format(): void {
		// Arrange
		$api_payload = array(
			array( 'id' => '1', 'title' => 'Product 1' ),
		);

		$this->products_payload_factory->shouldReceive( 'create' )
			->once()
			->with( $this->product_ids )
			->andReturn( $this->products_payload );

		$this->products_payload->shouldReceive( 'get_array' )
			->once()
			->andReturn( $api_payload );

		$this->logger->shouldReceive( 'info' )->twice();

		$expected_body = json_encode( array(
			'merchant_url' => 'https://example.com',
			'products' => $api_payload,
		) );

		// Mock wp_remote_post to verify the request format
		when( 'wp_remote_post' )->alias( function( $url, $args ) use ( $expected_body ) {
			$this->assertEquals( $this->api_endpoint, $url );
			$this->assertEquals( 30, $args['timeout'] );
			$this->assertEquals( 'application/json', $args['headers']['Content-Type'] );
			$this->assertEquals( $expected_body, $args['body'] );

			return array(
				'response' => array(
					'code' => 200,
					'message' => 'OK',
				),
				'body' => '{"success": true}',
			);
		} );

		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		when( 'wp_json_encode' )->alias( function( $data ) {
			return json_encode( $data );
		} );

		$this->mockProductUpdates( $this->product_ids );

		// Act
		$sync_job = new SyncJob(
			$this->api_endpoint,
			$this->product_ids,
			$this->logger,
			$this->products_payload_factory
		);

		$sync_job->execute();

		// Assert
		$this->assertTrue( true ); // Add assertion to avoid risky test warning
	}

	/**
	 * Helper method to mock product updates for successful sync.
	 *
	 * @param array $product_ids Product IDs to mock.
	 */
	private function mockProductUpdates( array $product_ids ): void {
		$products = array();
		foreach ( $product_ids as $product_id ) {
			$product = Mockery::mock( WC_Product::class );

			$product->shouldReceive( 'update_meta_data' )
				->once()
				->with( '_ppcp_agentic_last_sync', '2024-01-01 12:00:00' );

			$product->shouldReceive( 'delete_meta_data' )
				->once()
				->with( '_ppcp_agentic_needs_sync' );

			$product->shouldReceive( 'delete_meta_data' )
				->once()
				->with( '_ppcp_agentic_sync_error' );

			$product->shouldReceive( 'save_meta_data' )
				->once();

			$products[ $product_id ] = $product;
		}

		when( 'wc_get_product' )->alias( function( $id ) use ( $products ) {
			return isset( $products[ $id ] ) ? $products[ $id ] : false;
		} );
	}

	/**
	 * Helper method to mock product updates for error cases.
	 *
	 * @param array  $product_ids Product IDs to mock.
	 * @param string $error_message Error message to set.
	 */
	private function mockProductErrorUpdates( array $product_ids, string $error_message ): void {
		$products = array();
		foreach ( $product_ids as $product_id ) {
			$product = Mockery::mock( WC_Product::class );

			$product->shouldReceive( 'update_meta_data' )
				->once()
				->with( '_ppcp_agentic_sync_error', $error_message );

			$product->shouldReceive( 'save_meta_data' )
				->once();

			$products[ $product_id ] = $product;
		}

		when( 'wc_get_product' )->alias( function( $id ) use ( $products ) {
			return isset( $products[ $id ] ) ? $products[ $id ] : false;
		} );
	}
}
