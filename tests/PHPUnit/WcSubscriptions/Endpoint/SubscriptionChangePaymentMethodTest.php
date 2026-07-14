<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions\Endpoint;

use Mockery;
use WC_Order;
use WC_Payment_Token;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\WcSubscriptions\Endpoint\SubscriptionChangePaymentMethod
 */
class SubscriptionChangePaymentMethodTest extends TestCase
{
	private $request_data;
	private $payment_tokens;
	private SubscriptionChangePaymentMethod $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->request_data   = Mockery::mock( RequestData::class );
		$this->payment_tokens = Mockery::mock( 'alias:WC_Payment_Tokens' );
		$this->sut            = new SubscriptionChangePaymentMethod( $this->request_data );
	}

	/**
	 * @scenario Attacker (user 1) owns their own subscription but POSTs subscription_id=20
	 * belonging to victim (user 2). Endpoint must return 403 and leave the victim's
	 * subscription untouched.
	 */
	public function test_returns_403_when_subscription_belongs_to_different_user(): void
	{
		// Arrange
		$subscription = Mockery::mock( WC_Order::class );
		$subscription->shouldReceive( 'get_customer_id' )->andReturn( 2 );
		$subscription->shouldNotReceive( 'set_payment_method' );
		$subscription->shouldNotReceive( 'add_payment_token' );
		$subscription->shouldNotReceive( 'save' );

		$this->request_data->shouldReceive( 'read_request' )
			->once()
			->andReturn(
				[
					'subscription_id'     => 20,
					'payment_method'      => 'ppcp-gateway',
					'wc_payment_token_id' => 99,
				]
			);

		when( 'wcs_get_subscription' )->justReturn( $subscription );
		when( 'get_current_user_id' )->justReturn( 1 );

		expect( 'wp_send_json_error' )
			->once()
			->with( Mockery::any(), 403 )
			->andReturnUsing( static function (): void {
				throw new \Error( 'wp_send_json_error' );
			} );

		// When / Then
		$this->expectException( \Error::class );
		$this->sut->handle_request();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @scenario Current user owns the subscription but supplies a wc_payment_token_id
	 * belonging to a different user. Endpoint must return 403 and not mutate
	 * any subscription state.
	 */
	public function test_returns_403_when_payment_token_belongs_to_different_user(): void
	{
		// Arrange
		$token = Mockery::mock( WC_Payment_Token::class );
		$token->shouldReceive( 'get_user_id' )->andReturn( 2 );

		$subscription = Mockery::mock( WC_Order::class );
		$subscription->shouldReceive( 'get_customer_id' )->andReturn( 1 );
		$subscription->shouldNotReceive( 'set_payment_method' );
		$subscription->shouldNotReceive( 'add_payment_token' );
		$subscription->shouldNotReceive( 'save' );

		$this->request_data->shouldReceive( 'read_request' )
			->once()
			->andReturn(
				[
					'subscription_id'     => 10,
					'payment_method'      => 'ppcp-gateway',
					'wc_payment_token_id' => 99,
				]
			);

		$this->payment_tokens->shouldReceive( 'get' )
			->with( 99 )
			->andReturn( $token );

		when( 'wcs_get_subscription' )->justReturn( $subscription );
		when( 'get_current_user_id' )->justReturn( 1 );

		expect( 'wp_send_json_error' )
			->once()
			->with( Mockery::any(), 403 )
			->andReturnUsing( static function (): void {
				throw new \Error( 'wp_send_json_error' );
			} );

		// When / Then
		$this->expectException( \Error::class );
		$this->sut->handle_request();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @scenario Current user owns both the subscription and the payment token.
	 * Endpoint must update the payment method, attach the token, persist the
	 * subscription, and return success.
	 */
	public function test_updates_payment_method_and_token_when_ownership_checks_pass(): void
	{
		// Arrange
		$token = Mockery::mock( WC_Payment_Token::class );
		$token->shouldReceive( 'get_user_id' )->andReturn( 1 );

		$subscription = Mockery::mock( WC_Order::class );
		$subscription->shouldReceive( 'get_customer_id' )->andReturn( 1 );
		$subscription->shouldReceive( 'set_payment_method' )->once()->with( 'ppcp-gateway' );
		$subscription->shouldReceive( 'add_payment_token' )->once()->with( $token );
		$subscription->shouldReceive( 'save' )->once();

		$this->request_data->shouldReceive( 'read_request' )
			->once()
			->andReturn(
				[
					'subscription_id'     => 10,
					'payment_method'      => 'ppcp-gateway',
					'wc_payment_token_id' => 99,
				]
			);

		$this->payment_tokens->shouldReceive( 'get' )
			->with( 99 )
			->andReturn( $token );

		when( 'wcs_get_subscription' )->justReturn( $subscription );
		when( 'get_current_user_id' )->justReturn( 1 );

		expect( 'wp_send_json_success' )
			->once()
			->andReturnUsing( static function (): void {
				throw new \Error( 'wp_send_json_success' );
			} );

		// When / Then
		$this->expectException( \Error::class );
		$this->sut->handle_request();
	}
}