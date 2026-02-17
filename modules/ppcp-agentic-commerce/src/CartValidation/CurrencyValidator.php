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

	public function validate( PayPalCart $cart ): array {
		$cart_currencies = $this->extract_cart_currencies( $cart );

		if ( empty( $cart_currencies ) ) {
			return array();
		}

		$consistency_issue = $this->validate_consistent_currency( $cart_currencies );
		if ( $consistency_issue ) {
			return array( $consistency_issue );
		}

		$store_issue = $this->validate_store_currency(
			$cart_currencies[0]['currency'],
			$cart_currencies[0]['index']
		);

		if ( $store_issue ) {
			return array( $store_issue );
		}

		return array();
	}

	/**
	 * Extracts all currencies with their item indices from the cart.
	 *
	 * @param PayPalCart $cart The cart to extract currencies from.
	 * @return array Array of ['index' => int, 'currency' => string].
	 */
	private function extract_cart_currencies( PayPalCart $cart ): array {
		return array_values(
			array_filter(
				array_map(
					fn( $index ) => $this->extract_currency_at_index( $cart, $index ),
					array_keys( $cart->items() )
				)
			)
		);
	}

	/**
	 * Extracts currency from a specific cart item index.
	 *
	 * @param PayPalCart $cart  The cart.
	 * @param int        $index The item index.
	 * @return array|null Currency data or null if no currency.
	 */
	private function extract_currency_at_index( PayPalCart $cart, int $index ): ?array {
		$item  = $cart->items()[ $index ];
		$price = $item->price();

		if ( ! $price || ! $price->currency_code() ) {
			return null;
		}

		return array(
			'index'    => $index,
			'currency' => $price->currency_code(),
		);
	}

	/**
	 * Validates that all currencies are the same.
	 *
	 * @param array $currencies Array of ['index' => int, 'currency' => string].
	 * @return CurrencyMismatch|null Validation issue if currencies are inconsistent.
	 */
	private function validate_consistent_currency( array $currencies ): ?CurrencyMismatch {
		$unique_currencies = array_unique( array_column( $currencies, 'currency' ) );

		if ( count( $unique_currencies ) === 1 ) {
			return null;
		}

		$reference = $currencies[0];
		$mismatch  = current(
			array_filter(
				$currencies,
				fn( $item ) => $item['currency'] !== $reference['currency']
			)
		);

		return new CurrencyMismatch(
			sprintf(
				'Mixed currencies detected: item %d has currency %s, expected %s',
				$mismatch['index'],
				$mismatch['currency'],
				$reference['currency']
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
