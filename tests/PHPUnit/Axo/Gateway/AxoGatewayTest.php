<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo\Gateway;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use function Brain\Monkey\Functions\when;

class AxoGatewayTestable extends AxoGateway
{
	public function update_option( $key, $value = '' ): bool
	{
		return true;
	}

	public function process_3ds_return_exposed( WC_Order $wc_order, string $token ): array
	{
		return $this->process_3ds_return( $wc_order, $token );
	}
}

/**
 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway
 */
class AxoGatewayTest extends TestCase
{
	private CardPaymentsConfiguration $dcc_configuration;
	private OrderEndpoint $order_endpoint;
	private OrderProcessor $order_processor;
	private LoggerInterface $logger;
	private AxoGatewayTestable $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->dcc_configuration = Mockery::mock( CardPaymentsConfiguration::class );
		$this->dcc_configuration->shouldReceive( 'gateway_title' )->andReturn( 'Fastlane Cards' );
		$this->dcc_configuration->shouldReceive( 'use_fastlane' )->andReturn( false );

		$this->order_endpoint  = Mockery::mock( OrderEndpoint::class );
		$this->order_processor = Mockery::mock( OrderProcessor::class );
		$this->logger          = Mockery::mock( LoggerInterface::class );

		$woocommerce       = Mockery::mock( 'WooCommerce' );
		$cart              = Mockery::mock( 'stdClass' );
		$cart->shouldReceive( 'empty_cart' );
		when( 'WC' )->justReturn( $woocommerce );
		$woocommerce->cart = $cart;

