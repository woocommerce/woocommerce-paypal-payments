<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\TestCase;
use Psr\Log\LoggerInterface;
use Exception;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use Mockery;
use function Brain\Monkey\Actions\expectDone;
use function Brain\Monkey\Functions\expect;
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
	 * @var ProductManager
	 */
	private $product_manager;

	/**
	 * @var ProductFilter|Mockery\MockInterface
	 */
	private $product_filter;

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

		// Catch-all logger: logging is a side effect we never assert on.
		$this->logger = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		$store_currency = Mockery::mock( StoreCurrencyValue::class );
		$store_currency->allows( 'value' )->andReturn( 'USD' );
		$this->product_manager = new ProductManager( $store_currency );

		$this->product_filter = Mockery::mock( ProductFilter::class );

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
	 * Helper method to create a stub simple product with all required methods.
	 *
	 * @param int    $id    Product ID.
	 * @param string $title Product title.
	 * @param string $price Product price.
	 * @return WC_Product|Mockery\MockInterface
	 */
	private function create_product_stub( int $id, string $title, string $price ): WC_Product {
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

		return $product;
	}

	/**
	 * Helper method to create multiple product stubs and configure wc_get_product.
	 *
	 * @param array $product_ids Product IDs.
	 * @return array Product stubs indexed by ID.
	 */
	private function create_products_and_stub_wc_get_product( array $product_ids ): array {
		$products = array();
		foreach ( $product_ids as $index => $id ) {
			$products[ $id ] = $this->create_product_stub(
				$id,
				"Product {$id}",
				(string) ( ( $index + 1 ) * 10 )
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
	private function stub_successful_api_response( $expectedProductIds ): void {
		when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => '{"success": true}',
		) );
		$body = array( 'product_ids' => $expectedProductIds );
		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		when( 'wp_remote_retrieve_body' )->justReturn( json_encode( $body ) );
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
	 * THEN every product is marked processed so it is not re-synced in the next batch
	 */
	public function test_execute_marks_products_as_synced_when_api_returns_success(): void {
		$this->expectNotToPerformAssertions();

		$products = $this->create_products_and_stub_wc_get_product( $this->product_ids );
		$this->stub_successful_api_response( $this->product_ids );

		foreach ( $products as $product ) {
			$this->product_filter->shouldReceive( 'mark_processed' )
				->once()
				->with( $product );
		}

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$this->product_ids,
			$this->logger,
			$this->product_manager,
			$this->product_filter
		);

		$sync_job->execute();
	}

	/**
	 * GIVEN product IDs that do not resolve to any product
	 * WHEN execute is called
	 * THEN no API request is made
	 */
	public function test_execute_makes_no_api_request_when_all_product_ids_are_invalid(): void {
		when( 'wc_get_product' )->justReturn( false );

		expect( 'wp_remote_post' )->never();

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$this->product_ids,
			$this->logger,
			$this->product_manager,
			$this->product_filter
		);

		$sync_job->execute();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a sync job with an empty product IDs array
	 * WHEN execute is called
	 * THEN no API request is made
	 */
	public function test_execute_makes_no_api_request_when_product_ids_array_is_empty(): void {
		expect( 'wp_remote_post' )->never();

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			array(),
			$this->logger,
			$this->product_manager,
			$this->product_filter
		);

		$sync_job->execute();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * GIVEN a sync job with valid products
	 * WHEN the API returns a WordPress error (network failure)
	 * THEN no products are marked as processed, so they are retried on the next run
	 * AND a RuntimeException is thrown for Action Scheduler retry
	 */
	public function test_execute_throws_exception_when_wp_error_occurs(): void {
		$this->create_products_and_stub_wc_get_product( $this->product_ids );

		// Products are left unmarked so Action Scheduler retries them.
		$this->product_filter->shouldNotReceive( 'mark_processed' );

		when( 'wp_remote_retrieve_body' )->justReturn( '' );

		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->allows( 'get_error_message' )->andReturn( 'Connection timeout' );

		when( 'wp_remote_post' )->justReturn( $wp_error );
		when( 'is_wp_error' )->justReturn( true );

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$this->product_ids,
			$this->logger,
			$this->product_manager,
			$this->product_filter
		);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Agentic sync failed: Connection timeout' );

		$sync_job->execute();
	}

	/**
	 * GIVEN a sync job with valid products
	 * WHEN the API returns an HTTP error status code
	 * THEN no products are marked as processed, so they are retried on the next run
	 * AND a RuntimeException is thrown for Action Scheduler retry
	 *
	 * @dataProvider http_error_provider
	 */
	public function test_execute_throws_exception_when_api_returns_http_error(
		int $status_code,
		string $response_body,
		string $expected_error
	): void {
		$this->create_products_and_stub_wc_get_product( $this->product_ids );
		$this->stub_http_error_response( $status_code, $response_body );

		// Products are left unmarked so Action Scheduler retries them.
		$this->product_filter->shouldNotReceive( 'mark_processed' );

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$this->product_ids,
			$this->logger,
			$this->product_manager,
			$this->product_filter
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
			'internal server error (500)' => array(
				500,
				'Internal Server Error',
				'HTTP 500: Internal Server Error',
			),
			'service unavailable (503)'   => array(
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
		$this->product_filter->allows( 'mark_processed' );

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
			$this->product_manager,
			$this->product_filter
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
	 * THEN only the valid product is marked processed
	 */
	public function test_execute_handles_mixed_valid_and_invalid_product_ids(): void {
		$this->expectNotToPerformAssertions();

		$valid_product = $this->create_product_stub( 1, 'Product 1', '10' );

		when( 'wc_get_product' )->alias( function ( $id ) use ( $valid_product ) {
			return $id === 1 ? $valid_product : false;
		} );
		$expectedProductIds = array( 1, 999, 888 );
		$this->stub_successful_api_response( $expectedProductIds );

		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with( $valid_product );

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$expectedProductIds,
			$this->logger,
			$this->product_manager,
			$this->product_filter
		);

		$sync_job->execute();
	}

	/**
	 * GIVEN a batch of two products where one produces a payload entry missing a required
	 * field (an empty title)
	 * WHEN execute is called and the API responds successfully
	 * THEN only the complete product is included in the API request payload
	 * AND the incomplete product is marked processed with an "Incomplete payload" note
	 * AND the complete product is still marked processed as synced
	 */
	public function test_execute_drops_incomplete_product_from_payload_and_marks_it_processed(): void {
		$complete_product   = $this->create_product_stub( 1, 'Complete Product', '10' );
		$incomplete_product = $this->create_product_stub( 2, '', '20' );

		when( 'wc_get_product' )->alias( function ( $id ) use ( $complete_product, $incomplete_product ) {
			if ( 1 === $id ) {
				return $complete_product;
			}
			if ( 2 === $id ) {
				return $incomplete_product;
			}

			return false;
		} );

		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with( $incomplete_product, 'Incomplete payload' );
		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with( $complete_product );

		$captured_request = null;
		when( 'wp_remote_post' )->alias( function ( $url, $args ) use ( &$captured_request ) {
			$captured_request = $args;

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
		when( 'wp_remote_retrieve_body' )->justReturn( '{"success": true}' );

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			array( 1, 2 ),
			$this->logger,
			$this->product_manager,
			$this->product_filter
		);

		$sync_job->execute();

		$body = json_decode( $captured_request['body'], true );

		$this->assertCount( 1, $body['products'] );
		$this->assertSame( '1', $body['products'][0]['id'] );
	}

	/**
	 * GIVEN a batch where every product's payload entry is missing a required field
	 * WHEN execute is called
	 * THEN no API request is sent
	 * AND the ingestion-completed action reports an "empty" batch with nothing pushed,
	 * synced, or failed
	 */
	public function test_execute_skips_api_request_when_all_products_are_incomplete(): void {
		$product_one = $this->create_product_stub( 1, '', '10' );
		$product_two = $this->create_product_stub( 2, '', '20' );

		when( 'wc_get_product' )->alias( function ( $id ) use ( $product_one, $product_two ) {
			if ( 1 === $id ) {
				return $product_one;
			}
			if ( 2 === $id ) {
				return $product_two;
			}

			return false;
		} );

		$this->product_filter->allows( 'mark_processed' );

		expect( 'wp_remote_post' )->never();

		$captured_action = null;
		expectDone( 'woocommerce_paypal_payments_store_sync_ingestion_completed' )
			->once()
			->whenHappen( function ( array $payload ) use ( &$captured_action ) {
				$captured_action = $payload;
			} );

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			array( 1, 2 ),
			$this->logger,
			$this->product_manager,
			$this->product_filter
		);

		$sync_job->execute();

		$this->assertSame( 'empty', $captured_action['status'] );
		$this->assertSame( 0, $captured_action['pushed'] );
		$this->assertSame( 0, $captured_action['synced'] );
		$this->assertSame( 0, $captured_action['failed'] );
	}

	/**
	 * GIVEN a single variable product whose two variations expand into two payload entries,
	 * both sharing the parent's item_group_id
	 * WHEN the API reports a validation error on the second payload entry (index 1)
	 * THEN the error is attributed to the parent product, resolved via item_group_id rather
	 * than by indexing into the original product-id list
	 * AND the ingestion-completed action reports one failure with status "validation_errors"
	 */
	public function test_execute_attributes_validation_error_to_parent_product_via_item_group_id(): void {
		$parent_id     = 100;
		$variation1_id = 101;
		$variation2_id = 102;

		$parent_product = Mockery::mock( WC_Product_Variable::class . ', ' . WC_Product::class );
		$parent_product->shouldReceive( 'get_type' )->andReturn( 'variable' );
		$parent_product->shouldReceive( 'is_type' )->with( 'variable' )->andReturn( true );
		$parent_product->shouldReceive( 'get_children' )->andReturn( array( $variation1_id, $variation2_id ) );
		$parent_product->shouldReceive( 'get_id' )->andReturn( $parent_id );
		$parent_product->shouldReceive( 'get_image_id' )->andReturn( 300 );
		$parent_product->shouldReceive( 'get_description' )->andReturn( 'Parent description' );

		$variation1 = Mockery::mock( WC_Product_Variation::class );
		$variation1->shouldReceive( 'is_purchasable' )->andReturn( true );
		$variation1->shouldReceive( 'get_id' )->andReturn( $variation1_id );
		$variation1->shouldReceive( 'get_name' )->andReturn( 'Variant Red' );
		$variation1->shouldReceive( 'get_permalink' )->andReturn( 'https://example.com/v101' );
		$variation1->shouldReceive( 'get_image_id' )->andReturn( 0 );
		$variation1->shouldReceive( 'get_description' )->andReturn( '' );
		$variation1->shouldReceive( 'get_price' )->andReturn( '10.00' );
		$variation1->shouldReceive( 'get_stock_status' )->andReturn( 'instock' );
		$variation1->shouldReceive( 'get_sku' )->andReturn( 'SKU-101' );
		$variation1->shouldReceive( 'get_sale_price' )->andReturn( '' );
		$variation1->shouldReceive( 'get_variation_attributes' )->andReturn( array() );

		$variation2 = Mockery::mock( WC_Product_Variation::class );
		$variation2->shouldReceive( 'is_purchasable' )->andReturn( true );
		$variation2->shouldReceive( 'get_id' )->andReturn( $variation2_id );
		$variation2->shouldReceive( 'get_name' )->andReturn( 'Variant Blue' );
		$variation2->shouldReceive( 'get_permalink' )->andReturn( 'https://example.com/v102' );
		$variation2->shouldReceive( 'get_image_id' )->andReturn( 202 );
		$variation2->shouldReceive( 'get_description' )->andReturn( 'Variant 2 description' );
		$variation2->shouldReceive( 'get_price' )->andReturn( '12.00' );
		$variation2->shouldReceive( 'get_stock_status' )->andReturn( 'instock' );
		$variation2->shouldReceive( 'get_sku' )->andReturn( 'SKU-102' );
		$variation2->shouldReceive( 'get_sale_price' )->andReturn( '' );
		$variation2->shouldReceive( 'get_variation_attributes' )->andReturn( array() );

		when( 'wc_get_product' )->alias( function ( $id ) use ( $parent_id, $variation1_id, $variation2_id, $parent_product, $variation1, $variation2 ) {
			if ( $parent_id === $id ) {
				return $parent_product;
			}
			if ( $variation1_id === $id ) {
				return $variation1;
			}
			if ( $variation2_id === $id ) {
				return $variation2;
			}

			return false;
		} );

		when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => '{}',
		) );
		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		when( 'wp_remote_retrieve_body' )->justReturn( json_encode( array(
			'success' => false,
			'message' => 'data/products/1 image_link must be a valid URL',
		) ) );

		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with( $parent_product );
		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with( $parent_product, 'image_link must be a valid URL' );

		$captured_action = null;
		expectDone( 'woocommerce_paypal_payments_store_sync_ingestion_completed' )
			->once()
			->whenHappen( function ( array $payload ) use ( &$captured_action ) {
				$captured_action = $payload;
			} );

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			array( $parent_id ),
			$this->logger,
			$this->product_manager,
			$this->product_filter
		);

		$sync_job->execute();

		$this->assertSame( 'validation_errors', $captured_action['status'] );
		$this->assertSame( 1, $captured_action['pushed'] );
		$this->assertSame( 0, $captured_action['synced'] );
		$this->assertSame( 1, $captured_action['failed'] );
	}
}
