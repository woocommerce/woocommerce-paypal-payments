<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WC_Order;
use WC_Session;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class RecordedQuoteTest extends TestCase {
	use MockeryPHPUnitIntegration;
	use StubsWcSession;

	private RecordedQuote $sut;

	public function setUp(): void {
		parent::setUp();

		// A plain, deterministic stand-in for wc_price(): keeps the two decimals the
		// comparison logic already normalized to, without formatting concerns.
		when( 'wc_price' )->alias(
			static fn( $amount, $args = array() ) => number_format( (float) $amount, 2, '.', '' )
		);
		when( 'wp_strip_all_tags' )->returnArg();

		$this->sut = new RecordedQuote();
	}

	private function stub_wc( ?WC_Session $session ): void {
		when( 'WC' )->justReturn( (object) array( 'session' => $session ) );
	}

	/**
	 * An order whose total, meta and notes are all backed by plain arrays, so what
	 * the SUT writes is visible to assertions the same way a real WC_Order persists it.
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

	// ---------------------------------------------------------------------
	// record() / apply_to_order()
	// ---------------------------------------------------------------------

	/**
	 * GIVEN the sheet quoted a higher total than the order ends up totaling
	 * WHEN the quote is recorded at authorization and later applied to the order
	 * THEN the order is annotated with a private note describing both totals
	 * AND the quoted total is stored as order meta
	 */
	public function test_recorded_quote_is_applied_to_the_order_when_totals_differ(): void {
		$store = array();
		$this->stub_wc( $this->session_with( $store ) );

		$this->sut->set( '12.00' );

		$meta  = array();
		$notes = array();
		$order = $this->order_with( '10.00', $meta, $notes );

		$this->sut->apply_to_order( $order );

		$this->assertSame( '12.00', $meta[ RecordedQuote::ORDER_META_KEY ] );
		$this->assertCount( 1, $notes );
		$this->assertStringContainsString( '12.00', $notes[0] );
		$this->assertStringContainsString( '10.00', $notes[0] );
	}

	/**
	 * GIVEN a quote already applied to one order
	 * WHEN a second order is later created for a different payment
	 * THEN applying the adjustment to that second order leaves it untouched, because
	 * the quote describes one payment and was consumed by the first order
	 */
	public function test_quote_is_consumed_so_a_second_order_does_not_inherit_it(): void {
		$store = array();
		$this->stub_wc( $this->session_with( $store ) );

		$this->sut->set( '12.00' );

		$first_meta  = array();
		$first_notes = array();
		$this->sut->apply_to_order( $this->order_with( '10.00', $first_meta, $first_notes ) );

		$second_meta  = array();
		$second_notes = array();
		$this->sut->apply_to_order( $this->order_with( '5.00', $second_meta, $second_notes ) );

		$this->assertArrayNotHasKey( RecordedQuote::ORDER_META_KEY, $second_meta );
		$this->assertSame( array(), $second_notes );
	}

	/**
	 * GIVEN a quoted total and an order total that differ only by float noise
	 * WHEN the quote is applied to the order
	 * THEN nothing is recorded, because the two amounts are equal once compared in
	 * integer cents
	 */
	public function test_apply_to_order_records_nothing_when_totals_are_equal_in_cents(): void {
		$store = array();
		$this->stub_wc( $this->session_with( $store ) );

		$this->sut->set( '19.99' );

		$meta  = array();
		$notes = array();
		$order = $this->order_with( '19.990000004', $meta, $notes );

		$this->sut->apply_to_order( $order );

		$this->assertSame( array(), $meta );
		$this->assertSame( array(), $notes );
	}

	// ---------------------------------------------------------------------
	// thank_you_message()
	// ---------------------------------------------------------------------

	/**
	 * GIVEN an order for which no reduction was ever recorded, or one whose total is
	 * not below the total the sheet quoted
	 * WHEN the order-received message is filtered
	 * THEN the message is returned unchanged
	 *
	 * @dataProvider unchanged_message_provider
	 */
	public function test_thank_you_message_is_unchanged( array $meta, string $total ): void {
		$order = $this->order_with( $total, $meta );

		$this->assertSame( 'Thank you.', $this->sut->thank_you_message( 'Thank you.', $order ) );
	}

	public function unchanged_message_provider(): array {
		return array(
			'no adjustment was ever recorded for this order' => array( array(), '10.00' ),
			'the order total equals the quoted total'        => array(
				array( RecordedQuote::ORDER_META_KEY => '10.00' ),
				'10.00',
			),
			'the order total is higher than the quoted total' => array(
				array( RecordedQuote::ORDER_META_KEY => '10.00' ),
				'12.00',
			),
		);
	}

	/**
	 * GIVEN an order whose recorded quote is higher than what was actually charged
	 * WHEN the order-received message is filtered
	 * THEN the original text is kept and a sentence explaining the reduction is
	 * appended, naming both totals and the difference between them
	 */
	public function test_thank_you_message_appends_a_reduction_notice_when_the_shopper_paid_less(): void {
		$meta  = array( RecordedQuote::ORDER_META_KEY => '12.00' );
		$order = $this->order_with( '10.00', $meta );

		$result = $this->sut->thank_you_message( 'Thank you.', $order );

		$this->assertStringStartsWith( 'Thank you.', $result );
		$this->assertStringContainsString( '12.00', $result );
		$this->assertStringContainsString( '10.00', $result );

		// Derived from the other two, so a wrong subtraction would contradict them.
		$this->assertStringContainsString( '2.00', $result );
	}
}
