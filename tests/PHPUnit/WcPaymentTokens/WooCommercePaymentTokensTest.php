<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcPaymentTokens;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

/**
 * @scenario customer_tokens() looks up the PayPal customer id from usermeta and must not
 *           call payment_tokens_for_customer() when neither meta key holds one (e.g. after
 *           the usermeta row was deleted) — PayPal always rejects that request with 400. It
 *           bails out early instead, matching the same guard already used at the other two
 *           call sites of payment_tokens_for_customer() (PayPalGateway,
 *           PaymentMethodTokensChecker).
 */
class WooCommercePaymentTokensTest extends TestCase
{
	/**
	 * @var PaymentTokensEndpoint|Mockery\MockInterface
	 */
	private $payment_tokens_endpoint;

	private WooCommercePaymentTokens $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->payment_tokens_endpoint = Mockery::mock(PaymentTokensEndpoint::class);

		$this->sut = new WooCommercePaymentTokens(
			$this->payment_tokens_endpoint,
			Mockery::mock(LoggerInterface::class)
		);
	}

	/**
	 * Stubs get_user_meta() to return the given value only for the given meta key.
	 *
	 * @param array<string, string> $metaByKey Meta value keyed by meta key.
	 */
	private function stubUserMeta(array $metaByKey): void
	{
		when('get_user_meta')->alias(
			static fn (int $user_id, string $key, bool $single = false) => $metaByKey[$key] ?? ''
		);
	}

	public function testReturnsEmptyArrayWithoutCallingTheEndpointWhenNoCustomerIdIsStored()
	{
		// GIVEN a user with neither the current nor the legacy customer-id meta key set
		// (e.g. the usermeta row was deleted).
		$this->stubUserMeta([]);

		// WHEN the customer's PayPal tokens are requested.
		$result = $this->sut->customer_tokens(42);

		// THEN no request is made, and an empty array is returned as if there were none.
		$this->payment_tokens_endpoint->shouldNotHaveReceived('payment_tokens_for_customer');
		$this->assertSame([], $result);
	}

	public function testUsesTheCurrentCustomerIdMetaKeyWhenPresent()
	{
		// GIVEN a user with the current customer-id meta key set.
		$this->stubUserMeta(['_ppcp_target_customer_id' => 'CUST-1']);
		$tokens = [['id' => 'TOKEN-1']];
		$this->payment_tokens_endpoint
			->shouldReceive('payment_tokens_for_customer')
			->with('CUST-1')
			->andReturn($tokens);

		// WHEN the customer's PayPal tokens are requested.
		$result = $this->sut->customer_tokens(42);

		// THEN the tokens for that customer id are returned.
		$this->assertSame($tokens, $result);
	}

	public function testFallsBackToTheLegacyCustomerIdMetaKey()
	{
		// GIVEN a user with only the legacy customer-id meta key set.
		$this->stubUserMeta(['ppcp_customer_id' => 'CUST-LEGACY']);
		$tokens = [['id' => 'TOKEN-1']];
		$this->payment_tokens_endpoint
			->shouldReceive('payment_tokens_for_customer')
			->with('CUST-LEGACY')
			->andReturn($tokens);

		// WHEN the customer's PayPal tokens are requested.
		$result = $this->sut->customer_tokens(42);

		// THEN the tokens for the legacy customer id are returned.
		$this->assertSame($tokens, $result);
	}

	public function testReturnsEmptyArrayWhenTheEndpointFails()
	{
		// GIVEN a stored customer id, but the PayPal API call fails.
		$this->stubUserMeta(['_ppcp_target_customer_id' => 'CUST-1']);
		$this->payment_tokens_endpoint
			->shouldReceive('payment_tokens_for_customer')
			->andThrow(new RuntimeException('Bad Request'));

		// WHEN the customer's PayPal tokens are requested.
		$result = $this->sut->customer_tokens(42);

		// THEN the failure is treated the same as having no tokens.
		$this->assertSame([], $result);
	}

	/**
	 * Builds a customer token whose payment_source reports the given name, matching the
	 * shape returned by PaymentTokensEndpoint::payment_tokens_for_customer().
	 *
	 * @param string $payment_source_name Name reported by the token's payment source.
	 */
	private function tokenWithPaymentSourceName(string $payment_source_name): array
	{
		return ['payment_source' => Mockery::mock(['name' => $payment_source_name])];
	}

	/**
	 * GIVEN a customer whose stored payment tokens include one with the given payment
	 *      source name
	 * WHEN has_paypal_or_venmo_token() is called
	 * THEN it reports whether a PayPal or Venmo token is present among them
	 *
	 * @dataProvider payment_source_name_provider
	 */
	public function testHasPaypalOrVenmoTokenDetectsMatchingPaymentSource(string $payment_source_name, bool $expected)
	{
		$this->stubUserMeta(['_ppcp_target_customer_id' => 'CUST-1']);
		$this->payment_tokens_endpoint
			->shouldReceive('payment_tokens_for_customer')
			->with('CUST-1')
			->andReturn([$this->tokenWithPaymentSourceName($payment_source_name)]);

		$result = $this->sut->has_paypal_or_venmo_token(42);

		$this->assertSame($expected, $result);
	}

	public function payment_source_name_provider(): array
	{
		return [
			'paypal token present' => ['paypal', true],
			'venmo token present'  => ['venmo', true],
			'only a card token'    => ['card', false],
		];
	}

	public function testHasPaypalOrVenmoTokenReturnsFalseWhenThereIsNoResolvableCustomerId()
	{
		// GIVEN a user with no stored PayPal customer id, so no tokens can be looked up.
		$this->stubUserMeta([]);

		// WHEN checking whether the user has a saved PayPal or Venmo token.
		$result = $this->sut->has_paypal_or_venmo_token(42);

		// THEN the endpoint is never called and the user is treated as having no such token.
		$this->payment_tokens_endpoint->shouldNotHaveReceived('payment_tokens_for_customer');
		$this->assertFalse($result);
	}
}
