<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Subscription;
use WooCommerce\PayPalCommerce\PayPalSubscriptions\RenewalHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WP_REST_Request;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentSaleCompleted
 */
class PaymentSaleCompletedTest extends TestCase
{
	/** @var LoggerInterface&\Mockery\MockInterface */
	private $logger;

	/** @var RenewalHandler&\Mockery\MockInterface */
	private $renewal_handler;

	private PaymentSaleCompleted $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->logger          = Mockery::mock( LoggerInterface::class );
		$this->renewal_handler = Mockery::mock( RenewalHandler::class );
		$this->sut             = new PaymentSaleCompleted( $this->logger, $this->renewal_handler );

		when( 'wc_clean' )->returnArg();
		when( 'wp_unslash' )->returnArg();
	}

	/**
	 * @scenario When a PAYMENT.SALE.COMPLETED webhook arrives whose billing_agreement_id matches no WC subscription, the handler logs a warning, does NOT invoke the renewal handler, and still returns a 2xx response to PayPal.
	 */
	public function test_logs_warning_and_skips_processing_when_no_subscription_matches(): void
	{
		// Arrange
		when( 'wcs_get_subscriptions' )->justReturn( array() );
		$this->logger->allows( 'info' );
		$this->logger->expects( 'warning' )->once();
		$this->renewal_handler->expects( 'process' )->never();

		// When
		$response = $this->sut->handle_request( $this->make_request( 'I-BILLING-1', 'TX-1' ) );

		// Then
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @scenario When a PAYMENT.SALE.COMPLETED webhook matches a WC subscription, the handler logs an info line and forwards the matched subscriptions and transaction id to the renewal handler.
	 */
	public function test_logs_info_and_processes_when_subscription_matches(): void
	{
		// Arrange
		$subscription = Mockery::mock( WC_Subscription::class );
		$subscriptions = array( 123 => $subscription );
		when( 'wcs_get_subscriptions' )->justReturn( $subscriptions );
		$this->logger->allows( 'warning' );
		$this->logger->expects( 'info' )->once();
		$this->renewal_handler->expects( 'process' )->once()->with( $subscriptions, 'TX-1' );

		// When
		$response = $this->sut->handle_request( $this->make_request( 'I-BILLING-1', 'TX-1' ) );

		// Then
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Builds a WP_REST_Request mock carrying a minimal PAYMENT.SALE.COMPLETED payload.
	 *
	 * @param string $billing_agreement_id PayPal billing agreement / subscription id.
	 * @param string $transaction_id       PayPal sale/transaction id.
	 * @return WP_REST_Request
	 */
	private function make_request( string $billing_agreement_id, string $transaction_id ): WP_REST_Request
	{
		$resource = array(
			'id'                   => $transaction_id,
			'billing_agreement_id' => $billing_agreement_id,
		);

		/** @var WP_REST_Request&\Mockery\MockInterface $request */
		$request = Mockery::mock( 'WP_REST_Request, ArrayAccess' );
		$request->allows( 'offsetExists' )->andReturn( true );
		$request->allows( 'offsetGet' )->with( 'resource' )->andReturn( $resource );

		return $request;
	}
}
