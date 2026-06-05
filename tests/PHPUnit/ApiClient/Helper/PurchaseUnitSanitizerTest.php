<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * Tests for PurchaseUnitSanitizer, in extra_line mode the items must always
 * reconcile to item_total and never be silently ditched on a negative mismatch.
 */
class PurchaseUnitSanitizerTest extends TestCase {

	public function test_positive_mismatch_adds_extra_line_without_ditching(): void {
		$sanitizer = new PurchaseUnitSanitizer( PurchaseUnitSanitizer::MODE_EXTRA_LINE );

		// Items sum to 25.89 but item_total is 25.91 (positive mismatch of 0.02).
		$purchase_unit = $this->purchase_unit( array( $this->item( 8.63, 3 ) ), 25.91 );

		$result = $sanitizer->sanitize( $purchase_unit );

		$this->assertArrayHasKey( 'items', $result, 'Items must not be ditched.' );
		$this->assertCount( 2, $result['items'], 'An extra correction line should be added.' );

		$extra = $result['items'][1];
		$this->assertSame( PurchaseUnitSanitizer::EXTRA_LINE_NAME, $extra['name'] );
		$this->assertSame( '0.02', $extra['unit_amount']['value'] );
		$this->assertSame(
			'Item amount mismatch. Extra line added.',
			$sanitizer->get_last_message()
		);
	}

	public function test_negative_mismatch_reconciles_without_ditching(): void {
		$sanitizer = new PurchaseUnitSanitizer( PurchaseUnitSanitizer::MODE_EXTRA_LINE );

		// Items sum to 25.92 but item_total is 25.90 (negative mismatch of -0.02).
		$purchase_unit = $this->purchase_unit( array( $this->item( 8.64, 3 ) ), 25.90 );

		$result = $sanitizer->sanitize( $purchase_unit );

		$this->assertArrayHasKey( 'items', $result, 'Items must not be ditched on a negative mismatch.' );

		// The base item is floored from 8.64 to 8.63 (sum 25.89), then a 0.01
		// correction line reconciles the basket back to the 25.90 item_total.
		$this->assertSame( '8.63', $result['items'][0]['unit_amount']['value'] );
		$this->assertCount( 2, $result['items'] );
		$this->assertSame( PurchaseUnitSanitizer::EXTRA_LINE_NAME, $result['items'][1]['name'] );
		$this->assertSame( '0.01', $result['items'][1]['unit_amount']['value'] );
	}

	public function test_negative_mismatch_requiring_multiple_reductions_does_not_ditch(): void {
		$sanitizer = new PurchaseUnitSanitizer( PurchaseUnitSanitizer::MODE_EXTRA_LINE );

		// Two qty-1 items sum to 0.68 but item_total is 0.65 (mismatch -0.03).
		// A single flooring pass would only reach 0.66 and previously ditched the
		// whole basket; the bounded reduction loop must drive it exactly to 0.65.
		$purchase_unit = $this->purchase_unit(
			array( $this->item( 0.34, 1 ), $this->item( 0.34, 1 ) ),
			0.65
		);

		$result = $sanitizer->sanitize( $purchase_unit );

		$this->assertArrayHasKey( 'items', $result, 'Items must not be ditched.' );

		$item_sum = 0.0;
		foreach ( $result['items'] as $item ) {
			$item_sum += (float) $item['unit_amount']['value'] * (float) $item['quantity'];
		}
		$this->assertSame( 0.65, round( $item_sum, 2 ), 'Items must reconcile to item_total.' );
	}

	public function test_ditch_mode_drops_items_on_mismatch(): void {
		$sanitizer = new PurchaseUnitSanitizer( PurchaseUnitSanitizer::MODE_DITCH );

		$purchase_unit = $this->purchase_unit( array( $this->item( 8.63, 3 ) ), 25.91 );

		$result = $sanitizer->sanitize( $purchase_unit );

		$this->assertArrayNotHasKey( 'items', $result, 'Ditch mode must drop the items.' );
		$this->assertSame(
			'Item amount mismatch. Items ditched.',
			$sanitizer->get_last_message()
		);
	}

	public function test_matching_amounts_are_left_untouched(): void {
		$sanitizer = new PurchaseUnitSanitizer( PurchaseUnitSanitizer::MODE_EXTRA_LINE );

		$purchase_unit = $this->purchase_unit( array( $this->item( 8.63, 3 ) ), 25.89 );

		$result = $sanitizer->sanitize( $purchase_unit );

		$this->assertCount( 1, $result['items'], 'No correction line should be added when amounts match.' );
		$this->assertSame( '', $sanitizer->get_last_message() );
	}

	/**
	 * Builds a minimal purchase_unit array with a single item line and a matching
	 * breakdown total, so only the item-amount mismatch is exercised.
	 *
	 * @param array  $items The items array.
	 * @param float  $item_total The breakdown item_total / amount value.
	 * @param string $currency The currency code.
	 * @return array
	 */
	private function purchase_unit( array $items, float $item_total, string $currency = 'USD' ): array {
		$value = number_format( $item_total, 2, '.', '' );

		return array(
			'reference_id' => 'default',
			'amount'       => array(
				'currency_code' => $currency,
				'value'         => $value,
				'breakdown'     => array(
					'item_total' => array(
						'currency_code' => $currency,
						'value'         => $value,
					),
				),
			),
			'items'        => $items,
		);
	}

	/**
	 * Builds a single item line.
	 *
	 * @param float  $unit The unit amount.
	 * @param int    $quantity The quantity.
	 * @param string $currency The currency code.
	 * @return array
	 */
	private function item( float $unit, int $quantity, string $currency = 'USD' ): array {
		return array(
			'name'        => 'simple',
			'unit_amount' => array(
				'currency_code' => $currency,
				'value'         => number_format( $unit, 2, '.', '' ),
			),
			'quantity'    => $quantity,
			'category'    => Item::PHYSICAL_GOODS,
		);
	}
}
