<?php
/**
 * Currency Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\CartValidation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\CartValidation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\Priority;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Schema\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Validation\CurrencyMismatch;

class CurrencyValidator implements ValidatorInterface {

	public function validate( PayPalCart $cart ) {
		$store_currency  = get_woocommerce_currency();
		$cart_currencies = $this->extract_cart_currencies( $cart );

		if ( empty( $cart_currencies ) ) {
			return array();
		}

		$consistency_issue = $this->validate_consistent_currency( $cart_currencies, $store_currency );
		if ( $consistency_issue ) {
			return array( $consistency_issue );
		}

		$store_issue = $this->validate_store_currency(
			$cart_currencies[0]['currency'],
			$cart_currencies[0]['index'],
			$store_currency
		);

		return array_filter( array( $store_issue ) );
	}

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

	private function validate_consistent_currency( array $currencies, string $store_currency ): ?CurrencyMismatch {
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
			"items[{$mismatch['index']}].price.currency_code",
			'',
			array(),
			array(
				ResolutionOption::use_different_currency(
					sprintf( 'Set all items to %s', $store_currency ),
					$store_currency
				)->with(
					array(
						'metadata' => array(
							'priority'          => Priority::HIGH,
							'expected_currency' => $store_currency,
						),
					)
				),
				ResolutionOption::remove_item( Priority::LOW, array( 'item_index' => $mismatch['index'] ) ),
			)
		);
	}

	private function validate_store_currency( string $cart_currency, int $item_index, string $store_currency ): ?CurrencyMismatch {
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
				"items[{$item_index}].price.currency_code",
				'',
				array(),
				array(
					ResolutionOption::use_different_currency(
						sprintf( 'Change to %s', $store_currency ),
						$store_currency
					),
				)
			);
		}

		return null;
	}
}
