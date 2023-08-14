<?php
/**
 * Class PurchaseUnitSanitizer.
 *
 * Sanitizes a purchase_unit array to be consumed by PayPal.
 *
 * All money values send to PayPal can only have 2 decimal points. WooCommerce internally does
 * not have this restriction. Therefore, the totals of the cart in WooCommerce and the totals
 * of the rounded money values of the items, we send to PayPal, can differ. In those case we either:
 * - Add an extra line with roundings.
 * - Don't send the line items.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Item;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;

/**
 * Class PurchaseUnitSanitizer
 */
class PurchaseUnitSanitizer {
	const MODE_DITCH      = 'ditch';
	const MODE_EXTRA_LINE = 'extra_line';
	const VALID_MODES     = array(
		self::MODE_DITCH,
		self::MODE_EXTRA_LINE,
	);

	const EXTRA_LINE_NAME = 'Subtotal mismatch';

	/**
	 * The purchase unit data
	 *
	 * @var array
	 */
	private $purchase_unit = array();

	/**
	 * Whether to allow items to be ditched.
	 *
	 * @var bool
	 */
	private $allow_ditch_items = true;

	/**
	 * The working mode
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * The name for the extra line
	 *
	 * @var string
	 */
	private $extra_line_name;

	/**
	 * The last message. To be added to order notes.
	 *
	 * @var string
	 */
	private $last_message = '';

	/**
	 * If the items and breakdown has been ditched.
	 *
	 * @var bool
	 */
	private $has_ditched_items_breakdown = false;

	/**
	 * PurchaseUnitSanitizer constructor.
	 *
	 * @param string|null $mode The mismatch handling mode, ditch or extra_line.
	 * @param string|null $extra_line_name The name of the extra line.
	 */
	public function __construct( string $mode = null, string $extra_line_name = null ) {

		if ( ! in_array( $mode, self::VALID_MODES, true ) ) {
			$mode = self::MODE_DITCH;
		}

		if ( ! $extra_line_name ) {
			$extra_line_name = self::EXTRA_LINE_NAME;
		}

		$this->mode            = $mode;
		$this->extra_line_name = $extra_line_name;
	}

	/**
	 * The purchase_unit amount.
	 *
	 * @return array
	 */
	private function amount(): array {
		return $this->purchase_unit['amount'] ?? array();
	}

	/**
	 * The purchase_unit currency code.
	 *
	 * @return string
	 */
	private function currency_code(): string {
		return (string) ( $this->amount()['currency_code'] ?? '' );
	}

	/**
	 * The purchase_unit breakdown.
	 *
	 * @return array
	 */
	private function breakdown(): array {
		return $this->amount()['breakdown'] ?? array();
	}

	/**
	 * The purchase_unit breakdown.
	 *
	 * @param string $key The breakdown element to get the value from.
	 * @return float
	 */
	private function breakdown_value( string $key ): float {
		if ( ! isset( $this->breakdown()[ $key ] ) ) {
			return 0.0;
		}
		return (float) ( $this->breakdown()[ $key ]['value'] ?? 0.0 );
	}

	/**
	 * The purchase_unit items array.
	 *
	 * @return array
	 */
	private function items(): array {
		return $this->purchase_unit['items'] ?? array();
	}

	/**
	 * The sanitizes the purchase_unit array.
	 *
	 * @param array $purchase_unit The purchase_unit array that should be sanitized.
	 * @param bool  $allow_ditch_items Whether to allow items to be ditched.
	 * @return array
	 */
	public function sanitize( array $purchase_unit, bool $allow_ditch_items = true ): array {
		$this->purchase_unit               = $purchase_unit;
		$this->allow_ditch_items           = $allow_ditch_items;
		$this->has_ditched_items_breakdown = false;

		$this->sanitize_item_amount_mismatch();
		$this->sanitize_item_tax_mismatch();
		$this->sanitize_breakdown_mismatch();
		return $this->purchase_unit;
	}

	/**
	 * The sanitizes the purchase_unit items amount.
	 *
	 * @return void
	 */
	private function sanitize_item_amount_mismatch(): void {
		$item_mismatch = $this->calculate_item_mismatch();

		if ( $this->mode === self::MODE_EXTRA_LINE ) {
			if ( $item_mismatch < 0 ) {

				// Do floors on item amounts so item_mismatch is a positive value.
				foreach ( $this->purchase_unit['items'] as $index => $item ) {
					// Get a more intelligent adjustment mechanism.
					$increment = ( new MoneyFormatter() )->minimum_increment( $item['unit_amount']['currency_code'] );

					$this->purchase_unit['items'][ $index ]['unit_amount'] = ( new Money(
						( (float) $item['unit_amount']['value'] ) - $increment,
						$item['unit_amount']['currency_code']
					) )->to_array();
				}
			}

			$item_mismatch = $this->calculate_item_mismatch();

			if ( $item_mismatch > 0 ) {
				// Add extra line item with roundings.
				$line_name                      = $this->extra_line_name;
				$roundings_money                = new Money( $item_mismatch, $this->currency_code() );
				$this->purchase_unit['items'][] = ( new Item( $line_name, $roundings_money, 1 ) )->to_array();

				$this->set_last_message(
					__( 'Item amount mismatch. Extra line added.', 'woocommerce-paypal-payments' )
				);
			}

			$item_mismatch = $this->calculate_item_mismatch();
		}

		if ( $item_mismatch !== 0.0 ) {
			// Ditch items.
			if ( $this->allow_ditch_items && isset( $this->purchase_unit['items'] ) ) {
				unset( $this->purchase_unit['items'] );
				$this->set_last_message(
					__( 'Item amount mismatch. Items ditched.', 'woocommerce-paypal-payments' )
				);
			}
		}
	}

