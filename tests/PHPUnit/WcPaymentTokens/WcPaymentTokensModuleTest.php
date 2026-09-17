<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcPaymentTokens;

use Mockery;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use function Brain\Monkey\Filters\expectAdded as expectFilterAdded;

/**
 * @covers \WooCommerce\PayPalCommerce\WcPaymentTokens\WcPaymentTokensModule
 *
 * @scenario The block checkout builds a saved-token label itself as
 *           "<brand> ending in <last4>", falling back to "Saved token for <gateway id>"
 *           whenever either piece is missing. Wallet tokens (PayPal, Venmo) have no card
 *           number for last4, so without a workaround shoppers would see the raw gateway id
 *           ("Saved token for ppcp-gateway"). Passing the account email through the last4
 *           slot is a deliberate trick to land on the readable branch - it is not a bug and
 *           must not be "fixed" into a real last-4 digits lookup, since wallets don't have one.
 *           A companion `woocommerce_credit_card_type_labels` filter fixes the capitalisation
 *           WooCommerce's own label helper would otherwise apply to the "paypal" brand.
 */
class WcPaymentTokensModuleTest extends TestCase
{
	private $container;

	public function setUp(): void
	{
		parent::setUp();

		$this->container = Mockery::mock(ContainerInterface::class);
		$this->container->shouldReceive('get')
			->with('session.handler')
			->andReturn(Mockery::mock(SessionHandler::class));
	}

	/**
	 * Runs the module and returns the captured `woocommerce_payment_methods_list_item`
	 * callback so it can be invoked directly in assertions.
	 */
	private function captured_list_item_filter(): callable
	{
		$captured = null;

		expectFilterAdded('woocommerce_payment_methods_list_item')
			->once()
			->whenHappen(
				static function ( $callback ) use ( &$captured ) {
					$captured = $callback;
				}
			);

		( new WcPaymentTokensModule() )->run( $this->container );

		$this->assertIsCallable($captured);

		return $captured;
	}

	/**
	 * Runs the module and returns the captured `woocommerce_credit_card_type_labels`
	 * callback so it can be invoked directly in assertions.
	 */
	private function captured_card_type_labels_filter(): callable
	{
		$captured = null;

		expectFilterAdded('woocommerce_credit_card_type_labels')
			->once()
			->whenHappen(
				static function ( $callback ) use ( &$captured ) {
					$captured = $callback;
				}
			);

		( new WcPaymentTokensModule() )->run( $this->container );

		$this->assertIsCallable($captured);

		return $captured;
	}

	private function paypal_token(string $email = ''): PaymentTokenPayPal
	{
		$token = new PaymentTokenPayPal();
		if ($email) {
			$token->set_email($email);
		}
		return $token;
	}

	private function venmo_token(string $email = ''): PaymentTokenVenmo
	{
		$token = new PaymentTokenVenmo();
		if ($email) {
			$token->set_email($email);
		}
		return $token;
	}

	private function apple_pay_token(int $id): PaymentTokenApplePay
	{
		$token = new PaymentTokenApplePay();
		$token->set_id($id);
		return $token;
	}

	private function base_item(): array
	{
		return [
			'method' => [
				'brand' => '',
				'last4' => '',
			],
		];
	}

	/**
	 * GIVEN a saved PayPal token with an account email
	 * WHEN the payment methods list item is filtered
	 * THEN the brand is set to "PayPal" and the email is placed in last4, so the block
	 *      checkout's own "<brand> ending in <last4>" label reads the account email instead
	 *      of falling back to the raw gateway id
	 */
	public function testPaypalTokenWithEmailUsesEmailAsLast4(): void
	{
		$filter = $this->captured_list_item_filter();

		$result = $filter($this->base_item(), $this->paypal_token('shopper@example.com'));

		$this->assertSame('PayPal', $result['method']['brand']);
		$this->assertSame('shopper@example.com', $result['method']['last4']);
	}

