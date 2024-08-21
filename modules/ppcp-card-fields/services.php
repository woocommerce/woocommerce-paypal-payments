<?php
/**
 * The Card Fields module services.
 *
 * @package WooCommerce\PayPalCommerce\CardFields
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\CardFields;

use WooCommerce\PayPalCommerce\CardFields\Helper\CardFieldsApplies;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'card-fields.eligible'                             => static function ( ContainerInterface $container ): bool {
		$save_payment_methods_applies = $container->get( 'card-fields.helpers.save-payment-methods-applies' );
		assert( $save_payment_methods_applies instanceof CardFieldsApplies );

		return $save_payment_methods_applies->for_country_currency();
	},
	'card-fields.helpers.save-payment-methods-applies' => static function ( ContainerInterface $container ) : CardFieldsApplies {
		return new CardFieldsApplies(
			$container->get( 'card-fields.supported-country-currency-matrix' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'api.shop.country' )
		);
	},
	'card-fields.supported-country-currency-matrix'    => static function ( ContainerInterface $container ) : array {
		$default_currencies = array(
			'AUD',
			'BRL',
			'CAD',
			'CHF',
			'CZK',
			'DKK',
			'EUR',
			'GBP',
			'HKD',
			'HUF',
			'ILS',
			'JPY',
			'MXN',
			'NOK',
			'NZD',
			'PHP',
			'PLN',
			'SEK',
			'SGD',
			'THB',
			'TWD',
			'USD',
		);

		return apply_filters(
			'woocommerce_paypal_payments_card_fields_supported_country_currency_matrix',
			array(
				'AU' => $default_currencies,
				'AT' => $default_currencies,
				'BE' => $default_currencies,
				'BG' => $default_currencies,
				'CA' => $default_currencies,
				'CN' => $default_currencies,
				'CY' => $default_currencies,
				'CZ' => $default_currencies,
				'DK' => $default_currencies,
				'EE' => $default_currencies,
				'FI' => $default_currencies,
				'FR' => $default_currencies,
				'DE' => $default_currencies,
				'GR' => $default_currencies,
				'HU' => $default_currencies,
				'IE' => $default_currencies,
				'IT' => $default_currencies,
				'LV' => $default_currencies,
				'LI' => $default_currencies,
				'LT' => $default_currencies,
				'LU' => $default_currencies,
				'MT' => $default_currencies,
				'NL' => $default_currencies,
				'PL' => $default_currencies,
				'PT' => $default_currencies,
				'RO' => $default_currencies,
				'SK' => $default_currencies,
				'SI' => $default_currencies,
				'ES' => $default_currencies,
				'SE' => $default_currencies,
				'GB' => $default_currencies,
				'US' => array(
					'AUD',
					'CAD',
					'EUR',
					'GBP',
					'JPY',
					'USD',
				),
				'NO' => $default_currencies,
			)
		);
	},
);