	/**
	 * The sanitizes the purchase_unit items tax.
	 *
	 * @return void
	 */
	private function sanitize_item_tax_mismatch(): void {
		$tax_mismatch = $this->calculate_tax_mismatch();

		if ( $this->allow_ditch_items && $tax_mismatch !== 0.0 ) {
			// Unset tax in items.
			foreach ( $this->purchase_unit['items'] as $index => $item ) {
				if ( isset( $this->purchase_unit['items'][ $index ]['tax'] ) ) {
					unset( $this->purchase_unit['items'][ $index ]['tax'] );
				}
				if ( isset( $this->purchase_unit['items'][ $index ]['tax_rate'] ) ) {
					unset( $this->purchase_unit['items'][ $index ]['tax_rate'] );
				}
			}
		}
	}

	/**
	 * The sanitizes the purchase_unit breakdown.
	 *
	 * @return void
	 */
	private function sanitize_breakdown_mismatch(): void {
		$breakdown_mismatch = $this->calculate_breakdown_mismatch();

		if ( $this->allow_ditch_items && $breakdown_mismatch !== 0.0 ) {
			// Ditch breakdowns and items.
			if ( isset( $this->purchase_unit['items'] ) ) {
				unset( $this->purchase_unit['items'] );
			}
			if ( isset( $this->purchase_unit['amount']['breakdown'] ) ) {
				unset( $this->purchase_unit['amount']['breakdown'] );
			}

			$this->has_ditched_items_breakdown = true;
			$this->set_last_message(
				__( 'Breakdown mismatch. Items and breakdown ditched.', 'woocommerce-paypal-payments' )
			);
		}
	}

	/**
	 * The calculates amount mismatch of items sums with breakdown.
	 *
	 * @return float
	 */
	private function calculate_item_mismatch(): float {
		$item_total = $this->breakdown_value( 'item_total' );
		if ( ! $item_total ) {
			return 0;
		}

		$remaining_item_total = array_reduce(
			$this->items(),
			function ( float $total, array $item ): float {
				return $total - (float) $item['unit_amount']['value'] * (float) $item['quantity'];
			},
			$item_total
		);

		return round( $remaining_item_total, 2 );
	}

	/**
	 * The calculates tax mismatch of items sums with breakdown.
	 *
	 * @return float
	 */
	private function calculate_tax_mismatch(): float {
		$tax_total      = $this->breakdown_value( 'tax_total' );
		$items_with_tax = array_filter(
			$this->items(),
			function ( array $item ): bool {
				return isset( $item['tax'] );
			}
		);

		if ( ! $tax_total || empty( $items_with_tax ) ) {
			return 0;
		}

		$remaining_tax_total = array_reduce(
			$this->items(),
			function ( float $total, array $item ): float {
				$tax = $item['tax'] ?? false;
				if ( $tax ) {
					$total -= (float) $tax['value'] * (float) $item['quantity'];
				}
				return $total;
			},
			$tax_total
		);

		return round( $remaining_tax_total, 2 );
	}

	/**
	 * The calculates mismatch of breakdown sums with total amount.
	 *
	 * @return float
	 */
	private function calculate_breakdown_mismatch(): float {
		$breakdown = $this->breakdown();
		if ( ! $breakdown ) {
			return 0;
		}

		$amount_total  = 0.0;
		$amount_total += $this->breakdown_value( 'item_total' );
		$amount_total += $this->breakdown_value( 'tax_total' );
		$amount_total += $this->breakdown_value( 'shipping' );
		$amount_total -= $this->breakdown_value( 'discount' );
		$amount_total -= $this->breakdown_value( 'shipping_discount' );
		$amount_total += $this->breakdown_value( 'handling' );
		$amount_total += $this->breakdown_value( 'insurance' );

		$amount_str       = $this->amount()['value'] ?? 0;
		$amount_total_str = ( new Money( $amount_total, $this->currency_code() ) )->value_str();

		return $amount_str - $amount_total_str;
	}

	/**
	 * Indicates if the items and breakdown were ditched.
	 *
	 * @return bool
	 */
	public function has_ditched_items_breakdown(): bool {
		return $this->has_ditched_items_breakdown;
	}

	/**
	 * Returns the last sanitization message.
	 *
	 * @return string
	 */
	public function get_last_message(): string {
		return $this->last_message;
	}

	/**
	 * Set the last sanitization message.
	 *
	 * @param string $message The message.
	 */
	public function set_last_message( string $message ): void {
		$this->last_message = $message;
	}

}