	/**
	 * GIVEN a saved PayPal token that has no account email on file
	 * WHEN the payment methods list item is filtered
	 * THEN the brand is still set to "PayPal", but last4 is left untouched rather than being
	 *      overwritten with an empty string - an empty last4 would make WooCommerce render
	 *      "PayPal ending in " instead of falling back to a sensible label
	 */
	public function testPaypalTokenWithoutEmailDoesNotSetLast4(): void
	{
		$filter = $this->captured_list_item_filter();

		$item   = $this->base_item();
		$result = $filter($item, $this->paypal_token(''));

		$this->assertSame('PayPal', $result['method']['brand']);
		$this->assertSame($item['method']['last4'], $result['method']['last4']);
	}

	/**
	 * GIVEN a saved Venmo token with an account email
	 * WHEN the payment methods list item is filtered
	 * THEN the brand is set to "Venmo" and the email is placed in last4
	 */
	public function testVenmoTokenWithEmailUsesEmailAsLast4(): void
	{
		$filter = $this->captured_list_item_filter();

		$result = $filter($this->base_item(), $this->venmo_token('venmo-shopper@example.com'));

		$this->assertSame('Venmo', $result['method']['brand']);
		$this->assertSame('venmo-shopper@example.com', $result['method']['last4']);
	}

	/**
	 * GIVEN a saved Apple Pay token
	 * WHEN the payment methods list item is filtered
	 * THEN the brand identifies it by token id and last4 is left untouched, matching the
	 *      behaviour that predates the wallet-label workaround
	 */
	public function testApplePayTokenBrandIncludesTokenIdAndLeavesLast4Untouched(): void
	{
		$filter = $this->captured_list_item_filter();

		$item   = $this->base_item();
		$result = $filter($item, $this->apple_pay_token(7));

		$this->assertSame('ApplePay #7', $result['method']['brand']);
		$this->assertSame($item['method']['last4'], $result['method']['last4']);
	}

	/**
	 * GIVEN a payment token of a type the workaround does not target (e.g. a plain credit
	 *      card token)
	 * WHEN the payment methods list item is filtered
	 * THEN the item is returned unchanged
	 */
	public function testUnrelatedTokenTypeIsReturnedUnchanged(): void
	{
		$filter = $this->captured_list_item_filter();

		$token = new class extends \WC_Payment_Token {};

		$item   = $this->base_item();
		$result = $filter($item, $token);

		$this->assertSame($item, $result);
	}

	/**
	 * GIVEN the filter is invoked with arguments that don't match its documented shape
	 * WHEN the payment methods list item is filtered
	 * THEN the original $item is returned untouched instead of raising an error
	 *
	 * @dataProvider invalid_list_item_arguments_provider
	 */
	public function testInvalidArgumentsAreReturnedUnchanged($item, $payment_token): void
	{
		$filter = $this->captured_list_item_filter();

		$result = $filter($item, $payment_token);

		$this->assertSame($item, $result);
	}

	public function invalid_list_item_arguments_provider(): array
	{
		return [
			'item is not an array'            => ['not-an-array', new PaymentTokenPayPal()],
			'second argument is not a token'   => [['method' => ['brand' => '', 'last4' => '']], new \stdClass()],
		];
	}

	/**
	 * GIVEN WooCommerce's list of known credit card type labels
	 * WHEN the credit card type labels are filtered
	 * THEN a "PayPal" entry is added for the "paypal" key, without disturbing existing
	 *      entries - this exists because wc_get_credit_card_type_label() would otherwise
	 *      lowercase-then-ucfirst an unrecognised brand into "Paypal"
	 */
	public function testAddsPaypalLabelWhilePreservingExistingLabels(): void
	{
		$filter = $this->captured_card_type_labels_filter();

		$result = $filter(['visa' => 'Visa', 'mastercard' => 'MasterCard']);

		$this->assertSame('PayPal', $result['paypal']);
		$this->assertSame('Visa', $result['visa']);
		$this->assertSame('MasterCard', $result['mastercard']);
	}

	/**
	 * GIVEN the credit card type labels filter receives a value that isn't an array
	 * WHEN the filter runs
	 * THEN the value is returned untouched instead of raising an error
	 */
	public function testCardTypeLabelsFilterReturnsNonArrayUnchanged(): void
	{
		$filter = $this->captured_card_type_labels_filter();

		$result = $filter('not-an-array');

		$this->assertSame('not-an-array', $result);
	}
}
