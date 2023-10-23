<?php
/**
 * The save payment methods module services.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

use WooCommerce\PayPalCommerce\SavePaymentMethods\Helper\SavePaymentMethodsApplies;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'save-payment-methods.eligible'   => static function ( ContainerInterface $container ): bool {
		$save_payment_methods_applies = $container->get( 'save-payment-methods.helpers.save-payment-methods-applies' );
		assert( $save_payment_methods_applies instanceof SavePaymentMethodsApplies );

		return $save_payment_methods_applies->for_country_currency();
	},
	'save-payment-methods.helpers.save-payment-methods-applies' => static function ( ContainerInterface $container ) : SavePaymentMethodsApplies {
		return new SavePaymentMethodsApplies(
			$container->get( 'save-payment-methods.supported-country-currency-matrix' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'api.shop.country' )
		);
	},
	'save-payment-methods.supported-country-currency-matrix' => static function ( ContainerInterface $container ) : array {
		return apply_filters(
			'woocommerce_paypal_payments_save_payment_methods_supported_country_currency_matrix',
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
	'save-payment-methods.module.url' => static function ( ContainerInterface $container ): string {
		/**
		 * The path cannot be false.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		return plugins_url(
			'/modules/ppcp-save-payment-methods/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
);
