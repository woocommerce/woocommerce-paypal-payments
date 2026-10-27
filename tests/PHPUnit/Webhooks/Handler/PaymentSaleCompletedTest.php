<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerReceivableBreakdown;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExchangeRateFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\MoneyFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayeeFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PlatformFeeFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\SellerReceivableBreakdownFactory;
use WooCommerce\PayPalCommerce\PayPalSubscriptions\RenewalHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WP_REST_Request;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentSaleCompleted
 */
class PaymentSaleCompletedTest extends TestCase
{
	private PaymentSaleCompleted $sut;

	/** @var RenewalHandler&\Mockery\MockInterface */
	private $renewal_handler;

	public function setUp(): void
	{
		parent::setUp();

		$money_factory         = new MoneyFactory();
		$breakdown_factory     = new SellerReceivableBreakdownFactory(
			$money_factory,
			new ExchangeRateFactory(),
			new PlatformFeeFactory( $money_factory, new PayeeFactory() )
		);
		$logger                = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();
		$this->renewal_handler = Mockery::mock( RenewalHandler::class );
		$this->sut             = new PaymentSaleCompleted( $logger, $this->renewal_handler, $breakdown_factory );
	}

	/**
	 * GIVEN a completed sale of 77.00 USD with a 3.18 USD PayPal fee for a known subscription
	 * WHEN the webhook is handled
	 * THEN the renewal handler receives the transaction id and a breakdown with gross 77.00, fee 3.18 and net 73.82
	 */
	public function test_sale_with_fee_passes_derived_breakdown_to_renewal_handler(): void
	{
		$subscriptions = array( new \stdClass() );
		when( 'wcs_get_subscriptions' )->justReturn( $subscriptions );
		$received = $this->capture_process_args();

		$response = $this->sut->handle_request( $this->make_request( $this->sale_resource( '77.00', 'USD', '3.18', 'USD' ) ) );

		$this->assertTrue( $response->get_data()['success'] );
		$this->assertSame( $subscriptions, $received['subscriptions'] );
		$this->assertSame( 'SALE-TX-1', $received['transaction_id'] );
		$this->assertInstanceOf( SellerReceivableBreakdown::class, $received['breakdown'] );
		$this->assertSame(
			array(
				'gross_amount' => array( 'currency_code' => 'USD', 'value' => '77.00' ),
				'paypal_fee'   => array( 'currency_code' => 'USD', 'value' => '3.18' ),
				'net_amount'   => array( 'currency_code' => 'USD', 'value' => '73.82' ),
			),
			$received['breakdown']->to_array()
		);
	}

	/**
	 * GIVEN a completed sale in a zero-decimal currency (1000 JPY with a 40 JPY fee)
	 * WHEN the webhook is handled
	 * THEN the net amount stays a whole number of 960 JPY
	 */
	public function test_zero_decimal_currency_sale_yields_whole_net_amount(): void
	{
		when( 'wcs_get_subscriptions' )->justReturn( array( new \stdClass() ) );
		$received = $this->capture_process_args();

		$this->sut->handle_request( $this->make_request( $this->sale_resource( '1000', 'JPY', '40', 'JPY' ) ) );

		$this->assertSame(
			array(
				'gross_amount' => array( 'currency_code' => 'JPY', 'value' => '1000' ),
				'paypal_fee'   => array( 'currency_code' => 'JPY', 'value' => '40' ),
				'net_amount'   => array( 'currency_code' => 'JPY', 'value' => '960' ),
			),
			$received['breakdown']->to_array()
		);
	}

	/**
	 * GIVEN a completed sale whose amount or fee data is missing or inconsistent
	 * WHEN the webhook is handled
	 * THEN the renewal handler still receives the transaction id, but no breakdown
	 *
	 * @dataProvider unusable_sale_provider
	 */
	public function test_unusable_sale_data_is_processed_without_breakdown( array $resource ): void
	{
		when( 'wcs_get_subscriptions' )->justReturn( array( new \stdClass() ) );
		$received = $this->capture_process_args();

		$response = $this->sut->handle_request( $this->make_request( $resource ) );

		$this->assertTrue( $response->get_data()['success'] );
		$this->assertSame( 'SALE-TX-1', $received['transaction_id'] );
		$this->assertNull( $received['breakdown'] );
	}

	public function unusable_sale_provider(): array
	{
		$without_fee = $this->sale_resource( '77.00', 'USD', '3.18', 'USD' );
		unset( $without_fee['transaction_fee'] );

		return array(
			'fee is missing'              => array( $without_fee ),
			'total is not numeric'        => array( $this->sale_resource( 'abc', 'USD', '3.18', 'USD' ) ),
			'fee is not numeric'          => array( $this->sale_resource( '77.00', 'USD', 'n/a', 'USD' ) ),
			'currency is empty'           => array( $this->sale_resource( '77.00', '', '3.18', '' ) ),
			'fee currency differs'        => array( $this->sale_resource( '77.00', 'USD', '3.18', 'EUR' ) ),
		);
	}

	/**
	 * GIVEN a completed sale that matches no subscription
	 * WHEN the webhook is handled
	 * THEN nothing is processed and the response is a success
	 */
	public function test_sale_without_matching_subscriptions_is_not_processed(): void
	{
		when( 'wcs_get_subscriptions' )->justReturn( array() );
		$this->renewal_handler->shouldNotReceive( 'process' );

		$response = $this->sut->handle_request( $this->make_request( $this->sale_resource( '77.00', 'USD', '3.18', 'USD' ) ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * GIVEN a completed sale without a billing agreement id
	 * WHEN the webhook is handled
	 * THEN a failure response is returned and nothing is processed
	 */
	public function test_sale_without_billing_agreement_id_fails(): void
	{
		when( 'wcs_get_subscriptions' )->justReturn( array( new \stdClass() ) );
		$this->renewal_handler->shouldNotReceive( 'process' );
		$resource = $this->sale_resource( '77.00', 'USD', '3.18', 'USD' );
		unset( $resource['billing_agreement_id'] );

		$response = $this->sut->handle_request( $this->make_request( $resource ) );

		$this->assertFalse( $response->get_data()['success'] );
	}

	/**
	 * Records the arguments the renewal handler is called with.
	 *
	 * @return \ArrayObject Filled in when process() is called.
	 */
	private function capture_process_args(): \ArrayObject
	{
		$received = new \ArrayObject();
		$this->renewal_handler->shouldReceive( 'process' )
			->once()
			->andReturnUsing(
				static function ( array $subscriptions, string $transaction_id, ?SellerReceivableBreakdown $breakdown = null ) use ( $received ): void {
					$received['subscriptions']  = $subscriptions;
					$received['transaction_id'] = $transaction_id;
					$received['breakdown']      = $breakdown;
				}
			);

		return $received;
	}

	private function sale_resource( string $total, string $currency, string $fee, string $fee_currency ): array
	{
		return array(
			'id'                   => 'SALE-TX-1',
			'billing_agreement_id' => 'I-AGREEMENT-1',
			'amount'               => array(
				'total'    => $total,
				'currency' => $currency,
			),
			'transaction_fee'      => array(
				'value'    => $fee,
				'currency' => $fee_currency,
			),
		);
	}

	private function make_request( array $resource ): WP_REST_Request
	{
		/** @var WP_REST_Request&\Mockery\MockInterface $request */
		$request = Mockery::mock( 'WP_REST_Request, ArrayAccess' );
		$request->allows( 'offsetExists' )->andReturn( true );
		$request->allows( 'offsetGet' )->with( 'resource' )->andReturn( $resource );

		return $request;
	}
}
