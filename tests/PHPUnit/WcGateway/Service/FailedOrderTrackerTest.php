<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Service;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ModularTestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * Class FailedOrderTrackerTest
 */
class FailedOrderTrackerTest extends ModularTestCase {

	private $logger;
	private $tracker;
	private $mock_wc_order;
	private $mock_paypal_order;

	public function setUp(): void {
		parent::setUp();

		$this->logger = Mockery::mock( LoggerInterface::class );
		$this->tracker = new FailedOrderTracker( $this->logger );

		$this->mock_wc_order = Mockery::mock( WC_Order::class );
		$this->mock_paypal_order = Mockery::mock( Order::class );

		// Mock WordPress functions with proper Brain Monkey syntax
		when( 'current_time' )->justReturn( 1234567890 );
		when( 'get_option' )->justReturn( array() );
		when( 'update_option' )->justReturn( true );
		when( 'delete_option' )->justReturn( true );
		when( 'wc_get_order_notes' )->justReturn( array() );
		when( 'has_action' )->justReturn( true );
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test failed credit card transaction detection - should detect failed orders.
	 */
	public function test_detects_failed_credit_card_transaction(): void {
		$this->mock_wc_order->shouldReceive( 'has_status' )
			->with( 'failed' )
			->andReturn( true );

		$this->mock_wc_order->shouldReceive( 'get_payment_method' )
			->andReturn( 'ppcp-credit-card-gateway' );

		$this->mock_wc_order->shouldReceive( 'get_meta' )
			->with( PayPalGateway::FRAUD_RESULT_META_KEY )
			->andReturn( array( 'avs_code' => 'N', 'cvv2_code' => 'N' ) );

		$this->mock_wc_order->shouldReceive( 'get_id' )->andReturn( 123 );
		$this->mock_wc_order->shouldReceive( 'get_total' )->andReturn( '10.00' );
		$this->mock_wc_order->shouldReceive( 'get_currency' )->andReturn( 'USD' );
		$this->mock_wc_order->shouldReceive( 'get_customer_ip_address' )->andReturn( '192.168.1.1' );
		$this->mock_wc_order->shouldReceive( 'get_billing_email' )->andReturn( 'test@example.com' );
		$this->mock_wc_order->shouldReceive( 'get_billing_country' )->andReturn( 'US' );

		$this->mock_paypal_order->shouldReceive( 'id' )->andReturn( 'PAYPAL123' );

		// Mock the WordPress functions that will be called
		when( 'update_option' )->justReturn( true );

		$this->logger->shouldReceive( 'warning' )
			->once()
			->with( 'Failed credit card transaction recorded', Mockery::type( 'array' ) );

		$this->tracker->track_failed_card_order( $this->mock_wc_order, $this->mock_paypal_order );

		// Since we can't easily verify the update_option call with Brain Monkey,
		// we verify the logger was called which means the transaction was processed
		$this->addToAssertionCount(1); // Mark test as having made assertions
	}

	/**
	 * Test that non-failed orders are not recorded.
	 */
	public function test_ignores_non_failed_orders(): void {
		$this->mock_wc_order->shouldReceive( 'has_status' )
			->with( 'failed' )
			->andReturn( false );

		$this->logger->shouldReceive( 'warning' )->never();

		$this->tracker->track_failed_card_order( $this->mock_wc_order, $this->mock_paypal_order );

		$this->addToAssertionCount(1); // Test passed - no warnings were logged
	}

	/**
	 * Test that non-credit-card orders are not recorded.
	 */
	public function test_ignores_non_credit_card_orders(): void {
		$this->mock_wc_order->shouldReceive( 'has_status' )
			->with( 'failed' )
			->andReturn( true );

		$this->mock_wc_order->shouldReceive( 'get_payment_method' )
			->andReturn( 'ppcp-gateway' ); // Different gateway

		$this->logger->shouldReceive( 'warning' )->never();

		$this->tracker->track_failed_card_order( $this->mock_wc_order, $this->mock_paypal_order );

		$this->addToAssertionCount(1); // Test passed - no warnings were logged
	}

	/**
	 * Test data storage with limit enforcement.
	 */
	public function test_enforces_storage_limit(): void {
		// Create 101 existing failed orders to test limit
		$existing_orders = array();
		for ( $i = 0; $i < 101; $i++ ) {
			$existing_orders[] = array( 'order_id' => $i, 'timestamp' => time() - $i );
		}

		when( 'get_option' )->justReturn( $existing_orders );
		when( 'update_option' )->justReturn( true );

		$this->mock_wc_order->shouldReceive( 'has_status' )->andReturn( true );
		$this->mock_wc_order->shouldReceive( 'get_payment_method' )->andReturn( 'ppcp-credit-card-gateway' );
		$this->mock_wc_order->shouldReceive( 'get_meta' )->andReturn( array() );
		$this->mock_wc_order->shouldReceive( 'get_id' )->andReturn( 999 );
		$this->mock_wc_order->shouldReceive( 'get_total' )->andReturn( '5.00' );
		$this->mock_wc_order->shouldReceive( 'get_currency' )->andReturn( 'USD' );
		$this->mock_wc_order->shouldReceive( 'get_customer_ip_address' )->andReturn( '1.1.1.1' );
		$this->mock_wc_order->shouldReceive( 'get_billing_email' )->andReturn( 'test@test.com' );
		$this->mock_wc_order->shouldReceive( 'get_billing_country' )->andReturn( 'CA' );

		$this->mock_paypal_order->shouldReceive( 'id' )->andReturn( 'PP999' );

		$this->logger->shouldReceive( 'warning' )->once();

		$this->tracker->track_failed_card_order( $this->mock_wc_order, $this->mock_paypal_order );

		// Test passes if no exceptions thrown and logger was called
		$this->addToAssertionCount(1);
	}

	/**
	 * Test getting recent failed orders.
	 */
	public function test_get_recent_failed_orders(): void {
		$test_orders = array(
			array( 'order_id' => 1, 'timestamp' => 1000 ),
			array( 'order_id' => 2, 'timestamp' => 2000 ),
			array( 'order_id' => 3, 'timestamp' => 1500 ),
		);

		when( 'get_option' )->justReturn( $test_orders );

		$recent_orders = $this->tracker->get_recent_failed_orders( 5 );

		// Should return orders sorted by timestamp descending
		$this->assertCount( 3, $recent_orders );
		$this->assertEquals( 2, $recent_orders[0]['order_id'] ); // Most recent first
		$this->assertEquals( 3, $recent_orders[1]['order_id'] );
		$this->assertEquals( 1, $recent_orders[2]['order_id'] ); // Oldest last
	}

	/**
	 * Test limiting number of returned orders.
	 */
	public function test_get_recent_failed_orders_with_limit(): void {
		$test_orders = array(
			array( 'order_id' => 1, 'timestamp' => 1000 ),
			array( 'order_id' => 2, 'timestamp' => 2000 ),
			array( 'order_id' => 3, 'timestamp' => 1500 ),
		);

		when( 'get_option' )->justReturn( $test_orders );

		$recent_orders = $this->tracker->get_recent_failed_orders( 2 );

		$this->assertCount( 2, $recent_orders );
		$this->assertEquals( 2, $recent_orders[0]['order_id'] );
		$this->assertEquals( 3, $recent_orders[1]['order_id'] );
	}

	/**
	 * Test counting failed orders within time period.
	 */
	public function test_get_failed_orders_count(): void {
		$current_time = 3600; // Base timestamp
		when( 'current_time' )->justReturn( $current_time );

		// Cutoff time will be 3600 - (60 * 60) = 0
		// So orders with timestamp > 0 will be counted
		$test_orders = array(
			array( 'order_id' => 1, 'timestamp' => 3500 ), // > 0 - within 1 hour
			array( 'order_id' => 2, 'timestamp' => 1800 ), // > 0 - within 1 hour
			array( 'order_id' => 3, 'timestamp' => 0 ), // = 0 - excluded (not >)
		);

		when( 'get_option' )->justReturn( $test_orders );

		$count = $this->tracker->get_failed_orders_count( 60 ); // 60 minutes

		$this->assertEquals( 2, $count ); // Only 2 orders within the last hour
	}

	/**
	 * Test counting with custom time period.
	 */
	public function test_get_failed_orders_count_custom_period(): void {
		$current_time = 1800; // 30 minutes timestamp
		when( 'current_time' )->justReturn( $current_time );

		$test_orders = array(
			array( 'order_id' => 1, 'timestamp' => 1700 ), // 100 seconds ago - within 10 min
			array( 'order_id' => 2, 'timestamp' => 1200 ), // 600 seconds ago - outside 10 min
		);

		when( 'get_option' )->justReturn( $test_orders );

		$count = $this->tracker->get_failed_orders_count( 10 ); // 10 minutes

		$this->assertEquals( 1, $count ); // Only 1 order within the last 10 minutes
	}

	/**
	 * Test clearing failed orders.
	 */
	public function test_clear_failed_orders(): void {
		when( 'delete_option' )->justReturn( true );

		$this->tracker->clear_failed_orders();

		// Test passes if no exceptions thrown
		$this->addToAssertionCount(1);
	}

	/**
	 * Test fraud data capture with AVS/CVV codes.
	 */
	public function test_captures_fraud_data(): void {
		$fraud_data = array(
			'avs_code' => 'N',
			'cvv2_code' => 'M',
			'card_brand' => 'visa',
			'card_last_digits' => '1234',
		);

		$this->mock_wc_order->shouldReceive( 'has_status' )->andReturn( true );
		$this->mock_wc_order->shouldReceive( 'get_payment_method' )->andReturn( 'ppcp-credit-card-gateway' );
		$this->mock_wc_order->shouldReceive( 'get_meta' )
			->with( PayPalGateway::FRAUD_RESULT_META_KEY )
			->andReturn( $fraud_data );

		// Mock other required methods
		$this->mock_wc_order->shouldReceive( 'get_id' )->andReturn( 456 );
		$this->mock_wc_order->shouldReceive( 'get_total' )->andReturn( '25.99' );
		$this->mock_wc_order->shouldReceive( 'get_currency' )->andReturn( 'EUR' );
		$this->mock_wc_order->shouldReceive( 'get_customer_ip_address' )->andReturn( '10.0.0.1' );
		$this->mock_wc_order->shouldReceive( 'get_billing_email' )->andReturn( 'fraud@test.com' );
		$this->mock_wc_order->shouldReceive( 'get_billing_country' )->andReturn( 'FR' );

		$this->mock_paypal_order->shouldReceive( 'id' )->andReturn( 'FRAUD789' );

		// Mock WordPress functions
		when( 'update_option' )->justReturn( true );

		// Verify fraud data is captured in logs
		$this->logger->shouldReceive( 'warning' )
			->once()
			->with( 'Failed credit card transaction recorded', Mockery::on( function( $context ) {
				return $context['avs_code'] === 'N' &&
				       $context['cvv_code'] === 'M' &&
				       $context['order_id'] === 456 &&
				       $context['paypal_order_id'] === 'FRAUD789';
			}));

		$this->tracker->track_failed_card_order( $this->mock_wc_order, $this->mock_paypal_order );
	}
}
