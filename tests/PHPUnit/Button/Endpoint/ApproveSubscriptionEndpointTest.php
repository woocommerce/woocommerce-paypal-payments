<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Button\Endpoint\ApproveSubscriptionEndpoint
 */
class ApproveSubscriptionEndpointTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	private RequestData $request_data;
	private OrderEndpoint $order_endpoint;
	private SessionHandler $session_handler;
	private WooCommerceOrderCreator $wc_order_creator;
	private PayPalGateway $gateway;
	private Context $context;
	private BillingSubscriptions $billing_subscriptions;
	private LoggerInterface $logger;
	private SubscriptionHelper $subscription_helper;
	private ApproveSubscriptionEndpoint $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->request_data          = Mockery::mock( RequestData::class );
		$this->order_endpoint        = Mockery::mock( OrderEndpoint::class );
		$this->session_handler       = Mockery::mock( SessionHandler::class );
		$this->wc_order_creator      = Mockery::mock( WooCommerceOrderCreator::class );
		$this->gateway               = Mockery::mock( PayPalGateway::class );
		$this->context               = Mockery::mock( Context::class );
		$this->billing_subscriptions = Mockery::mock( BillingSubscriptions::class );
		$this->logger                = Mockery::mock( LoggerInterface::class );
		$this->subscription_helper   = Mockery::mock( SubscriptionHelper::class );

		$this->sut = new ApproveSubscriptionEndpoint(
			$this->request_data,
			$this->order_endpoint,
			$this->session_handler,
			false,
			$this->wc_order_creator,
			$this->gateway,
			$this->context,
			$this->billing_subscriptions,
			$this->logger,
			$this->subscription_helper
		);
	}

	/**
	 * @scenario The shopper keeps their own valid subscription_id but swaps in an order_id
	 *           approved by a different payer. The order is rejected before replace_order().
	 */
	public function test_order_not_approved_by_subscriber_is_rejected(): void
	{
		$session_id   = 'shopper-session';
		$subscription = $this->subscription( $session_id, 'PAYER-A' );

		// The order is bound to this session (custom_id passes) but approved by a different payer.
		$order = $this->order_with_payer( 'PAYER-B', 'pcp_customer_' . $session_id );

		$this->request_data->shouldReceive( 'read_request' )
			->with( ApproveSubscriptionEndpoint::nonce() )
			->andReturn(
				array(
					'order_id'        => 'ORDER-OTHER',
					'subscription_id' => 'SUB-1',
				)
			);
		$this->billing_subscriptions->shouldReceive( 'subscription' )->with( 'SUB-1' )->andReturn( $subscription );
		$this->subscription_helper->shouldReceive( 'paypal_subscription_variation_from_cart' )->andReturn( '' );
		$this->subscription_helper->shouldReceive( 'paypal_subscription_id' )->andReturn( 'PLAN-1' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'ORDER-OTHER' )->andReturn( $order );
		$this->session_handler->shouldReceive( 'replace_order' )->never();
		$this->gateway->shouldReceive( 'process_payment' )->never();
		$this->logger->shouldReceive( 'error' )->once();

		$this->mock_wc_session( $session_id );
		expect( 'wp_send_json_error' )->once();

		$this->sut->handle_request();
	}

	/**
	 * @scenario The shopper keeps their own valid subscription_id and a same-payer order_id, but
	 *           the swapped order is bound to a different session. The order is rejected before
	 *           replace_order().
	 */
	public function test_order_bound_to_other_session_is_rejected(): void
	{
		$session_id   = 'shopper-session';
		$subscription = $this->subscription( $session_id, 'PAYER-A' );

		// Same payer as the subscription, but the order belongs to a different session.
		$order = $this->order_with_payer( 'PAYER-A', 'pcp_customer_other-session' );

		$this->request_data->shouldReceive( 'read_request' )
			->with( ApproveSubscriptionEndpoint::nonce() )
			->andReturn(
				array(
					'order_id'        => 'ORDER-OTHER-SESSION',
					'subscription_id' => 'SUB-1',
				)
			);
		$this->billing_subscriptions->shouldReceive( 'subscription' )->with( 'SUB-1' )->andReturn( $subscription );
		$this->subscription_helper->shouldReceive( 'paypal_subscription_variation_from_cart' )->andReturn( '' );
		$this->subscription_helper->shouldReceive( 'paypal_subscription_id' )->andReturn( 'PLAN-1' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'ORDER-OTHER-SESSION' )->andReturn( $order );
		$this->session_handler->shouldReceive( 'replace_order' )->never();
		$this->gateway->shouldReceive( 'process_payment' )->never();
		$this->logger->shouldReceive( 'error' )->once();

		$this->mock_wc_session( $session_id );
		expect( 'wp_send_json_error' )->once();

		$this->sut->handle_request();
	}

	/**
	 * @scenario A PayPal subscription whose custom_id is bound to another session is rejected
	 *           before the order is even fetched or the session replaced.
	 */
	public function test_subscription_from_other_session_is_rejected_before_order_fetch(): void
	{
		$subscription = $this->subscription( 'other-session', 'PAYER-A' );

		$this->request_data->shouldReceive( 'read_request' )
			->with( ApproveSubscriptionEndpoint::nonce() )
			->andReturn(
				array(
					'order_id'        => 'ORDER-OTHER',
					'subscription_id' => 'SUB-OTHER',
				)
			);
		$this->billing_subscriptions->shouldReceive( 'subscription' )->with( 'SUB-OTHER' )->andReturn( $subscription );
		$this->order_endpoint->shouldReceive( 'order' )->never();
		$this->session_handler->shouldReceive( 'replace_order' )->never();
		$this->logger->shouldReceive( 'error' )->once();

		$this->mock_wc_session( 'current-session' );
		expect( 'wp_send_json_error' )->once();

		$this->sut->handle_request();
	}

	/**
	 * @scenario A subscription and order created and approved by the current shopper pass
	 *           validation and proceed through replace_order().
	 */
	public function test_valid_session_proceeds_to_replace_order(): void
	{
		$session_id   = 'shopper-session';
		$subscription = $this->subscription( $session_id, 'PAYER-A' );

		$order = $this->order_with_payer( 'PAYER-A', 'pcp_customer_' . $session_id );

		$this->request_data->shouldReceive( 'read_request' )
			->with( ApproveSubscriptionEndpoint::nonce() )
			->andReturn(
				array(
					'order_id'        => 'VALID-ORDER',
					'subscription_id' => 'SUB-1',
				)
			);
		$this->billing_subscriptions->shouldReceive( 'subscription' )->with( 'SUB-1' )->andReturn( $subscription );
		$this->subscription_helper->shouldReceive( 'paypal_subscription_variation_from_cart' )->andReturn( '' );
		$this->subscription_helper->shouldReceive( 'paypal_subscription_id' )->andReturn( 'PLAN-1' );
		$this->order_endpoint->shouldReceive( 'order' )->with( 'VALID-ORDER' )->andReturn( $order );
		$this->session_handler->shouldReceive( 'replace_order' )->once()->with( $order );
		$this->context->shouldReceive( 'is_checkout' )->andReturn( true );

		$this->mock_wc_session( $session_id );
		expect( 'wp_send_json_success' )->once();

		$this->sut->handle_request();
	}

	/**
	 * Builds a PayPal subscription stdClass bound to the given session and subscriber.
	 */
	private function subscription( string $session_id, string $payer_id ): \stdClass
	{
		return (object) array(
			'status'     => 'ACTIVE',
			'custom_id'  => 'pcp_customer_' . $session_id,
			'plan_id'    => 'PLAN-1',
			'subscriber' => (object) array( 'payer_id' => $payer_id ),
		);
	}

	/**
	 * Builds a PayPal order mock whose payer carries the given payer id and whose first
	 * purchase unit carries the given custom_id.
	 */
	private function order_with_payer( string $payer_id, string $custom_id = '' ): Order
	{
		$payer = Mockery::mock( Payer::class );
		$payer->shouldReceive( 'payer_id' )->andReturn( $payer_id );

		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->shouldReceive( 'custom_id' )->andReturn( $custom_id );

		$order = Mockery::mock( Order::class );
		$order->shouldReceive( 'payer' )->andReturn( $payer );
		$order->shouldReceive( 'purchase_units' )->andReturn( array( $purchase_unit ) );

		return $order;
	}

	/**
	 * Mocks WC()->session with the given customer unique id.
	 */
	private function mock_wc_session( string $session_id ): \WC_Session_Handler
	{
		$wc_session = Mockery::mock( \WC_Session_Handler::class );
		$wc_session->shouldReceive( 'get_customer_unique_id' )->andReturn( $session_id );
		$wc_session->shouldReceive( 'set' );

		$wc_cart = Mockery::mock();
		$wc_cart->shouldReceive( 'get_cart_hash' )->andReturn( 'cart-hash' );

		$wc          = Mockery::mock();
		$wc->session = $wc_session;
		$wc->cart    = $wc_cart;
		when( 'WC' )->justReturn( $wc );

		return $wc_session;
	}
}
