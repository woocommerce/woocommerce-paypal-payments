<?php
/**
 * Currency Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\CurrencyMismatch;

class CurrencyValidator implements ValidatorInterface {

	public function validate( PayPalCart $cart ) {
		$issues = array();

		// Extract all currencies from cart items.
		$cart_currencies = $this->extract_cart_currencies( $cart );

		// If no currencies found, nothing to validate.
		if ( empty( $cart_currencies ) ) {
			return $issues;
		}

		// Validate all items have the same currency.
		$currency_issue = $this->validate_consistent_currency( $cart_currencies );
		if ( $currency_issue ) {
			$issues[] = $currency_issue;
			// Don't check store currency if items have mixed currencies.
			return $issues;
		}

		// At this point, all currencies are the same - check against store.
		$cart_currency        = $cart_currencies[0]['currency'];
		$first_currency_index = $cart_currencies[0]['index'];
		$store_currency_issue = $this->validate_store_currency( $cart_currency, $first_currency_index );
		if ( $store_currency_issue ) {
			$issues[] = $store_currency_issue;
		}

		return $issues;
	}

	/**
	 * Extracts all currencies with their item indices from the cart.
	 *
	 * @param PayPalCart $cart The cart to extract currencies from.
	 * @return array Array of ['index' => int, 'currency' => string].
	 */
	private function extract_cart_currencies( PayPalCart $cart ): array {
		$currencies = array();
		foreach ( $cart->items() as $key => $item ) {
			$price = $item->price();
			if ( $price && $price->currency_code() ) {
				$currencies[] = array(
					'index'    => $key,
					'currency' => $price->currency_code(),
				);
			}
		}
		return $currencies;
	}

	/**
	 * Validates that all currencies are the same.
	 *
	 * @param array $currencies Array of ['index' => int, 'currency' => string].
	 * @return CurrencyMismatch|null Validation issue if currencies are inconsistent.
	 */
	private function validate_consistent_currency( array $currencies ): ?CurrencyMismatch {
		$unique_currencies = array_unique(
			array_column( $currencies, 'currency' )
		);

		// If all items have the same currency, validation passes.
		if ( count( $unique_currencies ) === 1 ) {
			return null;
		}

		// Find the first mismatch.
		$reference_currency = $currencies[0]['currency'];
		$mismatch           = current(
			array_filter(
				$currencies,
				fn( $item ) => $item['currency'] !== $reference_currency
			)
		);

		return new CurrencyMismatch(
			sprintf(
				'Mixed currencies detected: item %d has currency %s, expected %s',
				$mismatch['index'],
				$mismatch['currency'],
				$reference_currency
			),
			'All items in the cart must use the same currency.',
			"items[{$mismatch['index']}].price.currency_code"
		);
	}

	/**
	 * Validates that the cart currency matches the WooCommerce store currency.
	 *
	 * @param string $cart_currency The cart's currency code.
	 * @param int    $item_index    The index of the first item with this currency.
	 * @return CurrencyMismatch|null Validation issue if currency doesn't match store settings.
	 */
	private function validate_store_currency( string $cart_currency, int $item_index ): ?CurrencyMismatch {
		$store_currency = get_woocommerce_currency();

		if ( $cart_currency !== $store_currency ) {
			return new CurrencyMismatch(
				sprintf(
					'Cart currency %s does not match store currency %s',
					$cart_currency,
					$store_currency
				),
				sprintf(
					'This store only accepts payments in %s.',
					$store_currency
				),
				"items[{$item_index}].price.currency_code"
			);
		}

		return null;
	}
}
