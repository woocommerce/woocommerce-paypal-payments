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
 * @scenario Regression test for PCP-6731. customer_tokens() looked up the PayPal customer
 *           id from usermeta but never checked whether it actually found one before
 *           calling payment_tokens_for_customer(), so a user missing both meta keys (e.g.
 *           after the usermeta row was deleted) triggered a doomed
 *           GET .../v3/vault/payment-tokens?customer_id= request that PayPal rejects with
 *           400. It now bails out early, matching the same guard already used at the other
 *           two call sites of payment_tokens_for_customer() (PayPalGateway,
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
}
