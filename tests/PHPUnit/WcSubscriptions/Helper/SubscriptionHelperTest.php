<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions\Helper;

use Mockery;
use WC_Order;
use WC_Subscription;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use function Brain\Monkey\Functions\when;

class SubscriptionHelperTest extends TestCase
{
	public function testPreviousTransaction()
	{
		$subscription = Mockery::mock(WC_Subscription::class);
		$subscription->shouldReceive('get_related_orders')
			->andReturn(
				[
					1 => 1,
					3 => 3,
					2 => 2,
				]
			);



		$token = Mockery::mock( \WC_Payment_Token::class);
		$token->shouldReceive('get_token')->andReturn('token12345');

		$tokens = Mockery::mock( 'overload:' . \WC_Payment_Tokens::class );
		$tokens->shouldReceive('get')->andReturn( $token );

		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->shouldReceive('get_status')->andReturn('processing');
		$wc_order->shouldReceive('get_transaction_id')->andReturn('ABC123');
		$wc_order->shouldReceive('get_payment_method')->andReturn(CreditCardGateway::ID);
		$wc_order->shouldReceive('get_payment_tokens')->andReturn(['token12345']);

		when('wc_get_order')->justReturn($wc_order);

		$this->assertSame(
			'ABC123',
			(new SubscriptionHelper())->previous_transaction($subscription, 'token12345')
		);
	}
}
