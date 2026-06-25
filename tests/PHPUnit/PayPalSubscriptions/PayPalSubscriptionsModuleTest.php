<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayPalSubscriptions;

use Mockery;
use Psr\Log\LoggerInterface;
use stdClass;
use WC_Order;
use WC_Session;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\PayPalSubscriptions\PayPalSubscriptionsModule
 */
class PayPalSubscriptionsModuleTest extends TestCase
{
	private $container;
	private $billing_subscriptions;
	private $gateway;
	private $wc_order;
	private $wc_session;
	private PayPalSubscriptionsModule $sut;

	public function setUp(): void
	{
		parent::setUp();

		$subscriptions_helper = Mockery::mock( SubscriptionHelper::class );
		$subscriptions_helper->shouldReceive( 'plugin_is_active' )->andReturn( true );

		$this->billing_subscriptions = Mockery::mock( BillingSubscriptions::class );
		$this->wc_session            = Mockery::mock( WC_Session::class );
		$logger                      = Mockery::mock( LoggerInterface::class );
		$logger->shouldReceive( 'error' )->withAnyArgs();

		$this->container = Mockery::mock( ContainerInterface::class );
		$this->container->shouldReceive( 'get' )->with( 'wc-subscriptions.helper' )->andReturn( $subscriptions_helper );
		$this->container->shouldReceive( 'get' )->with( 'api.endpoint.billing-subscriptions' )->andReturn( $this->billing_subscriptions );
		$session_handler = Mockery::mock( 'WooCommerce\PayPalCommerce\Session\SessionHandler' );
		$this->container->shouldReceive( 'get' )->with( 'session.handler' )->andReturn( $session_handler );
		$this->container->shouldReceive( 'get' )->with( 'settings.environment' )->andReturn( Mockery::mock( 'WooCommerce\PayPalCommerce\WcGateway\Helper\Environment' ) );
		$this->container->shouldReceive( 'get' )->with( 'woocommerce.logger.woocommerce' )->andReturn( $logger );

		$this->gateway  = Mockery::mock( PayPalGateway::class );
		$this->wc_order = Mockery::mock( WC_Order::class );

		when( 'WC' )->alias(
			function () {
				$wc          = new stdClass();
				$wc->session = $this->wc_session;
				return $wc;
			}
		);

		$this->sut = new PayPalSubscriptionsModule();
		$this->sut->run( $this->container );
	}

	/**
	 * @scenario Given a customer approves a $1/month subscription and ppcp_subscription_id is stored
	 *           in session with a cart hash, when the customer replaces the cart with a $5000
	 *           non-subscription product and re-submits checkout, then the order's cart hash will not
	 *           match the stored hash, and payment_complete() must NOT be called; the order remains unpaid.
	 */
	public function test_payment_complete_not_called_when_cart_hash_does_not_match(): void
	{
		// Arrange
		$this->wc_session->shouldReceive( 'get' )->with( 'ppcp_subscription_id' )->andReturn( 'I-SUB123' );
		$this->wc_session->shouldReceive( 'get' )->with( 'ppcp_subscription_cart_hash' )->andReturn( 'hash_from_approval' );

		$this->billing_subscriptions->shouldNotReceive( 'subscription' );

		$this->wc_order->shouldReceive( 'get_cart_hash' )->andReturn( 'hash_from_new_order' );
		$this->wc_order->shouldNotReceive( 'payment_complete' );

		// Then
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Cart changed after subscription approval; please review and approve again.' );

		// When
		$this->sut->handle_before_order_process( true, $this->gateway, $this->wc_order );
	}

	/**
	 * @scenario Given a customer approves a $1/month subscription with ppcp_subscription_id in session,
	 *           when the filter validates and calls payment_complete() on the matching order, then
	 *           both ppcp_subscription_id and ppcp_subscription_cart_hash must be removed from
	 *           WC()->session afterward so neither key can be replayed on a second checkout.
	 */
	public function test_session_cleared_after_successful_payment_complete(): void
	{
		// Arrange
		$cart_hash = 'wc_cart_hash_abc123';

		$this->wc_session->shouldReceive( 'get' )->with( 'ppcp_subscription_id' )->andReturn( 'I-SUB123' );
		$this->wc_session->shouldReceive( 'get' )->with( 'ppcp_subscription_cart_hash' )->andReturn( $cart_hash );
		$this->wc_session->shouldReceive( 'set' )->with( 'ppcp_subscription_id', null )->once();
		$this->wc_session->shouldReceive( 'set' )->with( 'ppcp_subscription_cart_hash', null )->once();

		$this->billing_subscriptions->shouldReceive( 'subscription' )->with( 'I-SUB123' )->once()
			->andReturn( $this->create_active_subscription() );

		$paypal_order    = Mockery::mock( 'WooCommerce\PayPalCommerce\ApiClient\Entity\Order' );
		$session_handler = $this->container->get( 'session.handler' );
		$session_handler->shouldReceive( 'order' )->andReturn( $paypal_order );

		$this->gateway->shouldReceive( 'add_paypal_meta' )->once()->withAnyArgs();
		$this->gateway->shouldReceive( 'get_paypal_order_transaction_id' )->andReturn( null );

		when( 'wcs_get_subscriptions_for_order' )->justReturn( array() );

		$this->wc_order->shouldReceive( 'get_cart_hash' )->andReturn( $cart_hash );
		$this->wc_order->shouldReceive( 'payment_complete' )->once();

		// When
		$result = $this->sut->handle_before_order_process( true, $this->gateway, $this->wc_order );

		// Then
		$this->assertFalse( $result );
	}

	/**
	 * @scenario Given a customer's cart changes (item added or removed) after a subscription approval
	 *           has stored ppcp_subscription_id in session, when the cart change event fires, then
	 *           ppcp_subscription_id must be cleared from WC()->session as housekeeping (the
	 *           cart-hash comparison in handle_before_order_process is the actual security gate).
	 */
	public function test_session_cleared_when_cart_item_is_removed(): void
	{
		// Arrange
		$this->wc_session->shouldReceive( 'set' )->with( 'ppcp_subscription_id', null )->once();
		$this->wc_session->shouldReceive( 'set' )->with( 'ppcp_subscription_cart_hash', null )->once();

		// When
		$this->sut->clear_subscription_on_cart_change();

		// Then — Mockery expectation verified in tearDown
		$this->addToAssertionCount( 1 );
	}

	private function create_active_subscription(): stdClass
	{
		$sub         = new stdClass();
		$sub->status = 'ACTIVE';
		return $sub;
	}
}
