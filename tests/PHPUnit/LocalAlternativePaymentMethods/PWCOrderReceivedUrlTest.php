<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Mockery;
use WC_Order;
use WooCommerce\PayPalCommerce\TestCase;

class PWCOrderReceivedUrlTest extends TestCase
{
	private const ORDER_KEY = 'wc_order_abc123';
	private const ORDER_RECEIVED_URL = 'https://example.com/checkout/order-received/42/';

	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		$this->testee = new PWCOrderReceivedUrl();
	}

	/**
	 * @param mixed $order_key The value the order reports as its key.
	 */
	private function order($order_key = self::ORDER_KEY): WC_Order
	{
		$order = Mockery::mock(WC_Order::class);
		$order->shouldReceive('get_order_key')->andReturn($order_key);
		$order->shouldReceive('get_checkout_order_received_url')
			->andReturn(self::ORDER_RECEIVED_URL . '?key=' . self::ORDER_KEY);

		return $order;
	}

	/**
	 * GIVEN a Pay with Crypto order and a request carrying the order key plus PayPal's token, PayerID, errorcode and an arbitrary argument
	 * WHEN the order-received URL is built
	 * THEN it is the order's own received URL with the key
	 * AND none of the request parameters are carried over.
	 */
	public function test_returns_the_orders_received_url_unchanged_when_request_has_the_order_key(): void
	{
		$url = $this->testee->without_token(
			$this->order(),
			[
				'key'       => self::ORDER_KEY,
				'token'     => 'PAYPAL-TOKEN',
				'PayerID'   => 'ABC',
				'errorcode' => 'E1',
				'foo'       => 'bar',
			]
		);

		$this->assertSame(self::ORDER_RECEIVED_URL . '?key=' . self::ORDER_KEY, $url);
	}

	/**
	 * GIVEN a Pay with Crypto order and a request that does not prove knowledge of the order key
	 * WHEN the order-received URL is built
	 * THEN no URL is returned, so the order key is never disclosed.
	 *
	 * @dataProvider requests_without_valid_key_provider
	 */
	public function test_returns_null_when_request_lacks_the_order_key(array $params): void
	{
		$this->assertNull($this->testee->without_token($this->order(), $params));
	}

	public function requests_without_valid_key_provider(): array
	{
		return [
			'key is missing'             => [['token' => 'PAYPAL-TOKEN', 'PayerID' => 'ABC']],
			'key is empty'               => [['key' => '', 'token' => 'PAYPAL-TOKEN']],
			'key is wrong'               => [['key' => 'wc_order_other', 'token' => 'PAYPAL-TOKEN']],
			'key is a prefix of the real key' => [['key' => 'wc_order_abc', 'token' => 'PAYPAL-TOKEN']],
		];
	}

	/**
	 * GIVEN a Pay with Crypto order that has no key of its own
	 * WHEN the order-received URL is built for a request whose key is missing or empty
	 * THEN no URL is returned, as two empty keys must not count as a match.
	 *
	 * @dataProvider requests_with_absent_key_provider
	 */
	public function test_returns_null_when_order_key_is_empty(array $params): void
	{
		$this->assertNull($this->testee->without_token($this->order(''), $params));
	}

	/**
	 * GIVEN a Pay with Crypto order whose key is filtered into a non-string value
	 * WHEN the order-received URL is built for a request whose key is missing or empty
	 * THEN no URL is returned
	 * AND no TypeError is raised.
	 *
	 * @dataProvider non_string_order_key_provider
	 * @param mixed $order_key
	 */
	public function test_returns_null_without_error_when_order_key_is_not_a_string($order_key, array $params): void
	{
		$this->assertNull($this->testee->without_token($this->order($order_key), $params));
	}

	public function requests_with_absent_key_provider(): array
	{
		return [
			'request key is missing' => [['token' => 'PAYPAL-TOKEN']],
			'request key is empty'   => [['key' => '', 'token' => 'PAYPAL-TOKEN']],
		];
	}

	public function non_string_order_key_provider(): array
	{
		return [
			'order key null, request key missing'  => [null, ['token' => 'PAYPAL-TOKEN']],
			'order key null, request key empty'    => [null, ['key' => '']],
			'order key false, request key missing' => [false, ['token' => 'PAYPAL-TOKEN']],
			'order key false, request key empty'   => [false, ['key' => '']],
		];
	}
}
