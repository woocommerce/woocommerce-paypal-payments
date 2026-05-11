<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\TestCase;
use Psr\Log\LoggerInterface;
use Exception;
use WC_Product;
use Mockery;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Ingestion\SyncJob
 */
class SyncJobTest extends TestCase {

	/**
	 * @var LoggerInterface|Mockery\MockInterface
	 */
	private $logger;

	/**
	 * @var StoreCurrencyValue|Mockery\MockInterface
	 */
	private $store_currency;

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

		$this->store_currency = Mockery::mock( StoreCurrencyValue::class );
		$this->store_currency->allows( 'value' )->andReturn( 'USD' );

		// Stub WordPress functions with default values
		when( 'wp_generate_uuid4' )->justReturn( 'test-batch-id-1234' );
		when( 'current_time' )->justReturn( '2024-01-01 12:00:00' );
		when( 'wc_get_product_category_list' )->justReturn( 'Category1, Category2' );
		when( 'wp_strip_all_tags' )->returnArg();
		when( 'wp_get_attachment_image_url' )->justReturn( 'https://example.com/image.jpg' );
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );
		when( 'wp_json_encode' )->alias( fn( $data ) => json_encode( $data ) );
	}

	/**
	 * Helper method to stub logger to allow all calls without verification.
	 */
	private function stub_logger_to_allow_all(): void {
		$this->logger->allows( 'info' );
		$this->logger->allows( 'warning' );
		$this->logger->allows( 'debug' );
		$this->logger->allows( 'error' );
	}

	/**
	 * Helper method to create a stub simple product with all required methods.
	 *
	 * @param int    $id            Product ID.
	 * @param string $title         Product title.
	 * @param string $price         Product price.
	 * @param bool   $stub_meta_ops Whether to stub meta operations (set false when verifying them).
	 * @return WC_Product|Mockery\MockInterface
	 */
	private function create_product_stub(
		int $id,
		string $title,
		string $price,
		bool $stub_meta_ops = true
	): WC_Product {
		$product = Mockery::mock( WC_Product::class );

		$product->allows( 'get_type' )->andReturn( 'simple' );
		$product->allows( 'is_type' )->with( 'variable' )->andReturn( false );
		$product->allows( 'get_id' )->andReturn( $id );
		$product->allows( 'get_name' )->andReturn( $title );
		$product->allows( 'get_permalink' )->andReturn( "https://example.com/product/{$id}" );
		$product->allows( 'get_image_id' )->andReturn( 100 + $id );
		$product->allows( 'get_description' )->andReturn( "Description for {$title}" );
		$product->allows( 'get_short_description' )->andReturn( "Short desc for {$title}" );
		$product->allows( 'get_price' )->andReturn( $price );
		$product->allows( 'get_stock_status' )->andReturn( 'instock' );
		$product->allows( 'get_sku' )->andReturn( "SKU-{$id}" );
		$product->allows( 'get_sale_price' )->andReturn( '' );
		$product->allows( 'get_regular_price' )->andReturn( $price );

		// Allow meta operations by default (unless we want to verify them)
		if ( $stub_meta_ops ) {
			$product->allows( 'update_meta_data' );
			$product->allows( 'delete_meta_data' );
			$product->allows( 'save_meta_data' );
		}

		return $product;
	}

	/**
	 * Helper method to create multiple product stubs and configure wc_get_product.
	 *
	 * @param array $product_ids   Product IDs.
	 * @param bool  $stub_meta_ops Whether to stub meta operations (set false when verifying them).
	 * @return array Product stubs indexed by ID.
	 */
	private function create_products_and_stub_wc_get_product(
		array $product_ids,
		bool $stub_meta_ops = true
	): array {
		$products = array();
		foreach ( $product_ids as $index => $id ) {
			$products[ $id ] = $this->create_product_stub(
				$id,
				"Product {$id}",
				(string) ( ( $index + 1 ) * 10 ),
				$stub_meta_ops
			);
		}

		when( 'wc_get_product' )->alias( function ( $id ) use ( $products ) {
			return $products[ $id ] ?? false;
		} );

		return $products;
	}

	/**
	 * Helper method to stub a successful API response.
	 */
	private function stub_successful_api_response($expectedProductIds): void {
		when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => '{"success": true}',
		) );
        $body = array( 'product_ids' => $expectedProductIds  );
		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        when( 'wp_remote_retrieve_body' )->justReturn( json_encode($body) );
	}

	/**
	 * Helper method to stub an HTTP error response.
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $body        Response body.
	 */
	private function stub_http_error_response( int $status_code, string $body ): void {
		when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code'    => $status_code,
				'message' => 'Error',
			),
			'body'     => $body,
		) );

		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( $status_code );
		when( 'wp_remote_retrieve_body' )->justReturn( $body );
	}

	/**
	 * GIVEN a sync job with valid product IDs
	 * WHEN the API returns a successful response
	 * THEN all products are marked as synced with current timestamp
	 * AND the needs_sync and sync_error flags are cleared
	 * AND a success message is logged
	 */
	public function test_execute_marks_products_as_synced_when_api_returns_success(): void {
		// Test verification is done through Mockery expectations
		$this->expectNotToPerformAssertions();

		$products = $this->create_products_and_stub_wc_get_product( $this->product_ids, false );
		$this->stub_successful_api_response($this->product_ids);

		// Verify meta operations for successful sync
		foreach ( $products as $product ) {
			$product->shouldReceive( 'update_meta_data' )
				->once()
				->with( '_ppcp_agentic_last_sync', '2024-01-01 12:00:00' );
			$product->shouldReceive( 'delete_meta_data' )
				->once()
				->with( '_ppcp_agentic_sync_error' );
			$product->shouldReceive( 'save_meta_data' )->once();
		}

		$this->logger->shouldReceive( 'debug' )
			->once()
			->with(
				'Start Sync test-batch-id-1234...',
				Mockery::type( 'array' )
			);
		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );
		$this->logger->shouldReceive( 'info' )
			->once()
			->with(
				'Agentic Sync Job test-batch-id-1234: Successfully synced 3 products',
				array( 'product_ids' => $this->product_ids )
			);

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$this->product_ids,
			$this->logger,
			$this->store_currency
		);

		$sync_job->execute();
	}

	/**
	 * GIVEN a sync job with product IDs that don't exist in the database
	 * WHEN execute is called
	 * THEN no API request is made
	 * AND a "No products" message is logged
	 */
	public function test_execute_logs_no_products_when_all_product_ids_are_invalid(): void {
		// Test verification is done through Mockery expectations
		$this->expectNotToPerformAssertions();

		when( 'wc_get_product' )->justReturn( false );

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: No products' );

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$this->product_ids,
			$this->logger,
			$this->store_currency
		);

		$sync_job->execute();
	}

	/**
	 * GIVEN a sync job with valid products
	 * WHEN the API returns a WordPress error (network failure)
	 * THEN all products are marked with the error message
	 * AND the error is logged with product details
	 * AND a RuntimeException is thrown for Action Scheduler retry
	 */
	public function test_execute_throws_exception_when_wp_error_occurs(): void {
		$products = $this->create_products_and_stub_wc_get_product( $this->product_ids, false );

		// Verify error meta is set on products
		foreach ( $products as $product ) {
			$product->shouldReceive( 'update_meta_data' )
				->once()
				->with( '_ppcp_agentic_sync_error', 'Connection timeout' );
			$product->shouldReceive( 'save_meta_data' )->once();
		}

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );
        when( 'wp_remote_retrieve_body' )->justReturn( '' );

		$this->logger->shouldReceive( 'warning' )
			->once()
			->with(
				'Agentic Sync Job test-batch-id-1234: API Error - Connection timeout',
				array(
					'product_count' => 3,
					'product_ids'   => $this->product_ids,
				)
			);

		// Stub WP_Error response
		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->allows( 'get_error_message' )->andReturn( 'Connection timeout' );

		when( 'wp_remote_post' )->justReturn( $wp_error );
		$this->logger->shouldReceive( 'debug' )
			->once()
			->with(
				'Start Sync test-batch-id-1234...',
				Mockery::type( 'array' )
			);
		when( 'is_wp_error' )->justReturn( true );

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$this->product_ids,
			$this->logger,
			$this->store_currency
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Agentic sync failed: Connection timeout' );

		$sync_job->execute();
	}

	/**
	 * GIVEN a sync job with valid products
	 * WHEN the API returns an HTTP error status code
	 * THEN all products are marked with the error message including status and body
	 * AND a RuntimeException is thrown for Action Scheduler retry
	 *
	 * @dataProvider http_error_provider
	 */
	public function test_execute_throws_exception_when_api_returns_http_error(
		int $status_code,
		string $response_body,
		string $expected_error
	): void {
		$products = $this->create_products_and_stub_wc_get_product( $this->product_ids, false );
		$this->stub_http_error_response( $status_code, $response_body );

		// Verify error meta is set on products
		foreach ( $products as $product ) {
			$product->shouldReceive( 'update_meta_data' )
				->once()
				->with( '_ppcp_agentic_sync_error', $expected_error );
			$product->shouldReceive( 'save_meta_data' )->once();
		}
		$this->logger->shouldReceive( 'debug' )
			->once()
			->with(
				'Start Sync test-batch-id-1234...',
				Mockery::type( 'array' )
			);
		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );

		$this->logger->shouldReceive( 'warning' )
			->once()
			->with(
				"Agentic Sync Job test-batch-id-1234: API Error - {$expected_error}",
				array(
					'product_count' => 3,
					'product_ids'   => $this->product_ids,
				)
			);

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$this->product_ids,
			$this->logger,
			$this->store_currency
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( "Agentic sync failed: {$expected_error}" );

		$sync_job->execute();
	}

	/**
	 * Provides HTTP error scenarios for testing.
	 */
	public function http_error_provider(): array {
		return array(
			'internal server error (500)'       => array(
				500,
				'Internal Server Error',
				'HTTP 500: Internal Server Error',
			),
			'service unavailable (503)'         => array(
				503,
				'Service Temporarily Unavailable',
				'HTTP 503: Service Temporarily Unavailable',
			),
		);
	}

	/**
	 * GIVEN a sync job with valid products
	 * WHEN execute is called
	 * THEN the API request is sent to the correct endpoint
	 * AND the request has proper timeout and content-type headers
	 * AND the body contains merchant URL and product data
	 */
	public function test_execute_sends_properly_formatted_api_request(): void {
		$this->create_products_and_stub_wc_get_product( $this->product_ids );
		$this->stub_logger_to_allow_all();

		$api_endpoint     = $this->api_endpoint;
		$captured_request = null;

		when( 'wp_remote_post' )->alias( function ( $url, $args ) use ( $api_endpoint, &$captured_request ) {
			$captured_request = array( 'url' => $url, 'args' => $args );

			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => '{"success": true}',
			);
		} );

		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
        when( 'wp_remote_retrieve_body' )->justReturn( '}' );
		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$this->product_ids,
			$this->logger,
			$this->store_currency
		);

		$sync_job->execute();

		$this->assertSame( $api_endpoint, $captured_request['url'] );
		$this->assertSame( 30, $captured_request['args']['timeout'] );
		$this->assertSame( 'application/json', $captured_request['args']['headers']['Content-Type'] );
        $body = json_decode( $captured_request['args']['body'], true );

		$this->assertSame( 'https://example.com', $body['merchant_url'] );
		$this->assertIsArray( $body['products'] );
		$this->assertCount( 3, $body['products'] );
	}

	/**
	 * GIVEN a sync job with a mix of valid and invalid product IDs
	 * WHEN execute is called
	 * THEN only valid products are included in the API payload
	 * AND valid products are marked as synced
	 */
	public function test_execute_handles_mixed_valid_and_invalid_product_ids(): void {
		// Test verification is done through Mockery expectations
		$this->expectNotToPerformAssertions();

		$valid_product = $this->create_product_stub( 1, 'Product 1', '10', false );

		when( 'wc_get_product' )->alias( function ( $id ) use ( $valid_product ) {
			return $id === 1 ? $valid_product : false;
		} );
        $expectedProductIds = array(1, 999, 888);
		$this->stub_successful_api_response($expectedProductIds);
		// Verify only the valid product gets synced
		$valid_product->shouldReceive( 'update_meta_data' )
			->once()
			->with( '_ppcp_agentic_last_sync', '2024-01-01 12:00:00' );
		$valid_product->shouldReceive( 'delete_meta_data' )
			->once()
			->with( '_ppcp_agentic_sync_error' );
		$valid_product->shouldReceive( 'save_meta_data' )->once();

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );
		$this->logger->shouldReceive( 'debug' )
			->once()
			->with(
				'Start Sync test-batch-id-1234...',
				Mockery::type( 'array' )
			);

        $this->logger->shouldReceive( 'info' )
			->once()
			->with(
				'Agentic Sync Job test-batch-id-1234: Successfully synced 3 products',
				array( 'product_ids' => $expectedProductIds)
			);

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$expectedProductIds,
			$this->logger,
			$this->store_currency
		);

		$sync_job->execute();
	}

	/**
	 * GIVEN a sync job with an empty product IDs array
	 * WHEN execute is called
	 * THEN no API request is made
	 * AND a "No products" message is logged
	 */
	public function test_execute_logs_no_products_when_product_ids_array_is_empty(): void {
		// Test verification is done through Mockery expectations
		$this->expectNotToPerformAssertions();

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );
		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: No products' );

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			array(),
			$this->logger,
			$this->store_currency
		);

		$sync_job->execute();
	}

	/**
	 * GIVEN a sync job with a single product
	 * WHEN the API returns success
	 * THEN the correct product count is logged
	 */
	public function test_execute_logs_correct_count_for_single_product(): void {
		// Test verification is done through Mockery expectations
		$this->expectNotToPerformAssertions();

		$product = $this->create_product_stub( 42, 'Single Product', '25', false );

		when( 'wc_get_product' )->alias( function ( $id ) use ( $product ) {
			return $id === 42 ? $product : false;
		} );
        $expectedProductIds = array(42);
		$this->stub_successful_api_response($expectedProductIds);

		$product->shouldReceive( 'update_meta_data' )
			->once()
			->with( '_ppcp_agentic_last_sync', '2024-01-01 12:00:00' );
		$product->shouldReceive( 'delete_meta_data' )
			->once()
			->with( '_ppcp_agentic_sync_error' );
		$product->shouldReceive( 'save_meta_data' )->once();

		$this->logger->shouldReceive( 'info' )
			->once()
			->with( 'Agentic Sync Job test-batch-id-1234: Started' );
		$this->logger->shouldReceive( 'debug' )
			->once()
			->with(
				'Start Sync test-batch-id-1234...',
				Mockery::type( 'array' )
			);

        $this->logger->shouldReceive( 'info' )
			->once()
			->with(
				'Agentic Sync Job test-batch-id-1234: Successfully synced 1 products',
				array( 'product_ids' => $expectedProductIds)
			);

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$expectedProductIds,
			$this->logger,
			$this->store_currency
		);

		$sync_job->execute();
	}
}
