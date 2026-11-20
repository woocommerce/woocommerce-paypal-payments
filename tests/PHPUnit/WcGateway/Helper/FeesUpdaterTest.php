<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use RuntimeException;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CaptureFactory;
use WooCommerce\PayPalCommerce\TestCase;

class FeesUpdaterTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	/**
	 * GIVEN the PayPal order request fails with an exception thrown by the Orders endpoint
	 * WHEN the fees are updated for a WC order
	 * THEN a warning is logged and no exception reaches the caller, so the webhook handler can still complete the order
	 *
	 * @dataProvider ordersEndpointFailures
	 */
	public function testUpdateLogsAndReturnsWhenThePayPalOrderCannotBeFetched( string $failure ): void
	{
		$exception = 'transport' === $failure
			? new RuntimeException( 'cURL error 28: Operation timed out' )
			: new PayPalApiException( (object) array( 'name' => 'SERVICE_UNAVAILABLE' ), 503 );

		$orders = Mockery::mock( Orders::class );
		$orders->expects( 'order' )->with( 'PAYPAL-ORDER-1' )->andThrow( $exception );

		$logger = Mockery::mock( LoggerInterface::class );
		$logger->expects( 'warning' )->once();

		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 123 );
		$wc_order->shouldNotReceive( 'update_meta_data' );

		$testee = new FeesUpdater( $orders, Mockery::mock( CaptureFactory::class ), $logger );

		$testee->update( 'PAYPAL-ORDER-1', $wc_order );
	}

	public function ordersEndpointFailures(): array
	{
		return array(
			'transport error (WP_Error)' => array( 'transport' ),
			'PayPal 503 without details' => array( 'api' ),
		);
	}
}
