<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WC_Customer;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Googlepay\Endpoint\UpdatePaymentDataEndpoint;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Googlepay\Endpoint\UpdatePaymentDataEndpoint
 */
class UpdatePaymentDataEndpointTest extends TestCase {
	use MockeryPHPUnitIntegration;

	private RequestData $requestData;
	private LoggerInterface $logger;
	private UpdatePaymentDataEndpoint $sut;

	public function setUp(): void {
		parent::setUp();

		$this->requestData = Mockery::mock( RequestData::class );
		$this->logger      = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		$this->sut = new UpdatePaymentDataEndpoint(
			$this->requestData,
			$this->logger
		);
	}

	/**
	 * GIVEN a Google Pay SHIPPING_ADDRESS callback whose shippingAddress carries
	 *       administrativeArea "NSW"
	 * WHEN the endpoint processes the payment data update
	 * THEN the WooCommerce customer's shipping state and billing state are both set to "NSW"
	 */
	public function testShippingAddressStateIsAppliedToCustomer(): void {
		$data = array(
			'paymentData' => array(
				'callbackTrigger'    => 'SHIPPING_ADDRESS',
				'shippingAddress'    => array(
					'countryCode'        => 'AU',
					'postalCode'         => '2021',
					'locality'           => 'Paddington',
					'administrativeArea' => 'NSW',
				),
				// Present on every real callback; irrelevant to the state-mapping bug under test.
				'shippingOptionData' => array( 'id' => '' ),
			),
		);

		$this->requestData->shouldReceive( 'read_request' )
			->with( UpdatePaymentDataEndpoint::nonce() )
			->andReturn( $data );

		$customer = Mockery::mock( WC_Customer::class )->shouldIgnoreMissing();
		$customer->shouldReceive( 'set_shipping_state' )->once()->with( 'NSW' );
		$customer->shouldReceive( 'set_billing_state' )->once()->with( 'NSW' );

		when( 'WC' )->justReturn(
			(object) array(
				'customer' => $customer,
				'cart'     => Mockery::mock()->shouldIgnoreMissing(),
				'shipping' => Mockery::mock()->shouldIgnoreMissing(),
				'session'  => Mockery::mock()->shouldIgnoreMissing(),
			)
		);

		when( 'wc_get_base_location' )->justReturn( array( 'country' => 'AU' ) );
		when( 'get_woocommerce_currency' )->justReturn( 'AUD' );
		when( 'wp_send_json_success' )->justReturn( null );
		when( 'wp_send_json_error' )->justReturn( null );

		$this->sut->handle_request();
	}
}
