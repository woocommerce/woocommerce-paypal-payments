<?php

namespace PHPUnit\Vaulting;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokenEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;

class PaymentTokenRepositoryTest extends TestCase
{

	public function testTokensContainsCardPaymentSource()
	{
		$paymentSource = Mockery::mock(PaymentSource::class);
		$paymentSource->shouldReceive('card->last_digits')->andReturn('1234');
		$paymentSource->shouldReceive('card->brand')->andReturn('VISA');

		$token = Mockery::mock(PaymentToken::class);
		$source = (object)[
			'card' => (object)[
				'last_digits' => '1234',
				'brand' => 'VISA',
			],
		];

		$token->shouldReceive('source')->andReturn($source);
		$tokens = [$token];

		$factory = Mockery::mock(PaymentTokenFactory::class);
		$endpoint = Mockery::mock(PaymentTokenEndpoint::class);
		$testee = new PaymentTokenRepository($factory, $endpoint);

		self::assertTrue($testee->tokens_contains_payment_source($tokens, $paymentSource));
	}
}
