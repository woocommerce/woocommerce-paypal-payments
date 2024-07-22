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

		return $save_payment_methods_applies->for_country();
	},
	'card-fields.helpers.save-payment-methods-applies' => static function ( ContainerInterface $container ) : CardFieldsApplies {
		return new CardFieldsApplies(
			$container->get( 'card-fields.supported-country-matrix' ),
			$container->get( 'api.shop.country' )
		);
	},
	'card-fields.supported-country-matrix'             => static function ( ContainerInterface $container ) : array {
		return apply_filters(
			'woocommerce_paypal_payments_card_fields_supported_country_matrix',
			array(
				'AU',
				'AT',
				'BE',
				'BG',
				'CA',
				'CN',
				'CY',
				'CZ',
				'DK',
				'EE',
				'FI',
				'FR',
				'DE',
				'GR',
				'HU',
				'IE',
				'IT',
				'LV',
				'LI',
				'LT',
				'LU',
				'MT',
				'NL',
				'PL',
				'PT',
				'RO',
				'SK',
				'SI',
				'ES',
				'SE',
				'GB',
				'US',
				'NO',
			)
		);
	},
);
