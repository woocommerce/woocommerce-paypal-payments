<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\TestCase;
use Psr\Log\LoggerInterface;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use Mockery;
use RuntimeException;
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
	 * AND no exception is thrown, so Action Scheduler does not count the run as failed
	 * AND the ingestion-completed action reports status "api_error" with every product failed
	 */
	public function test_execute_reports_api_error_without_throwing_when_wp_error_occurs(): void {
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

		$captured_action = null;
		expectDone( 'woocommerce_paypal_payments_store_sync_ingestion_completed' )
			->once()
			->whenHappen( function ( array $payload ) use ( &$captured_action ) {
				$captured_action = $payload;
			} );

		$sync_job->execute();

		$this->assertSame( 'api_error', $captured_action['status'] );
		$this->assertSame( 3, $captured_action['pushed'] );
		$this->assertSame( 0, $captured_action['synced'] );
		$this->assertSame( 3, $captured_action['failed'] );
		$this->assertSame( 'Connection timeout', $captured_action['error_message'] );
		$this->assertSame( array(), $captured_action['failed_items'] );
	}

	/**
	 * GIVEN a sync job with valid products
	 * WHEN the API returns an HTTP error status that does not fail the job
	 * THEN no products are marked as processed, so they are retried on the next run
	 * AND no exception is thrown, so Action Scheduler does not count the run as failed
	 * AND the ingestion-completed action reports status "api_error" with every product failed
	 *
	 * @dataProvider http_error_provider
	 */
	public function test_execute_reports_api_error_without_throwing_when_api_returns_http_error(
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

		$captured_action = null;
		expectDone( 'woocommerce_paypal_payments_store_sync_ingestion_completed' )
			->once()
			->whenHappen( function ( array $payload ) use ( &$captured_action ) {
				$captured_action = $payload;
			} );

		$sync_job->execute();

		$this->assertSame( 'api_error', $captured_action['status'] );
		$this->assertSame( 3, $captured_action['pushed'] );
		$this->assertSame( 0, $captured_action['synced'] );
		$this->assertSame( 3, $captured_action['failed'] );
		$this->assertSame( $expected_error, $captured_action['error_message'] );
		$this->assertSame( array(), $captured_action['failed_items'] );
	}

	/**
	 * GIVEN a sync job with valid products
	 * WHEN the API answers HTTP 401 or a 5xx status
	 * THEN no products are marked as processed, so they are retried on the next run
	 * AND a RuntimeException is thrown, so Action Scheduler records the run as failed
	 * AND the ingestion-completed action reports status "api_error" with every product failed
	 *
	 * @dataProvider failing_http_error_provider
	 */
	public function test_execute_reports_api_error_and_throws_when_api_returns_failing_http_error(
		int $status_code,
		string $response_body,
		string $expected_error
	): void {
		$this->create_products_and_stub_wc_get_product( $this->product_ids );
		$this->stub_http_error_response( $status_code, $response_body );

		$this->product_filter->shouldNotReceive( 'mark_processed' );

		$sync_job = new SyncJob(
			$this->api_endpoint,
			'https://example.com',
			$this->product_ids,
			$this->logger,
			$this->product_manager,
			$this->product_filter
		);

		$captured_action = null;
		expectDone( 'woocommerce_paypal_payments_store_sync_ingestion_completed' )
			->once()
			->whenHappen( function ( array $payload ) use ( &$captured_action ) {
				$captured_action = $payload;
			} );

		$thrown = null;
		try {
			$sync_job->execute();
		} catch ( RuntimeException $exception ) {
			$thrown = $exception;
		}

		$this->assertInstanceOf( RuntimeException::class, $thrown );
		$this->assertSame( 'api_error', $captured_action['status'] );
		$this->assertSame( 3, $captured_action['pushed'] );
		$this->assertSame( 0, $captured_action['synced'] );
		$this->assertSame( 3, $captured_action['failed'] );
		$this->assertSame( $expected_error, $captured_action['error_message'] );
		$this->assertSame( array(), $captured_action['failed_items'] );
	}

	/**
	 * Provides HTTP statuses that make the job fail.
	 */
	public function failing_http_error_provider(): array {
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
			'unauthorized (401)'          => array(
				401,
				'Unauthorized',
				'HTTP 401: Unauthorized',
			),
		);
	}

	/**
	 * Provides HTTP error scenarios that do not make the job fail.
	 */
	public function http_error_provider(): array {
		return array(
			'forbidden (403)'             => array(
				403,
				'Forbidden',
				'HTTP 403: Forbidden',
			),
			'not found (404)'             => array(
				404,
				'Not Found',
				'HTTP 404: Not Found',
			),
			'payload too large (413)'     => array(
				413,
				'Payload Too Large',
				'HTTP 413: Payload Too Large',
			),
			'redirect (301)'              => array(
				301,
				'Moved Permanently',
				'HTTP 301: Moved Permanently',
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
		$this->assertSame( array(), $captured_action['failed_items'] );
	}

	/**
	 * GIVEN a batch of variable products that have no purchasable variations, so no payload
	 * entry exists for any of them
	 * WHEN execute is called
	 * THEN every product of the batch is marked processed, so it does not block the next batches
	 * AND no API request is sent
	 * AND the ingestion-completed action reports an "empty" batch
	 */
	public function test_execute_marks_all_products_processed_when_batch_has_no_payload_entries(): void {
		$variable_without_children = Mockery::mock( WC_Product_Variable::class . ', ' . WC_Product::class );
		$variable_without_children->allows( 'get_type' )->andReturn( 'variable' );
		$variable_without_children->allows( 'is_type' )->with( 'variable' )->andReturn( true );
		$variable_without_children->allows( 'get_id' )->andReturn( 100 );
		$variable_without_children->allows( 'get_children' )->andReturn( array() );

		$variation = Mockery::mock( WC_Product_Variation::class );
		$variation->allows( 'is_purchasable' )->andReturn( false );

		$variable_with_unavailable_variation = Mockery::mock( WC_Product_Variable::class . ', ' . WC_Product::class );
		$variable_with_unavailable_variation->allows( 'get_type' )->andReturn( 'variable' );
		$variable_with_unavailable_variation->allows( 'is_type' )->with( 'variable' )->andReturn( true );
		$variable_with_unavailable_variation->allows( 'get_id' )->andReturn( 200 );
		$variable_with_unavailable_variation->allows( 'get_children' )->andReturn( array( 201 ) );

		when( 'wc_get_product' )->alias(
			static function ( $id ) use ( $variable_without_children, $variable_with_unavailable_variation, $variation ) {
				return array(
					100 => $variable_without_children,
					200 => $variable_with_unavailable_variation,
					201 => $variation,
				)[ $id ] ?? false;
			}
		);

		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with( $variable_without_children );
		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with( $variable_with_unavailable_variation );

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
			array( 100, 200 ),
			$this->logger,
			$this->product_manager,
			$this->product_filter
		);

		$sync_job->execute();

		$this->assertSame( 'empty', $captured_action['status'] );
		$this->assertSame( 0, $captured_action['pushed'] );
		$this->assertSame( array(), $captured_action['failed_items'] );
	}

	/**
	 * GIVEN a single variable product whose two variations expand into two payload entries,
	 * both sharing the parent's item_group_id
	 * WHEN the API reports a validation error on the second payload entry (index 1)
	 * THEN the error is attributed to the parent product, resolved via item_group_id rather
	 * than by indexing into the original product-id list
	 * AND the ingestion-completed action reports one failure with status "validation_errors"
	 * AND the failed item names the parent as product_id and the failing variation as variant_id
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
		$parent_product->shouldReceive( 'get_short_description' )->andReturn( '' );

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
		$this->assertSame(
			array(
				array(
					'product_id' => $parent_id,
					'variant_id' => $variation2_id,
					'error'      => 'image_link must be a valid URL',
				),
			),
			$captured_action['failed_items']
		);
	}

	/**
	 * GIVEN a single variable product whose two variations expand into two payload entries
	 * WHEN the API reports a validation error for each of the two entries
	 * THEN the ingestion-completed action lists two failed items, one per variation
	 * AND both items name the same parent product
	 * AND the failure count is 1, because both errors belong to one product
	 */
	public function test_execute_counts_one_failed_product_when_two_variations_of_it_fail(): void {
		$parent_id     = 100;
		$variation1_id = 101;
		$variation2_id = 102;

		$parent_product = Mockery::mock( WC_Product_Variable::class . ', ' . WC_Product::class );
		$parent_product->allows( 'get_type' )->andReturn( 'variable' );
		$parent_product->allows( 'is_type' )->with( 'variable' )->andReturn( true );
		$parent_product->allows( 'get_children' )->andReturn( array( $variation1_id, $variation2_id ) );
		$parent_product->allows( 'get_id' )->andReturn( $parent_id );
		$parent_product->allows( 'get_image_id' )->andReturn( 300 );
		$parent_product->allows( 'get_description' )->andReturn( 'Parent description' );
		$parent_product->allows( 'get_short_description' )->andReturn( '' );

		$variations = array();
		foreach ( array( $variation1_id, $variation2_id ) as $variation_id ) {
			$variation = Mockery::mock( WC_Product_Variation::class );
			$variation->allows( 'is_purchasable' )->andReturn( true );
			$variation->allows( 'get_id' )->andReturn( $variation_id );
			$variation->allows( 'get_name' )->andReturn( "Variant {$variation_id}" );
			$variation->allows( 'get_permalink' )->andReturn( "https://example.com/v{$variation_id}" );
			$variation->allows( 'get_image_id' )->andReturn( 202 );
			$variation->allows( 'get_description' )->andReturn( "Variant {$variation_id} description" );
			$variation->allows( 'get_price' )->andReturn( '10.00' );
			$variation->allows( 'get_stock_status' )->andReturn( 'instock' );
			$variation->allows( 'get_sku' )->andReturn( "SKU-{$variation_id}" );
			$variation->allows( 'get_sale_price' )->andReturn( '' );
			$variation->allows( 'get_variation_attributes' )->andReturn( array() );

			$variations[ $variation_id ] = $variation;
		}

		when( 'wc_get_product' )->alias(
			static function ( $id ) use ( $parent_id, $parent_product, $variations ) {
				return $parent_id === $id ? $parent_product : ( $variations[ $id ] ?? false );
			}
		);

		$this->stub_http_error_response(
			422,
			json_encode(
				array(
					'success' => false,
					'error'   => 'VALIDATION_ERROR',
					'message' => 'Validation failed: data/products/0/title must pass "isNotEmpty" keyword validation, data/products/1/description must pass "isNotEmpty" keyword validation',
				)
			)
		);

		$this->product_filter->allows( 'mark_processed' );

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
		$this->assertSame( 1, $captured_action['failed'] );
		$this->assertSame(
			array(
				array(
					'product_id' => $parent_id,
					'variant_id' => $variation1_id,
					'error'      => 'title must pass "isNotEmpty" keyword validation',
				),
				array(
					'product_id' => $parent_id,
					'variant_id' => $variation2_id,
					'error'      => 'description must pass "isNotEmpty" keyword validation',
				),
			),
			$captured_action['failed_items']
		);
	}

	/**
	 * GIVEN a batch of two products
	 * WHEN the API answers HTTP 400 or 422 with a validation error naming the second product
	 * THEN no exception is thrown, so Action Scheduler does not retry the batch
	 * AND the named product is marked processed with its validation error
	 * AND the ingestion-completed action reports one failure with status "validation_errors"
	 * AND the failed item names the product as both product_id and variant_id
	 *
	 * @dataProvider validation_status_provider
	 */
	public function test_execute_marks_named_product_with_validation_error_on_http_validation_status( int $status_code ): void {
		$product_one = $this->create_product_stub( 1, 'Product 1', '10' );
		$product_two = $this->create_product_stub( 2, 'Product 2', '20' );

		when( 'wc_get_product' )->alias( function ( $id ) use ( $product_one, $product_two ) {
			return array( 1 => $product_one, 2 => $product_two )[ $id ] ?? false;
		} );

		$this->stub_http_error_response(
			$status_code,
			json_encode(
				array(
					'success' => false,
					'error'   => 'VALIDATION_ERROR',
					'message' => 'Validation failed: data/products/1/description must pass "isNotEmpty" keyword validation',
				)
			)
		);

		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with( $product_one );
		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with( $product_two );
		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with(
				$product_two,
				Mockery::on( static fn( $error ) => is_string( $error ) && false !== strpos( $error, 'isNotEmpty' ) )
			);

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

		$this->assertSame( 'validation_errors', $captured_action['status'] );
		$this->assertSame( 2, $captured_action['pushed'] );
		$this->assertSame( 1, $captured_action['synced'] );
		$this->assertSame( 1, $captured_action['failed'] );
		$this->assertSame(
			array(
				array(
					'product_id' => 2,
					'variant_id' => 2,
					'error'      => 'description must pass "isNotEmpty" keyword validation',
				),
			),
			$captured_action['failed_items']
		);
	}

	/**
	 * GIVEN a batch of two products
	 * WHEN the API rejects the request with a message that names no product
	 * THEN every product is marked processed without a validation error
	 * AND the ingestion-completed action reports status "validation_errors", not "success"
	 * AND the failed items list is empty
	 *
	 * @dataProvider unattributed_rejection_provider
	 */
	public function test_execute_reports_validation_errors_when_rejection_names_no_product( int $status_code ): void {
		$product_one = $this->create_product_stub( 1, 'Product 1', '10' );
		$product_two = $this->create_product_stub( 2, 'Product 2', '20' );

		when( 'wc_get_product' )->alias( function ( $id ) use ( $product_one, $product_two ) {
			return array( 1 => $product_one, 2 => $product_two )[ $id ] ?? false;
		} );

		$this->stub_http_error_response(
			$status_code,
			json_encode(
				array(
					'success' => false,
					'message' => 'data/merchant_url must match format "uri"',
				)
			)
		);

		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with( $product_one );
		$this->product_filter->shouldReceive( 'mark_processed' )
			->once()
			->with( $product_two );

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

		$this->assertSame( 'validation_errors', $captured_action['status'] );
		$this->assertSame( array(), $captured_action['failed_items'] );
	}

	/**
	 * Provides HTTP statuses that can carry a rejection without a product reference.
	 */
	public function unattributed_rejection_provider(): array {
		return array(
			'bad request (400)'                => array( 400 ),
			'unprocessable entity (422)'       => array( 422 ),
			'success status with failure flag' => array( 200 ),
		);
	}

	/**
	 * Provides HTTP statuses the API uses to report per-product validation errors.
	 */
	public function validation_status_provider(): array {
		return array(
			'bad request (400)'          => array( 400 ),
			'unprocessable entity (422)' => array( 422 ),
		);
	}
}
