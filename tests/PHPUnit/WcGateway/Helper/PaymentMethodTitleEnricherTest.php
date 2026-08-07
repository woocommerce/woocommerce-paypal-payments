<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Mockery;
use WC_Order;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
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
	private const ICON_FILTER = 'woocommerce_paypal_payments_payment_method_title_icon';
	private const ENRICHED_FILTER = 'woocommerce_paypal_payments_enriched_payment_method_title';

	private const ASSET_BASE_URL = 'https://example.com/wp-content/plugins/woocommerce-paypal-payments/modules/ppcp-wc-gateway/assets/';

	private $testee;

	public function setUp(): void
	{
		parent::setUp();

		when('sanitize_email')->returnArg();

		$asset_getter = Mockery::mock( AssetGetter::class );
		$asset_getter->shouldReceive( 'get_static_asset_url' )
			->andReturnUsing(
				static function ( string $asset_name ): string {
					return self::ASSET_BASE_URL . $asset_name;
				}
			);

		$this->testee = new PaymentMethodTitleEnricher( $asset_getter );
	}

	/**
	 * Builds the expected icon URL for a given bundled icon file name,
	 * matching the mocked AssetGetter in setUp().
	 */
	private function iconUrl( string $file ): string
	{
		return self::ASSET_BASE_URL . "images/$file.svg";
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
	 * GIVEN a card payment source
	 * WHEN the icon URL is resolved for a mapped brand
	 * THEN the bundled icon file for that brand is returned
	 * AND the brand lookup is case-insensitive
	 *
	 * @dataProvider mappedCardBrandProvider
	 */
	public function testGetIconUrlResolvesMappedCardBrand( string $brand, string $expected_file ): void
	{
		self::assertSame(
			$this->iconUrl( $expected_file ),
			$this->testee->get_icon_url( 'card', $brand )
		);
	}

	public function mappedCardBrandProvider(): array
	{
		return array(
			'visa'                     => array( 'VISA', 'visa' ),
			'lower-case visa normalizes to upper case' => array( 'visa', 'visa' ),
			'mastercard'               => array( 'MASTERCARD', 'mastercard' ),
			'amex'                     => array( 'AMEX', 'amex' ),
			'american_express aliases to amex' => array( 'AMERICAN_EXPRESS', 'amex' ),
			'discover'                 => array( 'DISCOVER', 'discover' ),
			'jcb'                      => array( 'JCB', 'jcb' ),
			'elo'                      => array( 'ELO', 'elo' ),
			'hiper'                    => array( 'HIPER', 'hiper' ),
		);
	}

	/**
	 * GIVEN a card payment source
	 * WHEN the icon URL is resolved for a brand with no bundled icon
	 * THEN an empty string is returned
	 *
	 * @dataProvider unmappedCardBrandProvider
	 */
	public function testGetIconUrlReturnsEmptyStringForUnmappedCardBrand( string $brand ): void
	{
		self::assertSame( '', $this->testee->get_icon_url( 'card', $brand ) );
	}

	public function unmappedCardBrandProvider(): array
	{
		return array(
			'diners'        => array( 'DINERS' ),
			'maestro'       => array( 'MAESTRO' ),
			'solo'          => array( 'SOLO' ),
			'switch'        => array( 'SWITCH' ),
			'unionpay'      => array( 'UNIONPAY' ),
			'unknown brand' => array( 'FOO_BAR' ),
			'empty brand'   => array( '' ),
		);
	}

	/**
	 * GIVEN a payment source with its own bundled logo
	 * WHEN the icon URL is resolved
	 * THEN the source's own icon is returned regardless of card brand
	 * AND unsupported sources resolve to an empty string
	 */
	public function testGetIconUrlResolvesSourcesWithTheirOwnLogo(): void
	{
		self::assertSame( $this->iconUrl( 'paypal' ), $this->testee->get_icon_url( 'paypal', '' ) );
		self::assertSame( $this->iconUrl( 'venmo' ), $this->testee->get_icon_url( 'venmo', '' ) );
		self::assertSame( '', $this->testee->get_icon_url( 'bancontact', '' ) );
	}

	/**
	 * GIVEN a wallet source that carries an underlying card brand
	 * WHEN the icon URL is resolved
	 * THEN the icon of the underlying card brand is returned
	 */
	public function testGetIconUrlResolvesWalletSourcesToUnderlyingCardBrand(): void
	{
		self::assertSame(
			$this->iconUrl( 'mastercard' ),
			$this->testee->get_icon_url( 'apple_pay', 'MASTERCARD' )
		);
		self::assertSame(
			$this->iconUrl( 'visa' ),
			$this->testee->get_icon_url( 'google_pay', 'VISA' )
		);
	}

	/**
	 * GIVEN a payment source with its own bundled logo
	 * WHEN a card brand is also present
	 * THEN the source's own icon wins and the card brand is ignored
	 */
	public function testGetIconUrlSourceMapWinsOverCardBrand(): void
	{
		self::assertSame(
			$this->iconUrl( 'paypal' ),
			$this->testee->get_icon_url( 'paypal', 'VISA' )
		);
	}

	/**
	 * GIVEN a PayPal order
	 * WHEN no callback is registered on the icon filter
	 * THEN the title is enriched exactly as before the icon filter existed
	 */
	public function testEnrichIsUnchangedWhenNoIconFilterCallbackIsRegistered(): void
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
			$this->testee->enrich( 'PayPal', $order )
		);
	}

	/**
	 * GIVEN a PayPal order
	 * WHEN the icon filter returns markup
	 * THEN the markup is prepended to the detail, separated by a single space
	 */
	public function testIconFilterMarkupIsPrependedToDetail(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::ICON_FILTER )
			->once()
			->andReturn( '<img src="x.svg">' );

		self::assertSame(
			'PayPal (<img src="x.svg"> john@example.com)',
			$this->testee->enrich( 'PayPal', $order )
		);
	}

	/**
	 * GIVEN a PayPal order
	 * WHEN the title is enriched
	 * THEN the icon filter receives the resolved icon URL, the source, the empty brand, and the order
	 */
	public function testIconFilterReceivesAllArgumentsForPayPalOrder(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::ICON_FILTER )
			->once()
			->with( '', $this->iconUrl( 'paypal' ), 'paypal', '', $order )
			->andReturn( '' );

		self::assertSame(
			'PayPal (john@example.com)',
			$this->testee->enrich( 'PayPal', $order )
		);
	}

	/**
	 * GIVEN a card order with a mapped brand
	 * WHEN the title is enriched
	 * THEN the icon filter receives the resolved icon URL, the card source, the brand, and the order
	 */
	public function testIconFilterReceivesAllArgumentsForCardOrder(): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
			)
		);

		expectApplied( self::ICON_FILTER )
			->once()
			->with( '', $this->iconUrl( 'visa' ), 'card', 'VISA', $order )
			->andReturn( '' );

		self::assertSame(
			'Debit & Credit Cards (Visa ending in 1234)',
			$this->testee->enrich( 'Debit & Credit Cards', $order )
		);
	}

	/**
	 * GIVEN an Apple Pay order carrying an underlying card brand
	 * WHEN the title is enriched
	 * THEN the icon filter's source argument is "apple_pay" and its URL argument resolves the underlying brand
	 */
	public function testIconFilterReceivesUnderlyingBrandUrlForApplePayOrder(): void
	{
		$order = $this->makeOrder(
			ApplePayGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'apple_pay',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'MASTERCARD',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '5678',
			)
		);

		expectApplied( self::ICON_FILTER )
			->once()
			->with( '', $this->iconUrl( 'mastercard' ), 'apple_pay', 'MASTERCARD', $order )
			->andReturn( '' );

		self::assertSame(
			'Apple Pay (Mastercard ending in 5678)',
			$this->testee->enrich( 'Apple Pay', $order )
		);
	}

	/**
	 * GIVEN a card order whose brand has no bundled icon
	 * WHEN the title is enriched
	 * THEN the icon filter still receives the raw brand, but with an empty resolved icon URL
	 */
	public function testIconFilterReceivesEmptyUrlForUnmappedBrand(): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'MAESTRO',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
			)
		);

		expectApplied( self::ICON_FILTER )
			->once()
			->with( '', '', 'card', 'MAESTRO', $order )
			->andReturn( '' );

		self::assertSame(
			'Debit & Credit Cards (Maestro ending in 1234)',
			$this->testee->enrich( 'Debit & Credit Cards', $order )
		);
	}

	/**
	 * GIVEN a PayPal order
	 * WHEN the icon filter explicitly returns an empty string
	 * THEN the detail is left unprefixed
	 */
	public function testIconFilterReturningEmptyStringLeavesDetailUnprefixed(): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::ICON_FILTER )
			->once()
			->andReturn( '' );

		self::assertSame(
			'PayPal (john@example.com)',
			$this->testee->enrich( 'PayPal', $order )
		);
	}

	/**
	 * GIVEN a merchant has opted out of title enrichment
	 * WHEN the title is enriched
	 * THEN the icon filter never fires
	 */
	public function testIconFilterNeverFiresWhenOptedOut(): void
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

		expectApplied( self::ICON_FILTER )->never();

		self::assertSame( 'PayPal', $this->testee->enrich( 'PayPal', $order ) );
	}

	/**
	 * GIVEN an order placed through an unsupported gateway
	 * WHEN the title is enriched
	 * THEN the icon filter never fires
	 */
	public function testIconFilterNeverFiresForUnsupportedGateway(): void
	{
		$order = $this->makeOrder(
			'ppcp-card-button-gateway',
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
			)
		);

		expectApplied( self::ICON_FILTER )->never();

		self::assertSame(
			'Debit & Credit Cards',
			$this->testee->enrich( 'Debit & Credit Cards', $order )
		);
	}

	/**
	 * GIVEN a card order with no detail available
	 * WHEN the title is enriched
	 * THEN the icon filter never fires because there is no detail to prepend an icon onto
	 */
	public function testIconFilterNeverFiresWhenDetailIsEmpty(): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array( PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'card' )
		);

		expectApplied( self::ICON_FILTER )->never();

		self::assertSame(
			'Debit & Credit Cards',
			$this->testee->enrich( 'Debit & Credit Cards', $order )
		);
	}

	/**
	 * GIVEN a title that already contains the built detail
	 * WHEN the title is enriched
	 * THEN the dedupe guard short-circuits before the icon filter ever fires
	 */
	public function testIconFilterNeverFiresOnDedupeHit(): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
			)
		);

		expectApplied( self::ICON_FILTER )->never();

		$already_enriched = 'Debit & Credit Cards (Visa ending in 1234)';

		self::assertSame(
			$already_enriched,
			$this->testee->enrich( $already_enriched, $order )
		);
	}

	/**
	 * GIVEN a card title that already contains the icon markup and the detail
	 * WHEN the title is enriched again
	 * THEN the title is returned unchanged, with no double-prepended icon
	 * AND the icon filter never fires because the dedupe guard short-circuits first
	 */
	public function testNoDoublePrependOnReEnrichmentWithIcon(): void
	{
		$order = $this->makeOrder(
			CreditCardGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY  => 'card',
				PayPalGateway::ORDER_CARD_BRAND_META_KEY      => 'VISA',
				PayPalGateway::ORDER_CARD_LAST_DIGITS_META_KEY => '1234',
			)
		);

		$icon_markup = '<img src="' . $this->iconUrl( 'visa' ) . '">';
		$already_enriched = "Debit & Credit Cards ($icon_markup Visa ending in 1234)";

		expectApplied( self::ICON_FILTER )
			->never()
			->andReturn( $icon_markup );

		self::assertSame(
			$already_enriched,
			$this->testee->enrich( $already_enriched, $order )
		);
	}

	/**
	 * GIVEN a PayPal order
	 * WHEN the detail filter rewrites the detail and the icon filter supplies markup
	 * THEN the enriched-title filter receives the icon-prefixed detail as both the detail and part of the assembled title
	 */
	public function testEnrichedTitleFilterReceivesIconPrefixedDetail(): void
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
			->andReturn( 'B' );

		expectApplied( self::ICON_FILTER )
			->once()
			->andReturn( '<img src="i.svg">' );

		expectApplied( self::ENRICHED_FILTER )
			->once()
			->with( 'PayPal (<img src="i.svg"> B)', 'PayPal', '<img src="i.svg"> B', $order )
			->andReturnUsing(
				static function ( $enriched ) {
					return $enriched;
				}
			);

		self::assertSame(
			'PayPal (<img src="i.svg"> B)',
			$this->testee->enrich( 'PayPal', $order )
		);
	}

	/**
	 * GIVEN a PayPal order
	 * WHEN the icon filter returns a value that is cast via (string)
	 * THEN the resulting title reflects the casted value
	 *
	 * @dataProvider iconFilterCastProvider
	 */
	public function testIconFilterReturnValueIsCastToString( $filtered_icon, string $expected_title ): void
	{
		$order = $this->makeOrder(
			PayPalGateway::ID,
			array(
				PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY => 'paypal',
				PayPalGateway::ORDER_PAYER_EMAIL_META_KEY    => 'john@example.com',
			)
		);

		expectApplied( self::ICON_FILTER )
			->once()
			->andReturn( $filtered_icon );

		self::assertSame( $expected_title, $this->testee->enrich( 'PayPal', $order ) );
	}

	public function iconFilterCastProvider(): array
	{
		return array(
			'integer icon markup is cast to its string representation' => array( 12345, 'PayPal (12345 john@example.com)' ),
			'null icon casts to an empty string, detail stays unprefixed' => array( null, 'PayPal (john@example.com)' ),
			'false icon casts to an empty string, detail stays unprefixed' => array( false, 'PayPal (john@example.com)' ),
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
