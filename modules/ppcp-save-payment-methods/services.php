<?php
/**
 * The save payment methods module services.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreatePaymentToken;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreateSetupToken;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreatePaymentTokenForGuest;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Helper\SavePaymentMethodsApplies;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'save-payment-methods.eligible'                      => static function ( ContainerInterface $container ): bool {
		$save_payment_methods_applies = $container->get( 'save-payment-methods.helpers.save-payment-methods-applies' );
		assert( $save_payment_methods_applies instanceof SavePaymentMethodsApplies );

		return $save_payment_methods_applies->for_country_currency();
	},
	'save-payment-methods.helpers.save-payment-methods-applies' => static function ( ContainerInterface $container ) : SavePaymentMethodsApplies {
		return new SavePaymentMethodsApplies(
			$container->get( 'save-payment-methods.supported-country-currency-matrix' ),
			$container->get( 'api.shop.currency.getter' ),
			$container->get( 'api.shop.country' )
		);
	},
	'save-payment-methods.supported-country-currency-matrix' => static function ( ContainerInterface $container ) : array {
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
			'woocommerce_paypal_payments_save_payment_methods_supported_country_currency_matrix',
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
				'NO' => $default_currencies,
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
			)
		);
	},
	'save-payment-methods.module.url'                    => static function ( ContainerInterface $container ): string {
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
	'save-payment-methods.endpoint.create-setup-token'   => static function ( ContainerInterface $container ): CreateSetupToken {
		return new CreateSetupToken(
			$container->get( 'button.request-data' ),
			$container->get( 'api.endpoint.payment-method-tokens' )
		);
	},
	'save-payment-methods.endpoint.create-payment-token' => static function ( ContainerInterface $container ): CreatePaymentToken {
		return new CreatePaymentToken(
			$container->get( 'button.request-data' ),
			$container->get( 'api.endpoint.payment-method-tokens' ),
			$container->get( 'vaulting.wc-payment-tokens' )
		);
	},
	'save-payment-methods.endpoint.create-payment-token-for-guest' => static function ( ContainerInterface $container ): CreatePaymentTokenForGuest {
		return new CreatePaymentTokenForGuest(
			$container->get( 'button.request-data' ),
			$container->get( 'api.endpoint.payment-method-tokens' )
		);
	},
);
