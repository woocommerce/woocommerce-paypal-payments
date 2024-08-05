<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class OrdersTest extends TestCase
{
	public function test_create() {
		$bearer = Mockery::mock(Bearer::class);
		$token = Mockery::mock(Token::class);
		$token->shouldReceive('token')->andReturn('');
		$bearer->shouldReceive('bearer')->andReturn($token);

		$sut = new Orders('', $bearer);

		expect('wp_remote_get')->andReturn([]);

		$this->assertEquals([], $sut->create([]));
	}
}
