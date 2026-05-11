<?php
/**
 * Cart Helper for Agentic Commerce.
 *
 * Provides convenience methods for accessing and calculating cart data.
 * Schema classes remain pure data structures; all convenience logic goes here.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Address;

class CartHelper {
	/**
	 * Derives currency from the first cart item's price.
	 */
	public static function currency( PayPalCart $cart, string $default = '' ): string {
		$items = $cart->items();

		if ( empty( $items ) ) {
			return $default;
		}

		$first_item = $items[0];
		$price      = $first_item->price();

		if ( ! $price ) {
			return $default;
		}

		return $price->currency_code() ?? $default;
	}

	public static function full_customer_name( PayPalCart $cart, string $default = '' ): string {
		$full_name = '';
		$customer  = $cart->customer();

		if ( $customer && $customer->name() ) {
			$name       = $customer->name();
			$first_name = $name['given_name'] ?? '';
			$last_name  = $name['surname'] ?? '';

			$full_name = trim( "$first_name $last_name" );
		}

		return $full_name ?: $default;
	}

	/**
	 * @return array{
	 *     address_line_1: string,
	 *     address_line_2: string,
	 *     admin_area_2: string,
	 *     admin_area_1: string,
	 *     postal_code: string,
	 *     country_code: string
	 * }
	 */
	public static function shipping_address_array( PayPalCart $cart ): array {
		return self::address_array( $cart->shipping_address() );
	}

	/**
	 * @return array{
	 *     address_line_1: string,
	 *     address_line_2: string,
	 *     admin_area_2: string,
	 *     admin_area_1: string,
	 *     postal_code: string,
	 *     country_code: string
	 * }
	 */
	public static function billing_address_array( PayPalCart $cart ): array {
		return self::address_array( $cart->billing_address() );
	}

	private static function address_array( ?Address $address ): array {
		if ( ! $address ) {
			return array(
				'address_line_1' => '',
				'address_line_2' => '',
				'admin_area_2'   => '',
				'admin_area_1'   => '',
				'postal_code'    => '',
				'country_code'   => '',
			);
		}

		return array(
			'address_line_1' => $address->address_line_1() ?? '',
			'address_line_2' => $address->address_line_2() ?? '',
			'admin_area_2'   => $address->admin_area_2() ?? '',
			'admin_area_1'   => $address->admin_area_1() ?? '',
			'postal_code'    => $address->postal_code() ?? '',
			'country_code'   => $address->country_code() ?? '',
		);
	}
}
