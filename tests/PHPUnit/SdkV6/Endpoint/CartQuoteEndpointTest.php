<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Endpoint;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Psr\Log\LoggerInterface;
use WC_Cart;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\SdkV6\Helper\RecordedShippingRate;
use WooCommerce\PayPalCommerce\SdkV6\Helper\StubsWcSession;
use WooCommerce\PayPalCommerce\SdkV6\Helper\RecordedQuote;
use WooCommerce\PayPalCommerce\SdkV6\Helper\RecordedTaxBasis;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\Functions\expect;

class CartQuoteEndpointTest extends TestCase {
	use MockeryPHPUnitIntegration;
	use StubsWcSession;

	/**
	 * @var RequestData&Mockery\MockInterface
	 */
	private $request_data;

	/**
	 * @var AmountFactory&Mockery\MockInterface
	 */
	private $amount_factory;

	private RecordedShippingRate $recorded_rate;

	private RecordedTaxBasis $recorded_tax_basis;

	private RecordedQuote $recorded_quote;

	private CartQuoteEndpoint $sut;

	/**
	 * @var array<string, mixed>
	 */
	private array $session_state = array();

	/**
	 * What the amount_factory stub reports for from_wc_cart(), so one expectation
	 * covers every stub_cart() call instead of stacking a losing one per call.
	 *
	 * @var Amount&Mockery\MockInterface
	 */
	private $current_amount;

	public function setUp(): void {
		parent::setUp();

		$this->request_data     = Mockery::mock( RequestData::class );
		$this->amount_factory   = Mockery::mock( AmountFactory::class );
		$this->recorded_rate         = new RecordedShippingRate();
		$this->recorded_tax_basis        = new RecordedTaxBasis();
		$this->recorded_quote = new RecordedQuote();
		$logger                 = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		$this->amount_factory->allows( 'from_wc_cart' )->andReturnUsing(
			function () {
				return $this->current_amount;
			}
		);

		$this->sut = new CartQuoteEndpoint(
			$this->request_data,
			$this->amount_factory,
			$this->recorded_rate,
			$this->recorded_tax_basis,
			$this->recorded_quote,
			$logger
		);

		when( 'wc_get_price_decimals' )->justReturn( 2 );
		when( 'wc_format_decimal' )->justReturn( '0.00' );
		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		// A plain, deterministic stand-in for wc_price(): keeps the two decimals the
		// comparison logic already normalized to, without formatting concerns.
		when( 'wc_price' )->alias(
			static fn( $amount, $args = array() ) => number_format( (float) $amount, 2, '.', '' )
		);
		when( 'wp_strip_all_tags' )->returnArg();

		$this->stub_wc();
	}

	/**
	 * Wires up the WC() global. The real RecordedShippingRate and RecordedTaxBasis run
	 * against it, so the session has to persist what they write.
	 *
	 * @param array<string, string>|null $countries Country code => name pairs, as
	 *                                               WC()->countries->get_countries() returns them.
	 */
	private function stub_wc( ?WC_Cart $cart = null, ?array $countries = null ): void {
		$session = $this->session_with( $this->session_state );

		$shipping = Mockery::mock( 'WC_Shipping' );
		$shipping->allows( 'get_packages' )->andReturn( array() );

		$countries_provider = Mockery::mock( 'WC_Countries' );
		$countries_provider->allows( 'get_countries' )->andReturn( $countries ?? array() );

		$wc            = Mockery::mock( 'WooCommerce' );
		$wc->allows( 'shipping' )->andReturn( $shipping );
		$wc->session   = $session;
		$wc->cart      = $cart ?? $this->stub_cart( '0.00' );
		$wc->customer  = null;
		$wc->countries = $countries_provider;

		when( 'WC' )->justReturn( $wc );
	}

	/**
	 * A cart stub whose recomputed total, as reported by AmountFactory, is fixed
	 * to the given amount.
	 */
	private function stub_cart( string $total ): WC_Cart {
		$cart = Mockery::mock( 'WC_Cart' );
		$cart->allows( 'calculate_shipping' );
		$cart->allows( 'calculate_totals' );
		$cart->allows( 'needs_shipping' )->andReturn( false );

		$amount = Mockery::mock( Amount::class );
		$amount->allows( 'value_str' )->andReturn( $total );
		$amount->allows( 'breakdown' )->andReturn( null );

		$this->current_amount = $amount;

		return $cart;
	}

	private function stub_request( array $data ): void {
		$this->request_data->shouldReceive( 'read_request' )
			->with( CartQuoteEndpoint::nonce() )
			->andReturn( $data );
	}

	/**
	 * GIVEN a store's tax-basis configuration and any address already recorded
	 * from an earlier request in this payment
	 * WHEN a wallet shipping request carries the given addresses
	 * THEN the recorded tax basis afterwards is the expected address
	 *
	 * @dataProvider tax_basis_provider
	 */
	public function test_tax_basis_after_request( ?array $initial_basis, string $tax_based_on, array $request, array $expected ): void {
		when( 'get_option' )->justReturn( $tax_based_on );
		when( 'wp_send_json_success' )->justReturn( null );

		if ( $initial_basis ) {
			$this->recorded_tax_basis->set( $initial_basis );
		}

		$this->stub_request( $request );

		$this->sut->handle_request();

		$this->assertSame( $expected, $this->recorded_tax_basis->get() );
	}

