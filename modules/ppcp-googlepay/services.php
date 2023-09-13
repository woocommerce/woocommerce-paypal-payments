<?php
/**
 * The Googlepay module services.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodTypeInterface;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Googlepay\Assets\BlocksPaymentMethod;
use WooCommerce\PayPalCommerce\Googlepay\Assets\Button;
use WooCommerce\PayPalCommerce\Googlepay\Helper\ApmApplies;
use WooCommerce\PayPalCommerce\Googlepay\Helper\ApmProductStatus;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(

	// If GooglePay can be configured.
	'googlepay.eligible'                          => static function ( ContainerInterface $container ): bool {
		$apm_applies = $container->get( 'googlepay.helpers.apm-applies' );
		assert( $apm_applies instanceof ApmApplies );

		return $apm_applies->for_country_currency();
	},

	'googlepay.helpers.apm-applies'               => static function ( ContainerInterface $container ) : ApmApplies {
		return new ApmApplies(
			$container->get( 'googlepay.supported-country-currency-matrix' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'api.shop.country' )
		);
	},

	// If GooglePay is configured.
	'googlepay.available'                         => static function ( ContainerInterface $container ): bool {
		if ( apply_filters( 'woocommerce_paypal_payments_googlepay_validate_product_status', false ) ) {
			$status = $container->get( 'googlepay.helpers.apm-product-status' );
			assert( $status instanceof ApmProductStatus );
			/**
			 * If merchant isn't onboarded via /v1/customer/partner-referrals this returns false as the API call fails.
			 */
			return apply_filters( 'woocommerce_paypal_payments_googlepay_product_status', $status->is_active() );
		}
		return true;
	},

	'googlepay.helpers.apm-product-status'        => static function( ContainerInterface $container ): ApmProductStatus {
		return new ApmProductStatus(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'api.endpoint.partners' ),
			$container->get( 'onboarding.state' )
		);
	},

	/**
	 * The matrix which countries and currency combinations can be used for GooglePay.
	 */
	'googlepay.supported-country-currency-matrix' => static function ( ContainerInterface $container ) : array {
		/**
		 * Returns which countries and currency combinations can be used for GooglePay.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_googlepay_supported_country_currency_matrix',
			array(
				'GB' => array(
					'AUD',
					'CAD',
					'CHF',
					'CZK',
					'DKK',
					'EUR',
					'GBP',
					'HKD',
					'HUF',
					'JPY',
					'NOK',
					'NZD',
					'PLN',
					'SEK',
					'SGD',
					'USD',
				),
				'US' => array(
					'AUD',
					'CAD',
					'EUR',
					'GBP',
					'JPY',
					'USD',
				),
				'CA' => array(
					'AUD',
					'CAD',
					'CHF',
					'CZK',
					'DKK',
					'EUR',
					'GBP',
					'HKD',
					'HUF',
					'JPY',
					'NOK',
					'NZD',
					'PLN',
					'SEK',
					'SGD',
					'USD',
				),
			)
		);
	},

	'googlepay.button'                            => static function ( ContainerInterface $container ): ButtonInterface {
		return new Button(
			$container->get( 'googlepay.url' ),
			$container->get( 'googlepay.sdk_url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'session.handler' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'wcgateway.settings.status' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},

	'googlepay.blocks-payment-method'             => static function ( ContainerInterface $container ): PaymentMethodTypeInterface {
		return new BlocksPaymentMethod(
			'ppcp-googlepay',
			$container->get( 'googlepay.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'googlepay.button' ),
			$container->get( 'blocks.method' )
		);
	},

	'googlepay.url'                               => static function ( ContainerInterface $container ): string {
		$path = realpath( __FILE__ );
		if ( false === $path ) {
			return '';
		}
		return plugins_url(
			'/modules/ppcp-googlepay/',
			dirname( $path, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},

	'googlepay.sdk_url'                           => static function ( ContainerInterface $container ): string {
		return 'https://pay.google.com/gp/p/js/pay.js';
	},

);
