<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;
use stdClass;
use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Button\Endpoint\CreateSubscriptionEndpoint
 */
class CreateSubscriptionEndpointTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	private RequestData $request_data;
	private BillingSubscriptions $billing_subscriptions;
	private SubscriptionHelper $subscription_helper;
	private LoggerInterface $logger;
	private string $mode;
	private array $success_responses;
	private array $error_responses;

	public function setUp(): void
	{
		parent::setUp();

		$this->request_data          = Mockery::mock( RequestData::class );
		$this->billing_subscriptions = Mockery::mock( BillingSubscriptions::class );
		$this->subscription_helper   = Mockery::mock( SubscriptionHelper::class );
		$this->logger                = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();
		$this->mode                  = SubscriptionHelper::SUBSCRIPTION_MODE_VALUE_SUBSCRIPTIONS;

		$this->success_responses = array();
		$this->error_responses   = array();

		when( 'absint' )->alias( static fn( $value ) => abs( (int) $value ) );
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

	private function sut(): CreateSubscriptionEndpoint
	{
		return new CreateSubscriptionEndpoint(
			$this->request_data,
			$this->billing_subscriptions,
			$this->subscription_helper,
			fn(): string => $this->mode,
			$this->logger
		);
	}

	/**
	 * GIVEN a cart holding a subscription variation with its own plan
	 * WHEN the shopper starts the PayPal subscription
	 * THEN the subscription is created with the variation plan, bound to the shopper session
	 * AND the response carries the new subscription id
	 */
	public function test_cart_variation_plan_is_used_to_create_session_bound_subscription(): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( array() );
		$this->subscription_helper->allows( 'paypal_subscription_variation_from_cart' )->andReturn( 'PLAN-VARIATION' );
		$this->subscription_helper->allows( 'paypal_subscription_id' )->andReturn( 'PLAN-PRODUCT' );
		$this->mock_wc_session( 'shopper-session' );
		$this->billing_subscriptions->shouldReceive( 'create' )
			->once()
			->with( 'PLAN-VARIATION', 'pcp_customer_shopper-session' )
			->andReturn( (object) array( 'id' => 'SUB-1' ) );

		$this->sut()->handle_request();

		$this->assertSame( array( array( 'subscription_id' => 'SUB-1' ) ), $this->success_responses );
		$this->assertSame( array(), $this->error_responses );
	}

	/**
	 * GIVEN a cart whose subscription has no variation plan
	 * WHEN the shopper starts the PayPal subscription
	 * THEN the plan of the cart's subscription product is used
	 */
	public function test_cart_without_variation_plan_falls_back_to_product_plan(): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( array() );
		$this->subscription_helper->allows( 'paypal_subscription_variation_from_cart' )->andReturn( '' );
		$this->subscription_helper->allows( 'paypal_subscription_id' )->andReturn( 'PLAN-PRODUCT' );
		$this->mock_wc_session( 'shopper-session' );
		$this->billing_subscriptions->shouldReceive( 'create' )
			->once()
			->with( 'PLAN-PRODUCT', 'pcp_customer_shopper-session' )
			->andReturn( (object) array( 'id' => 'SUB-2' ) );

		$this->sut()->handle_request();

		$this->assertSame( array( array( 'subscription_id' => 'SUB-2' ) ), $this->success_responses );
	}

	/**
	 * GIVEN a purchasable simple subscription product that has a PayPal plan
	 * WHEN the single product page requests a subscription for that product
	 * THEN the subscription is created with the product plan, without consulting the cart
	 */
	public function test_single_simple_product_plan_is_used(): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( array( 'product_id' => 10 ) );
		$this->subscription_helper->shouldReceive( 'paypal_subscription_variation_from_cart' )->never();
		$this->subscription_helper->shouldReceive( 'paypal_subscription_id' )->never();
		when( 'wc_get_product' )->justReturn( $this->product( array( 'id' => 'PLAN-SIMPLE' ) ) );
		$this->mock_wc_session( 'shopper-session' );
		$this->billing_subscriptions->shouldReceive( 'create' )
			->once()
			->with( 'PLAN-SIMPLE', 'pcp_customer_shopper-session' )
			->andReturn( (object) array( 'id' => 'SUB-3' ) );

		$this->sut()->handle_request();

		$this->assertSame( array( array( 'subscription_id' => 'SUB-3' ) ), $this->success_responses );
	}

	/**
	 * GIVEN a purchasable variation that belongs to the requested product
	 * WHEN the single product page requests a subscription for that variation
	 * THEN the subscription is created with the variation plan
	 */
	public function test_variation_of_requested_product_plan_is_used(): void
	{
		$this->request_data->allows( 'read_request' )
			->andReturn( array( 'product_id' => 10, 'variation_id' => 11 ) );
		when( 'wc_get_product' )->alias(
			fn( $id ) => 11 === $id ? $this->product( array( 'id' => 'PLAN-VAR' ), true, 10 ) : null
		);
		$this->mock_wc_session( 'shopper-session' );
		$this->billing_subscriptions->shouldReceive( 'create' )
			->once()
			->with( 'PLAN-VAR', 'pcp_customer_shopper-session' )
			->andReturn( (object) array( 'id' => 'SUB-4' ) );

		$this->sut()->handle_request();

		$this->assertSame( array( array( 'subscription_id' => 'SUB-4' ) ), $this->success_responses );
	}

	/**
	 * GIVEN a variation that belongs to a different product than the one requested
	 * WHEN the shopper requests a subscription for it
	 * THEN no subscription is created and an error is returned
	 */
	public function test_variation_of_other_product_is_rejected(): void
	{
		$this->request_data->allows( 'read_request' )
			->andReturn( array( 'product_id' => 10, 'variation_id' => 11 ) );
		when( 'wc_get_product' )->justReturn( $this->product( array( 'id' => 'PLAN-VAR' ), true, 99 ) );
		$this->mock_wc_session( 'shopper-session' );
		$this->billing_subscriptions->shouldReceive( 'create' )->never();

		$this->sut()->handle_request();

		$this->assert_only_error_sent();
	}

	/**
	 * GIVEN a product that cannot be purchased
	 * WHEN the shopper requests a subscription for it
	 * THEN no subscription is created and an error is returned
	 */
	public function test_non_purchasable_product_is_rejected(): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( array( 'product_id' => 10 ) );
		when( 'wc_get_product' )->justReturn( $this->product( array( 'id' => 'PLAN-SIMPLE' ), false ) );
		$this->mock_wc_session( 'shopper-session' );
		$this->billing_subscriptions->shouldReceive( 'create' )->never();

		$this->sut()->handle_request();

		$this->assert_only_error_sent();
	}

	/**
	 * GIVEN the merchant does not use the PayPal Subscriptions API mode
	 * WHEN the shopper starts a PayPal subscription
	 * THEN no subscription is created and an error is returned
	 *
	 * @dataProvider other_modes_provider
	 */
	public function test_subscription_is_not_created_outside_subscriptions_api_mode( string $mode ): void
	{
		$this->mode = $mode;
		$this->request_data->allows( 'read_request' )->andReturn( array() );
		$this->subscription_helper->allows( 'paypal_subscription_variation_from_cart' )->andReturn( 'PLAN-VARIATION' );
		$this->subscription_helper->allows( 'paypal_subscription_id' )->andReturn( 'PLAN-PRODUCT' );
		$this->mock_wc_session( 'shopper-session' );
		$this->billing_subscriptions->shouldReceive( 'create' )->never();

		$this->sut()->handle_request();

		$this->assert_only_error_sent();
	}

	public function other_modes_provider(): array
	{
		return array(
			'vaulting api mode' => array( 'vaulting_api' ),
			'disabled mode'     => array( 'disable_paypal_subscriptions' ),
		);
	}

	/**
	 * GIVEN neither the cart nor the product provides a plan
	 * WHEN the shopper starts a PayPal subscription
	 * THEN no subscription is created and an error is returned
	 *
	 * @dataProvider missing_plan_provider
	 */
	public function test_missing_plan_is_rejected( array $request, ?array $product_plan ): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( $request );
		$this->subscription_helper->allows( 'paypal_subscription_variation_from_cart' )->andReturn( '' );
		$this->subscription_helper->allows( 'paypal_subscription_id' )->andReturn( '' );
		when( 'wc_get_product' )->justReturn( $this->product( $product_plan ?? '' ) );
		$this->mock_wc_session( 'shopper-session' );
		$this->billing_subscriptions->shouldReceive( 'create' )->never();

		$this->sut()->handle_request();

		$this->assert_only_error_sent();
	}

	public function missing_plan_provider(): array
	{
		return array(
			'cart has no plan'                  => array( array(), null ),
			'product has no plan meta'          => array( array( 'product_id' => 10 ), null ),
			'product plan meta has no plan id'  => array( array( 'product_id' => 10 ), array( 'name' => 'x' ) ),
		);
	}

	/**
	 * GIVEN PayPal rejects the subscription creation
	 * WHEN the shopper starts a PayPal subscription
	 * THEN an error response carrying the PayPal error name and message is returned
	 * AND no success response is sent
	 */
	public function test_paypal_api_failure_is_returned_as_error(): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( array() );
		$this->subscription_helper->allows( 'paypal_subscription_variation_from_cart' )->andReturn( 'PLAN-VARIATION' );
		$this->mock_wc_session( 'shopper-session' );
		$this->billing_subscriptions->allows( 'create' )->andThrow(
			new PayPalApiException(
				(object) array(
					'name'    => 'INVALID_REQUEST',
					'message' => 'Plan is inactive',
				),
				422
			)
		);

		$this->sut()->handle_request();

		$this->assert_only_error_sent();
		$payload = $this->error_responses[0][0];
		$this->assertSame( 'INVALID_REQUEST', $payload['name'] );
		$this->assertStringContainsString( 'Plan is inactive', $payload['message'] );
		$this->assertSame( 422, $payload['code'] );
	}

	/**
	 * GIVEN the request fails nonce validation
	 * WHEN the shopper starts a PayPal subscription
	 * THEN a 400 error is returned and no subscription is created
	 */
	public function test_invalid_nonce_is_answered_with_bad_request(): void
	{
		$this->request_data->allows( 'read_request' )
			->andThrow( new NonceValidationException( 'Invalid nonce' ) );
		$this->billing_subscriptions->shouldReceive( 'create' )->never();

		$this->sut()->handle_request();

		$this->assertSame( array(), $this->success_responses );
		$this->assertSame( array( array( array( 'message' => 'Invalid nonce' ), 400 ) ), $this->error_responses );
	}

	/**
	 * GIVEN there is no WooCommerce customer session
	 * WHEN the shopper starts a PayPal subscription
	 * THEN the subscription is created without a custom id
	 */
	public function test_subscription_is_created_without_custom_id_when_no_session_exists(): void
	{
		$this->request_data->allows( 'read_request' )->andReturn( array() );
		$this->subscription_helper->allows( 'paypal_subscription_variation_from_cart' )->andReturn( 'PLAN-VARIATION' );
		$wc          = new stdClass();
		$wc->session = null;
		when( 'WC' )->justReturn( $wc );
		$this->billing_subscriptions->shouldReceive( 'create' )
			->once()
			->with( 'PLAN-VARIATION', '' )
			->andReturn( (object) array( 'id' => 'SUB-5' ) );

		$this->sut()->handle_request();

		$this->assertSame( array( array( 'subscription_id' => 'SUB-5' ) ), $this->success_responses );
	}

	private function assert_only_error_sent(): void
	{
		$this->assertSame( array(), $this->success_responses );
		$this->assertCount( 1, $this->error_responses );
	}

	/**
	 * @param array|string $plan_meta The value of the ppcp_subscription_plan meta.
	 */
	private function product( $plan_meta, bool $purchasable = true, int $parent_id = 0 ): MockInterface
	{
		$product = Mockery::mock( WC_Product::class );
		$product->allows( 'is_purchasable' )->andReturn( $purchasable );
		$product->allows( 'get_parent_id' )->andReturn( $parent_id );
		$product->allows( 'get_meta' )->with( 'ppcp_subscription_plan' )->andReturn( $plan_meta );

		return $product;
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