	public function tax_basis_provider(): array {
		return array(
			'billing address recorded once it arrives at commit'               => array(
				null,
				'billing',
				array(
					'address'         => array(
						'country'  => 'FR',
						'postcode' => '75000',
					),
					'billing_address' => array(
						'country'  => 'DE',
						'postcode' => '10115',
					),
				),
				array(
					'country'  => 'DE',
					'postcode' => '10115',
				),
			),
			'destination stands in for the payer\'s address before authorization' => array(
				null,
				'billing',
				array(
					'address' => array(
						'country'  => 'FR',
						'postcode' => '75000',
					),
				),
				array(
					'country'  => 'FR',
					'postcode' => '75000',
				),
			),
			'a retry estimate never displaces the payer\'s already recorded address' => array(
				array(
					'country'  => 'DE',
					'postcode' => '10115',
				),
				'billing',
				array(
					'address' => array(
						'country'  => 'FR',
						'postcode' => '75000',
					),
				),
				array(
					'country'  => 'DE',
					'postcode' => '10115',
				),
			),
			'nothing is recorded when the store does not tax on the billing address' => array(
				null,
				'shipping',
				array(
					'address' => array(
						'country'  => 'FR',
						'postcode' => '75000',
					),
				),
				array(),
			),
		);
	}

	/**
	 * GIVEN a wallet request that quoted a price to the shopper
	 * WHEN the recomputed cart total is compared against the total the sheet
	 * displayed
	 * THEN the quote is answered unless the recomputed total is higher, and the
	 * comparison is made in integer cents regardless of the shop's own display
	 * precision
	 *
	 * @dataProvider price_guard_provider
	 */
	public function test_price_guard( string $expected_total, string $recomputed_total, int $price_decimals, bool $should_be_refused ): void {
		when( 'get_option' )->justReturn( 'shipping' );
		when( 'wc_get_price_decimals' )->justReturn( $price_decimals );

		$this->stub_wc( $this->stub_cart( $recomputed_total ) );
		$this->stub_request( array( 'expected_total' => $expected_total ) );

		if ( $should_be_refused ) {
			expect( 'wp_send_json_error' )
				->once()
				->with(
					Mockery::on(
						static function ( $payload ) {
							return isset( $payload['message'] ) && '' !== $payload['message'];
						}
					)
				);
			expect( 'wp_send_json_success' )->never();
		} else {
			expect( 'wp_send_json_success' )
				->once()
				->with(
					Mockery::on(
						static function ( $payload ) use ( $recomputed_total ) {
							return isset( $payload['total'] ) && $recomputed_total === $payload['total'];
						}
					)
				);
			expect( 'wp_send_json_error' )->never();
		}

		$this->sut->handle_request();
	}

	public function price_guard_provider(): array {
		return array(
			'total exactly equal to the sheet\'s displayed total proceeds'            => array( '10.00', '10.00', 2, false ),
			'total one cent higher than the sheet\'s displayed total is refused'      => array( '10.00', '10.01', 2, true ),
			'total lower than the sheet\'s displayed total proceeds'                  => array( '10.00', '9.99', 2, false ),
			'a one-cent overcharge is still caught on a zero-decimal shop, because AmountFactory always formats two decimals' => array( '100.00', '100.01', 0, true ),
		);
	}

	/**
	 * GIVEN a shopper chose a shipping rate, a tax basis was recorded for the open
	 * payment sheet, and an authorized quote was recorded for it
	 * WHEN the sheet is dismissed and sends a release request
	 * THEN neither the chosen rate, the tax basis, nor the price adjustment carries
	 * over to a later, ordinary checkout
	 */
	public function test_release_clears_every_record_of_the_payment(): void {
		$this->recorded_rate->set( 'flat_rate:1' );
		$this->recorded_tax_basis->set(
			array(
				'country'  => 'DE',
				'postcode' => '10115',
			)
		);
		$this->recorded_quote->set( '10.00' );

		$this->stub_request( array( 'release' => true ) );

		when( 'wp_send_json_success' )->justReturn( null );

		$this->sut->handle_request();

		$this->assertSame( '', $this->recorded_rate->get() );
		$this->assertSame( array(), $this->recorded_tax_basis->get() );

		$meta = array();
		$this->recorded_quote->apply_to_order( $this->order_with( '5.00', $meta ) );
		$this->assertSame( array(), $meta );
	}

	// ---------------------------------------------------------------------
	// price adjustment recording
	// ---------------------------------------------------------------------

