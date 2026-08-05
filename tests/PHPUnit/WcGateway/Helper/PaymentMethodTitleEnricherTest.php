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
use function Brain\Monkey\Filters\expectApplied;

class PaymentMethodTitleEnricherTest extends TestCase
{
	private const OPT_OUT_FILTER = 'woocommerce_paypal_payments_enrich_payment_method_title';
	private const DETAIL_FILTER = 'woocommerce_paypal_payments_payment_method_title_detail';
	private const ENRICHED_FILTER = 'woocommerce_paypal_payments_enriched_payment_method_title';

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
	 * GIVEN a PayPal order with a payer email
	 * WHEN the detail filter rewrites the built detail
	 * THEN the rewritten detail is appended to the title
	 * AND the filter receives the built detail and the order
	 */
	public function testDetailFilterReceivesBuiltDetailAndAppendsFilteredValue(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::DETAIL_FILTER )
			->once()
			->with( 'john@example.com', $order )
			->andReturn( 'Verified: john@example.com' );

		self::assertSame(
			'PayPal (Verified: john@example.com)',
			$this->testee->enrich( 'PayPal', $order )
		);
	}

	/**
	 * GIVEN a PayPal order with a payer email
	 * WHEN the detail filter suppresses the detail by returning an empty string
	 * THEN the title stays unchanged even though enrichment itself is still enabled
	 */
	public function testEmptyDetailFilterReturnValueSuppressesAppend(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::DETAIL_FILTER )
			->once()
			->with( 'john@example.com', $order )
			->andReturn( '' );

		self::assertSame( 'PayPal', $this->testee->enrich( 'PayPal', $order ) );
	}

	/**
	 * GIVEN a supported gateway whose payment source yields no built-in detail
	 * WHEN the detail filter supplies a detail of its own
	 * THEN the filter is invoked with an empty built detail
	 * AND the supplied detail is appended to the title
	 */
	public function testDetailFilterCanSupplyDetailWhenSourceHasNone(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array( PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'venmo' )
		);

		expectApplied( self::DETAIL_FILTER )
			->once()
			->with( '', $order )
			->andReturn( '@johndoe' );

		self::assertSame(
			'PayPal (@johndoe)',
			$this->testee->enrich( 'PayPal', $order )
		);
	}

	/**
	 * GIVEN a card order missing the last-digits meta
	 * WHEN the detail filter supplies a detail of its own
	 * THEN the filter is invoked with an empty built detail
	 * AND the supplied detail is appended to the title
	 */
	public function testDetailFilterCanSupplyDetailForPartialCardData(): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY     => 'VISA',
			)
		);

		expectApplied( self::DETAIL_FILTER )
			->once()
			->with( '', $order )
			->andReturn( 'Card on file' );

		self::assertSame(
			'Debit & Credit Cards (Card on file)',
			$this->testee->enrich( 'Debit & Credit Cards', $order )
		);
	}

	/**
	 * GIVEN an order placed through an unsupported gateway
	 * WHEN the title is enriched
	 * THEN the detail filter never fires
	 * AND the title stays unchanged
	 */
	public function testDetailFilterNeverFiresForUnsupportedGateway(): void
	{
		$order = $this->makeOrder(
			'ppcp-card-button-gateway',
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
			)
		);

		expectApplied( self::DETAIL_FILTER )->never();

		self::assertSame(
			'Debit & Credit Cards',
			$this->testee->enrich( 'Debit & Credit Cards', $order )
		);
	}

	/**
	 * GIVEN a merchant has opted out of title enrichment
	 * WHEN the title is enriched
	 * THEN the detail filter never fires
	 * AND the title stays unchanged
	 */
	public function testDetailFilterNeverFiresWhenOptedOut(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::OPT_OUT_FILTER )
			->once()
			->with( true, $order )
			->andReturn( false );

		expectApplied( self::DETAIL_FILTER )->never();

		self::assertSame( 'PayPal', $this->testee->enrich( 'PayPal', $order ) );
	}

	/**
	 * GIVEN a card title that already contains the detail the filter returns
	 * WHEN the title is enriched
	 * THEN the duplicate-append guard compares against the FILTERED detail
	 * AND the title stays unchanged
	 */
	public function testDedupeGuardComparesAgainstFilteredDetail(): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
			)
		);

		$already_enriched = 'Debit & Credit Cards (Visa •••• 1234)';

		expectApplied( self::DETAIL_FILTER )
			->once()
			->andReturn( 'Visa •••• 1234' );

		self::assertSame(
			$already_enriched,
			$this->testee->enrich( $already_enriched, $order )
		);
	}

	/**
	 * GIVEN a title that already contains the built-in detail
	 * WHEN the detail filter rewrites the detail to a different value
	 * THEN the new detail is appended even though the title was previously enriched
	 */
	public function testFilteredDetailIsAppendedWhenItDiffersFromTitleContent(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::DETAIL_FILTER )
			->once()
			->andReturn( 'Verified' );

		self::assertSame(
			'PayPal (john@example.com) (Verified)',
			$this->testee->enrich( 'PayPal (john@example.com)', $order )
		);
	}

	/**
	 * GIVEN a PayPal order
	 * WHEN the detail filter returns a value that is cast via (string)
	 * THEN the resulting title reflects the casted value
	 *
	 * @dataProvider detailFilterCastProvider
	 */
	public function testDetailFilterReturnValueIsCastToString( $filtered_detail, string $expected_title ): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::DETAIL_FILTER )
			->once()
			->andReturn( $filtered_detail );

		self::assertSame( $expected_title, $this->testee->enrich( 'PayPal', $order ) );
	}

	public function detailFilterCastProvider(): array
	{
		return array(
			'integer detail is cast to its string representation' => array( 12345, 'PayPal (12345)' ),
			'null detail casts to an empty string, title unchanged' => array( null, 'PayPal' ),
			'false detail casts to an empty string, title unchanged' => array( false, 'PayPal' ),
		);
	}

	/**
	 * GIVEN a PayPal order
	 * WHEN the enriched-title filter rewrites the assembled title
	 * THEN its return value is used verbatim
	 */
	public function testEnrichedTitleFilterReturnValueIsUsedVerbatim(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::ENRICHED_FILTER )
			->once()
			->andReturn( 'PayPal — john@example.com' );

		self::assertSame(
			'PayPal — john@example.com',
			$this->testee->enrich( 'PayPal', $order )
		);
	}

	/**
	 * GIVEN a PayPal order
	 * WHEN the title is enriched
	 * THEN the enriched-title filter receives the assembled title, the original title, the detail, and the order
	 */
	public function testEnrichedTitleFilterReceivesAssembledTitleOriginalTitleDetailAndOrder(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::ENRICHED_FILTER )
			->once()
			->with( 'PayPal (john@example.com)', 'PayPal', 'john@example.com', $order )
			->andReturnUsing(
				static function ( $enriched ) {
					return $enriched;
				}
			);

		self::assertSame(
			'PayPal (john@example.com)',
			$this->testee->enrich( 'PayPal', $order )
		);
	}

	/**
	 * GIVEN a merchant has opted out of title enrichment
	 * WHEN the title is enriched
	 * THEN the enriched-title filter never fires
	 */
	public function testEnrichedTitleFilterNeverFiresWhenOptedOut(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::OPT_OUT_FILTER )
			->once()
			->andReturn( false );

		expectApplied( self::ENRICHED_FILTER )->never();

		self::assertSame( 'PayPal', $this->testee->enrich( 'PayPal', $order ) );
	}

	/**
	 * GIVEN one of several early-return scenarios
	 * WHEN the title is enriched
	 * THEN the enriched-title filter never fires because no detail is appended
	 *
	 * @dataProvider enrichedTitleNeverFiresProvider
	 */
	public function testEnrichedTitleFilterNeverFiresOnEarlyReturn( string $gateway, array $meta, string $title ): void
	{
		$order = $this->makeOrder( $gateway, $meta );

		expectApplied( self::ENRICHED_FILTER )->never();

		self::assertSame( $title, $this->testee->enrich( $title, $order ) );
	}

	public function enrichedTitleNeverFiresProvider(): array
	{
		return array(
			'unsupported gateway'          => array(
				'ppcp-card-button-gateway',
				array(
					PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
					PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
					PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
				),
				'Debit & Credit Cards',
			),
			'card order with no card meta' => array(
				CreditCardGateway::ID,
				array( PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'card' ),
				'Debit & Credit Cards',
			),
			'title already contains the built detail' => array(
				CreditCardGateway::ID,
				array(
					PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
					PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
					PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
				),
				'Debit & Credit Cards (Visa ending in 1234)',
			),
		);
	}

	/**
	 * GIVEN a PayPal order
	 * WHEN the enriched-title filter returns an empty string
	 * THEN the result is an empty string, with no fallback to the original title
	 */
	public function testEnrichedTitleFilterReturningEmptyStringYieldsEmptyString(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::ENRICHED_FILTER )
			->once()
			->andReturn( '' );

		self::assertSame( '', $this->testee->enrich( 'PayPal', $order ) );
	}

	/**
	 * GIVEN a PayPal order
	 * WHEN the detail filter rewrites the detail
	 * THEN the enriched-title filter receives the assembled title built from the rewritten detail
	 */
	public function testEnrichedTitleFilterReceivesTitleBuiltFromFilteredDetail(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::DETAIL_FILTER )
			->once()
			->with( 'john@example.com', $order )
			->andReturn( 'B' );

		expectApplied( self::ENRICHED_FILTER )
			->once()
			->with( 'PayPal (B)', 'PayPal', 'B', $order )
			->andReturnUsing(
				static function ( $enriched ) {
					return $enriched;
				}
			);

		self::assertSame(
			'PayPal (B)',
			$this->testee->enrich( 'PayPal', $order )
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