		$this->sut = new AxoGatewayTestable(
			$this->dcc_configuration,
			Mockery::mock( SessionHandler::class ),
			$this->order_processor,
			[],
			$this->order_endpoint,
			Mockery::mock( PurchaseUnitFactory::class ),
			Mockery::mock( ShippingPreferenceFactory::class ),
			Mockery::mock( TransactionUrlProvider::class ),
			Mockery::mock( Environment::class ),
			$this->logger,
			Mockery::mock( ExperienceContextBuilder::class ),
			Mockery::mock( SettingsModel::class )
		);
	}

	/**
	 * @param string $custom_id
	 * @param string $amount_value
	 * @param string $currency_code
	 * @return Order
	 */
	private function create_completed_paypal_order(
		string $custom_id,
		string $amount_value,
		string $currency_code
	): Order {
		$order_status = Mockery::mock( OrderStatus::class );
		$order_status->shouldReceive( 'is' )
			->with( OrderStatus::COMPLETED )
			->andReturn( true );

		$amount = Mockery::mock( Amount::class );
		$amount->shouldReceive( 'value_str' )->andReturn( $amount_value );
		$amount->shouldReceive( 'currency_code' )->andReturn( $currency_code );

		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'custom_id' )->andReturn( $custom_id );
		$purchase_unit->shouldReceive( 'amount' )->andReturn( $amount );

		$paypal_order = Mockery::mock( Order::class );
		$paypal_order->shouldReceive( 'status' )->andReturn( $order_status );
		$paypal_order->shouldReceive( 'purchase_units' )->andReturn( [ $purchase_unit ] );

		return $paypal_order;
	}

	/**
	 * @scenario When process_3ds_return() is called with a valid PayPal token whose
	 *           custom_id does NOT match the WC order id, the method returns a WP_Error
	 *           and the WC order remains unpaid.
	 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway::process_3ds_return
	 */
	public function test_returns_failure_when_custom_id_does_not_match_wc_order_id(): void
	{
		// Arrange
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );
		$wc_order->shouldReceive( 'get_total' )->andReturn( '100.00' );
		$wc_order->shouldReceive( 'get_currency' )->andReturn( 'USD' );

		$paypal_order = $this->create_completed_paypal_order( '999', '100.00', 'USD' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'attacker-token' )->andReturn( $paypal_order );
		$this->order_processor->shouldReceive( 'process_captured_and_authorized' )->never();
		$this->logger->shouldReceive( 'error' )->once();

		// When
		$result = $this->sut->process_3ds_return_exposed( $wc_order, 'attacker-token' );

		// Then
		$this->assertSame( 'failure', $result['result'] );
	}

	/**
	 * @scenario When process_3ds_return() is called with a PayPal token whose custom_id
	 *           matches the WC order id but whose captured amount is significantly
	 *           different from the WC order total, the order is still marked paid.
	 *           Amount comparison was deliberately removed: once custom_id matches the
	 *           PayPal order is proven to belong to this WC order, and amount differences
	 *           (e.g. inclusive-tax rounding, promotional adjustments) must not block
	 *           payment. This test guards against re-introducing an amount check.
	 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway::process_3ds_return
	 */
	public function test_allows_payment_when_paypal_amount_differs_from_wc_total(): void
	{
		// Arrange — WC total 50.00, PayPal captured 10.00 (large gap); custom_id matches
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );

		$paypal_order = $this->create_completed_paypal_order( '1', '10.00', 'USD' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'valid-token' )->andReturn( $paypal_order );
		$this->order_processor
			->shouldReceive( 'process_captured_and_authorized' )
			->once()
			->with( $wc_order, $paypal_order );

		when( 'apply_filters' )->justReturn( true );

		// When
		$result = $this->sut->process_3ds_return_exposed( $wc_order, 'valid-token' );

		// Then
		$this->assertSame( 'success', $result['result'] );
	}

	/**
	 * @scenario When process_3ds_return() is called with a PayPal token whose custom_id
	 *           matches the WC order id, the order is marked paid via
	 *           OrderProcessor::process_captured_and_authorized(). The custom_id binding
	 *           is the sole validation criterion; amount and currency are not checked.
	 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway::process_3ds_return
	 */
	public function test_calls_order_processor_when_custom_id_matches(): void
	{
		// Arrange
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );
		$wc_order->shouldReceive( 'get_total' )->andReturn( '100.00' );
		$wc_order->shouldReceive( 'get_currency' )->andReturn( 'USD' );

		$paypal_order = $this->create_completed_paypal_order( '1', '100.00', 'USD' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'valid-token' )->andReturn( $paypal_order );
		$this->order_processor
			->shouldReceive( 'process_captured_and_authorized' )
			->once()
			->with( $wc_order, $paypal_order );

		when( 'apply_filters' )->justReturn( true );

		// When
		$result = $this->sut->process_3ds_return_exposed( $wc_order, 'valid-token' );

		// Then
		$this->assertSame( 'success', $result['result'] );
	}

	/**
	 * @return Order
	 */
	private function create_completed_paypal_order_without_purchase_units(): Order {
		$order_status = Mockery::mock( OrderStatus::class );
		$order_status->shouldReceive( 'is' )
			->with( OrderStatus::COMPLETED )
			->andReturn( true );

		$paypal_order = Mockery::mock( Order::class );
		$paypal_order->shouldReceive( 'status' )->andReturn( $order_status );
		$paypal_order->shouldReceive( 'purchase_units' )->andReturn( [] );

		return $paypal_order;
	}

	/**
	 * GIVEN a PayPal order that is COMPLETED but has no purchase units
	 * WHEN process_3ds_return() is called
	 * THEN the method returns a failure result
	 * AND the order processor is never called
	 * AND an error is logged containing the WC order ID
	 *
	 * @scenario When process_3ds_return() is called and the COMPLETED PayPal order
	 *           contains no purchase units, the method must return a failure result
	 *           without invoking the order processor, and must log an error.
	 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway::process_3ds_return
	 */
	public function test_returns_failure_when_paypal_order_has_no_purchase_units(): void {
		// Arrange
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 42 );

		$paypal_order = $this->create_completed_paypal_order_without_purchase_units();
		$this->order_endpoint->shouldReceive( 'order' )->with( 'some-token' )->andReturn( $paypal_order );
		$this->order_processor->shouldReceive( 'process_captured_and_authorized' )->never();
		$this->logger->shouldReceive( 'error' )->once();

		// When
		$result = $this->sut->process_3ds_return_exposed( $wc_order, 'some-token' );

		// Then
		$this->assertSame( 'failure', $result['result'] );
	}

	/**
	 * @scenario All validation rejections are logged with details sufficient for an
	 *           auditor to identify the attacker's token and the attempted fraud.
	 * @covers \WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway::process_3ds_return
	 */
	public function test_logs_rejection_details_when_validation_fails(): void
	{
		// Arrange
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 1 );
		$wc_order->shouldReceive( 'get_total' )->andReturn( '100.00' );
		$wc_order->shouldReceive( 'get_currency' )->andReturn( 'USD' );

		$paypal_order = $this->create_completed_paypal_order( '999', '100.00', 'USD' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'attacker-token' )->andReturn( $paypal_order );
		$this->order_processor->shouldReceive( 'process_captured_and_authorized' )->never();

		$this->logger
			->shouldReceive( 'error' )
			->once()
			->with(
				Mockery::on(
					static function ( string $message ): bool {
						return strpos( $message, '999' ) !== false
							&& strpos( $message, '1' ) !== false
							&& strpos( $message, 'attacker-token' ) !== false;
					}
				)
			);

		// When
		$this->sut->process_3ds_return_exposed( $wc_order, 'attacker-token' );

		// Then — Mockery verifies logger->error() call in tearDown(); count it as an assertion
		$this->addToAssertionCount( 1 );
	}
}
