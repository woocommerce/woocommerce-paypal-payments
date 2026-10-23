<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Button\Endpoint\GetSubscriptionEndpoint
 */
class GetSubscriptionEndpointTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	private RequestData $request_data;
	private BillingSubscriptions $billing_subscriptions;
	private LoggerInterface $logger;
	private array $success_responses;
	private array $error_responses;

	public function setUp(): void
	{
		parent::setUp();

		$this->request_data          = Mockery::mock( RequestData::class );
		$this->billing_subscriptions = Mockery::mock( BillingSubscriptions::class );
		$this->logger                = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		$this->success_responses = array();
		$this->error_responses   = array();

		when( 'wp_send_json_success' )->alias(
			function ( $data = null ): void {
				$this->success_responses[] = $data;
			}
		);
		when( 'wp_send_json_error' )->alias(
			function ( $data = null, $status = null ): void {
				$this->error_responses[] = array( $data, $status );
			}
		);
	}

	private function sut(): GetSubscriptionEndpoint
	{
		return new GetSubscriptionEndpoint(
			$this->request_data,
			$this->billing_subscriptions,
			$this->logger
		);
	}

	private function subscription( $custom_id ): stdClass
	{
		return (object) array(
			'id'         => 'SUB-1',
			'custom_id'  => $custom_id,
			'subscriber' => (object) array(
				'name'             => (object) array( 'given_name' => 'Jane' ),
				'email_address'    => 'jane@example.com',
				'shipping_address' => (object) array( 'address' => (object) array( 'country_code' => 'US' ) ),
				'payer_id'         => 'PAYER-SECRET',
			),
		);
	}

	/**
	 * GIVEN a subscription created for the current shopper session
	 * WHEN the shopper's subscriber details are requested
	 * THEN only the subscriber name, email and shipping address are returned
	 */
	public function test_subscriber_of_session_bound_subscription_is_returned(): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( array( 'subscription_id' => 'SUB-1' ) );
		$this->mock_wc_session( 'shopper-session' );
		$subscription = $this->subscription( 'pcp_customer_shopper-session' );
		$this->billing_subscriptions->allows( 'subscription' )->with( 'SUB-1' )->andReturn( $subscription );

		$this->sut()->handle_request();

		$this->assertSame( array(), $this->error_responses );
		$this->assertSame(
			array(
				array(
					'subscriber' => array(
						'name'             => $subscription->subscriber->name,
						'email_address'    => 'jane@example.com',
						'shipping_address' => $subscription->subscriber->shipping_address,
					),
				),
			),
			$this->success_responses
		);
	}

	/**
	 * GIVEN a subscription whose custom id does not match the current shopper session
	 * WHEN its subscriber details are requested
	 * THEN an error is returned and no subscriber data is sent
	 *
	 * @dataProvider foreign_custom_id_provider
	 */
	public function test_subscription_of_another_session_is_rejected( $custom_id ): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( array( 'subscription_id' => 'SUB-1' ) );
		$this->mock_wc_session( 'shopper-session' );
		$this->billing_subscriptions->allows( 'subscription' )->andReturn( $this->subscription( $custom_id ) );

		$this->sut()->handle_request();

		$this->assert_only_error_sent();
	}

	public function foreign_custom_id_provider(): array
	{
		return array(
			'custom id of another session'    => array( 'pcp_customer_other-session' ),
			'custom id without session prefix' => array( 'shopper-session' ),
			'empty custom id'                 => array( '' ),
			'missing custom id'               => array( null ),
		);
	}

	/**
	 * GIVEN there is no WooCommerce customer session
	 * WHEN the subscriber details of a subscription without custom id are requested
	 * THEN an error is returned and no subscriber data is sent
	 */
	public function test_subscription_is_rejected_when_no_session_exists(): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( array( 'subscription_id' => 'SUB-1' ) );
		$wc          = new stdClass();
		$wc->session = null;
		when( 'WC' )->justReturn( $wc );
		$this->billing_subscriptions->allows( 'subscription' )->andReturn( $this->subscription( '' ) );

		$this->sut()->handle_request();

		$this->assert_only_error_sent();
	}

	/**
	 * GIVEN a request without a subscription id
	 * WHEN the subscriber details are requested
	 * THEN an error is returned and PayPal is not asked
	 *
	 * @dataProvider missing_subscription_id_provider
	 */
	public function test_missing_subscription_id_is_rejected( array $request ): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( $request );
		$this->billing_subscriptions->shouldReceive( 'subscription' )->never();

		$this->sut()->handle_request();

		$this->assert_only_error_sent();
	}

	public function missing_subscription_id_provider(): array
	{
		return array(
			'no subscription id'    => array( array() ),
			'empty subscription id' => array( array( 'subscription_id' => '' ) ),
		);
	}

	/**
	 * GIVEN PayPal fails to return the subscription
	 * WHEN the subscriber details are requested
	 * THEN an error carrying the failure message is returned
	 */
	public function test_api_failure_is_returned_as_error(): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( array( 'subscription_id' => 'SUB-1' ) );
		$this->mock_wc_session( 'shopper-session' );
		$this->billing_subscriptions->allows( 'subscription' )->andThrow( new Exception( 'PayPal is down' ) );

		$this->sut()->handle_request();

		$this->assert_only_error_sent();
		$this->assertSame( array( 'message' => 'PayPal is down' ), $this->error_responses[0][0] );
	}

	/**
	 * GIVEN the request fails nonce validation
	 * WHEN the subscriber details are requested
	 * THEN a 400 error is returned and PayPal is not asked
	 */
	public function test_invalid_nonce_is_answered_with_bad_request(): void
	{
		$this->request_data->allows( 'read_request' )
			->andThrow( new NonceValidationException( 'Invalid nonce' ) );
		$this->billing_subscriptions->shouldReceive( 'subscription' )->never();

		$this->sut()->handle_request();

		$this->assertSame( array(), $this->success_responses );
		$this->assertSame( array( array( array( 'message' => 'Invalid nonce' ), 400 ) ), $this->error_responses );
	}

	private function assert_only_error_sent(): void
	{
		$this->assertSame( array(), $this->success_responses );
		$this->assertCount( 1, $this->error_responses );
	}

	private function mock_wc_session( string $session_id ): void
	{
		$wc_session = Mockery::mock( \WC_Session_Handler::class );
		$wc_session->allows( 'get_customer_unique_id' )->andReturn( $session_id );

		$wc          = new stdClass();
		$wc->session = $wc_session;
		when( 'WC' )->justReturn( $wc );
	}
}
