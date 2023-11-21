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
		return apply_filters(
			'woocommerce_paypal_payments_card_fields_supported_country_currency_matrix',
			array(
				'US' => array(
					'AUD',
					'CAD',
					'EUR',
					'GBP',
					'JPY',
					'USD',
				),
			)
		);
	},
);
