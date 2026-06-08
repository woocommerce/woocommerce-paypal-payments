<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Mockery;
use WC_Order;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\when;

class PaymentMethodTitleEnricherTest extends TestCase
{
	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		when('sanitize_email')->returnArg();

		$this->testee = new PaymentMethodTitleEnricher();
	}

	public function testAppendsPayerEmailForPayPal(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		self::assertSame(
			'PayPal (john@example.com)',
			$this->testee->enrich('PayPal', $order)
		);
	}

	public function testPayPalWithoutEmailIsUnchanged(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array( PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal' )
		);

		self::assertSame( 'PayPal', $this->testee->enrich( 'PayPal', $order ) );
	}

	public function testAppendsCardDetailsForAcdc(): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
			)
		);

		self::assertSame(
			'Debit & Credit Cards (Visa ending in 1234)',
			$this->testee->enrich( 'Debit & Credit Cards', $order )
		);
	}

	/**
	 * @dataProvider brandProvider
	 */
	public function testNormalizesCardBrand( string $raw_brand, string $expected_label ): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => $raw_brand,
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '0005',
			)
		);

		self::assertSame(
			"Card ($expected_label ending in 0005)",
			$this->testee->enrich( 'Card', $order )
		);
	}

	public function brandProvider(): array
	{
		return array(
			'visa'             => array( 'VISA', 'Visa' ),
			'mastercard'       => array( 'MASTERCARD', 'Mastercard' ),
			'amex'             => array( 'AMEX', 'American Express' ),
			'american_express' => array( 'AMERICAN_EXPRESS', 'American Express' ),
			'unknown'          => array( 'FOO_BAR', 'Foo bar' ),
		);
	}

	public function testAppendsCardDetailsForApplePay(): void
	{
		$order = $this->makeOrder(
			ApplePayGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'apple_pay',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'MASTERCARD',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '5678',
			)
		);

		self::assertSame(
			'Apple Pay (Mastercard ending in 5678)',
			$this->testee->enrich( 'Apple Pay', $order )
		);
	}

	public function testAppendsCardDetailsForGooglePay(): void
	{
		$order = $this->makeOrder(
			GooglePayGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'google_pay',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '4242',
			)
		);

		self::assertSame(
			'Google Pay (Visa ending in 4242)',
			$this->testee->enrich( 'Google Pay', $order )
		);
	}

	public function testPartialCardDataIsUnchanged(): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY     => 'VISA',
			)
		);

		self::assertSame(
			'Debit & Credit Cards',
			$this->testee->enrich( 'Debit & Credit Cards', $order )
		);
	}

	public function testMissingCardMetaIsUnchanged(): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array( PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'card' )
		);

		self::assertSame(
			'Debit & Credit Cards',
			$this->testee->enrich( 'Debit & Credit Cards', $order )
		);
	}

	public function testUnsupportedGatewayIsUnchanged(): void
	{
		$order = $this->makeOrder(
			'ppcp-card-button-gateway',
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
			)
		);

		self::assertSame(
			'Debit & Credit Cards',
			$this->testee->enrich( 'Debit & Credit Cards', $order )
		);
	}

	public function testOptOutFilterDisablesEnrichment(): void
	{
		when('apply_filters')->justReturn(false);

		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		self::assertSame( 'PayPal', $this->testee->enrich( 'PayPal', $order ) );
	}

	public function testDoesNotAppendDetailTwice(): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
			)
		);

		$already_enriched = 'Debit & Credit Cards (Visa ending in 1234)';

		self::assertSame(
			$already_enriched,
			$this->testee->enrich( $already_enriched, $order )
		);
	}

	/**
	 * @param string                $gateway The order payment method id.
	 * @param array<string, string> $meta    Map of meta key => value.
	 * @return WC_Order&Mockery\MockInterface
	 */
	private function makeOrder( string $gateway, array $meta )
	{
		$order = Mockery::mock( \WC_Order::class );
		$order->shouldReceive( 'get_payment_method' )->andReturn( $gateway );
		$order->shouldReceive( 'get_meta' )->andReturnUsing(
			static function ( $key ) use ( $meta ) {
				return $meta[ $key ] ?? '';
			}
		);

		return $order;
	}
}
