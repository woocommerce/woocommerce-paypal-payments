<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderEndpoints\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\OrderEndpoints\Helper\PayNowOrderHandler
 */
class PayNowOrderHandlerTest extends TestCase
{
	use MockeryPHPUnitIntegration;

	private WooCommerceOrderCreator $wc_order_creator;
	private PayPalGateway $gateway;
	private Order $paypal_order;
	private PayNowOrderHandler $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->wc_order_creator = Mockery::mock( WooCommerceOrderCreator::class );
		$this->gateway          = Mockery::mock( PayPalGateway::class );
		$this->paypal_order     = Mockery::mock( Order::class );

		$this->sut = $this->create_sut( true, false );
	}

	private function create_sut( bool $pay_now_enabled, bool $is_checkout ): PayNowOrderHandler
	{
		$context = Mockery::mock( Context::class );
		$context->shouldReceive( 'is_checkout' )->andReturn( $is_checkout );

		$settings_provider = Mockery::mock( SettingsProvider::class );
		$settings_provider->shouldReceive( 'enable_pay_now' )->andReturn( $pay_now_enabled );

		return new PayNowOrderHandler( $this->wc_order_creator, $this->gateway, $settings_provider, $context );
	}

	/**
	 * GIVEN an approval request, the Pay Now setting and the current page
	 * WHEN the handler is asked whether the approval completes the purchase
	 * THEN it applies only if the client asks for a WC order, Pay Now is on and the page is not checkout
	 *
	 * @dataProvider applies_provider
	 */
	public function test_applies_only_when_all_conditions_are_met(
		array $request_data,
		bool $pay_now_enabled,
		bool $is_checkout,
		bool $expected
	): void {
		$sut = $this->create_sut( $pay_now_enabled, $is_checkout );

		$this->assertSame( $expected, $sut->applies( $request_data ) );
	}

	public function applies_provider(): array
	{
		$asked = array( 'should_create_wc_order' => true );

		return array(
			'all three conditions are met' => array( $asked, true, false, true ),
			'client does not ask for a WC order' => array( array( 'should_create_wc_order' => false ), true, false, false ),
			'request has no should_create_wc_order key' => array( array( 'order_id' => 'ORDER-1' ), true, false, false ),
			'Pay Now is disabled' => array( $asked, false, false, false ),
			'current page is the checkout' => array( $asked, true, true, false ),
		);
	}

	/**
	 * GIVEN an approved PayPal order and a WC order created from the cart
	 * WHEN the gateway returns a redirect for the payment
	 * THEN the handler returns exactly that redirect
	 *
	 * @dataProvider gateway_redirect_provider
	 */
	public function test_pay_returns_the_redirect_of_the_gateway( string $redirect ): void
	{
		$cart = $this->prepare_payment( null, 'processing', $redirect );
		$cart->shouldReceive( 'empty_cart' );

		$this->assertSame( $redirect, $this->sut->pay( $this->paypal_order ) );
	}

	public function gateway_redirect_provider(): array
	{
		return array(
			'order-received URL after a successful payment' => array( 'https://shop.example/checkout/order-received/55/?key=wc_order_abc' ),
			'PayPal approval URL when payment needs another approval' => array( 'https://www.paypal.com/checkoutnow?token=ORDER-1' ),
			'checkout URL when the payment fails' => array( 'https://shop.example/checkout/' ),
		);
	}

	/**
	 * GIVEN a WC order for which the gateway reports success
	 * WHEN the order has a given status after the payment
	 * THEN the cart is emptied unless the order is pending, failed or canceled
	 *
	 * @dataProvider order_status_provider
	 */
	public function test_pay_empties_cart_depending_on_order_status( string $status, bool $cart_is_emptied ): void
	{
		$cart = $this->prepare_payment( null, $status, 'https://shop.example/checkout/order-received/55/' );
		$cart->shouldReceive( 'empty_cart' )->times( $cart_is_emptied ? 1 : 0 );

		$this->sut->pay( $this->paypal_order );

		$this->addToAssertionCount( 1 );
	}

	public function order_status_provider(): array
	{
		return array(
			'processing order empties the cart' => array( 'processing', true ),
			'completed order empties the cart' => array( 'completed', true ),
			'on-hold order (authorize only) empties the cart' => array( 'on-hold', true ),
			'pending order (retry) keeps the cart' => array( 'pending', false ),
			'failed order keeps the cart' => array( 'failed', false ),
			'canceled order keeps the cart' => array( 'cancelled', false ),
		);
	}

	/**
	 * GIVEN the payer and shipping data of the buyer's request
	 * WHEN the order is paid
	 * THEN the WC order is created from the PayPal order, the cart and that data
	 *
	 * @dataProvider paypal_data_provider
	 */
	public function test_pay_passes_the_paypal_data_to_the_order_creator( ?array $paypal_data ): void
	{
		$cart = $this->prepare_payment( $paypal_data, 'processing','https://shop.example/checkout/' );
		$cart->shouldReceive( 'empty_cart' );

		$this->sut->pay( $this->paypal_order, $paypal_data );

		$this->addToAssertionCount( 1 );
	}

	public function paypal_data_provider(): array
	{
		return array(
			'payer data from the request' => array( array( 'payer' => array( 'email_address' => 'buyer@example.com' ) ) ),
			'no data from the request' => array( null ),
		);
	}

	/**
	 * Sets up the order creator, the gateway and the WC globals for one payment.
	 * The order creator only returns the WC order for the expected arguments.
	 *
	 * @return \Mockery\MockInterface The WC cart double.
	 */
	private function prepare_payment( ?array $paypal_data, string $status, string $redirect ): \Mockery\MockInterface
	{
		$cart = Mockery::mock();
		$wc   = Mockery::mock();

		$wc->cart = $cart;
		when( 'WC' )->justReturn( $wc );

		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->shouldReceive( 'get_id' )->andReturn( 55 );

		$reloaded_order = Mockery::mock( WC_Order::class );
		$reloaded_order->shouldReceive( 'has_status' )->andReturnUsing(
			static fn( $statuses ): bool => in_array( $status, (array) $statuses, true )
		);
		when( 'wc_get_order' )->justReturn( $reloaded_order );

		$this->wc_order_creator->shouldReceive( 'create_from_paypal_order' )
			->with( $this->paypal_order, $cart, $paypal_data )
			->andReturn( $wc_order );
		$this->gateway->shouldReceive( 'process_payment' )
			->with( 55 )
			->andReturn( array( 'result' => 'success', 'redirect' => $redirect ) );

		return $cart;
	}
}