	/**
	 * GIVEN a wallet request whose recomputed total matches the total the sheet
	 * posted as authorized
	 * WHEN the request is handled
	 * THEN the quote is recorded, so applying it afterward to the order created for
	 * this payment annotates the order with the difference from its actual total
	 */
	public function test_authorized_quote_is_recorded_when_expected_total_is_posted(): void {
		when( 'get_option' )->justReturn( 'shipping' );
		when( 'wp_send_json_success' )->justReturn( null );

		$this->stub_wc( $this->stub_cart( '10.00' ) );
		$this->stub_request( array( 'expected_total' => '10.00' ) );

		$this->sut->handle_request();

		$meta  = array();
		$notes = array();
		$this->recorded_quote->apply_to_order( $this->order_with( '9.00', $meta, $notes ) );

		$this->assertSame( '10.00', $meta[ RecordedQuote::ORDER_META_KEY ] );
		$this->assertCount( 1, $notes );
	}

	/**
	 * GIVEN a wallet request that posts no authorized total, e.g. an in-sheet
	 * recalculation before the shopper authorizes
	 * WHEN the request is handled
	 * THEN nothing is recorded, so applying the adjustment to the order created for
	 * this payment leaves it unannotated
	 */
	public function test_no_quote_is_recorded_when_expected_total_is_absent(): void {
		when( 'get_option' )->justReturn( 'shipping' );
		when( 'wp_send_json_success' )->justReturn( null );

		$this->stub_wc( $this->stub_cart( '10.00' ) );
		$this->stub_request( array() );

		$this->sut->handle_request();

		$meta  = array();
		$notes = array();
		$this->recorded_quote->apply_to_order( $this->order_with( '9.00', $meta, $notes ) );

		$this->assertSame( array(), $meta );
		$this->assertSame( array(), $notes );
	}

	// ---------------------------------------------------------------------
	// refusal message
	// ---------------------------------------------------------------------

	/**
	 * GIVEN a billing address whose country WooCommerce has a translated name for,
	 * and a recomputed total that exceeds the total the sheet quoted
	 * WHEN the request is handled
	 * THEN the shopper is refused, and the message names the country and the
	 * corrected total
	 */
	public function test_refusal_message_names_a_country_known_to_woocommerce(): void {
		when( 'get_option' )->justReturn( 'shipping' );

		$this->stub_wc( $this->stub_cart( '10.01' ), array( 'DE' => 'Germany' ) );
		$this->stub_request(
			array(
				'expected_total'  => '10.00',
				'billing_address' => array( 'country' => 'DE' ),
			)
		);

		$captured_message = '';
		expect( 'wp_send_json_error' )
			->once()
			->with(
				Mockery::on(
					static function ( $payload ) use ( &$captured_message ) {
						$captured_message = $payload['message'] ?? '';

						return true;
					}
				)
			);

		$this->sut->handle_request();

		$this->assertStringContainsString( 'Germany', $captured_message );
		$this->assertStringContainsString( '10.01', $captured_message );
	}

	/**
	 * GIVEN a billing address whose country code WooCommerce has no translated name
	 * for, and a recomputed total that exceeds the total the sheet quoted
	 * WHEN the request is handled
	 * THEN the shopper is refused, and the message falls back to the raw country
	 * code
	 */
	public function test_refusal_message_falls_back_to_the_raw_country_code_when_unknown(): void {
		when( 'get_option' )->justReturn( 'shipping' );

		$this->stub_wc( $this->stub_cart( '10.01' ), array( 'DE' => 'Germany' ) );
		$this->stub_request(
			array(
				'expected_total'  => '10.00',
				'billing_address' => array( 'country' => 'ZZ' ),
			)
		);

		$captured_message = '';
		expect( 'wp_send_json_error' )
			->once()
			->with(
				Mockery::on(
					static function ( $payload ) use ( &$captured_message ) {
						$captured_message = $payload['message'] ?? '';

						return true;
					}
				)
			);

		$this->sut->handle_request();

		$this->assertStringContainsString( 'ZZ', $captured_message );
		$this->assertStringContainsString( '10.01', $captured_message );
	}

	/**
	 * An order whose total, meta and notes are all backed by plain arrays, so what
	 * the price adjustment writes is visible to assertions the same way a real
	 * WC_Order persists it.
	 */
	private function order_with( string $total, array &$meta = array(), array &$notes = array() ): WC_Order {
		$order = Mockery::mock( WC_Order::class );

		$order->allows( 'get_total' )->andReturn( $total );
		$order->allows( 'get_currency' )->andReturn( 'USD' );

		$order->allows( 'get_meta' )->andReturnUsing(
			static fn( string $key ) => $meta[ $key ] ?? ''
		);

		$order->allows( 'update_meta_data' )->andReturnUsing(
			static function ( string $key, $value ) use ( &$meta ): void {
				$meta[ $key ] = $value;
			}
		);

		$order->allows( 'add_order_note' )->andReturnUsing(
			static function ( string $note ) use ( &$notes ): void {
				$notes[] = $note;
			}
		);

		$order->allows( 'save' );

		return $order;
	}
}
