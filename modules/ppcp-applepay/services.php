<?php
/**
 * The Applepay module services.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodTypeInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Applepay\Assets\ApplePayButton;
use WooCommerce\PayPalCommerce\Applepay\Assets\AppleProductStatus;
use WooCommerce\PayPalCommerce\Applepay\Assets\DataToAppleButtonScripts;
use WooCommerce\PayPalCommerce\Applepay\Assets\BlocksPaymentMethod;
use WooCommerce\PayPalCommerce\Applepay\Helper\ApmApplies;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

return array(
	'applepay.eligible'                          => static function ( ContainerInterface $container ): bool {
		$apm_applies = $container->get( 'applepay.helpers.apm-applies' );
		assert( $apm_applies instanceof ApmApplies );

		return $apm_applies->for_country_currency();
	},
	'applepay.helpers.apm-applies'               => static function ( ContainerInterface $container ) : ApmApplies {
		return new ApmApplies(
			$container->get( 'applepay.supported-country-currency-matrix' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'api.shop.country' )
		);
	},
	'applepay.status-cache'                      => static function( ContainerInterface $container ): Cache {
		return new Cache( 'ppcp-paypal-apple-status-cache' );
	},
	'applepay.apple-product-status'              => static function( ContainerInterface $container ): AppleProductStatus {
		return new AppleProductStatus(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'api.endpoint.partners' ),
			$container->get( 'applepay.status-cache' ),
			$container->get( 'onboarding.state' )
		);
	},
	'applepay.enabled'                           => static function ( ContainerInterface $container ): bool {
		if ( apply_filters( 'woocommerce_paypal_payments_applepay_validate_product_status', false ) ) {
			$status = $container->get( 'applepay.apple-product-status' );
			assert( $status instanceof AppleProductStatus );
			/**
			 * If merchant isn't onboarded via /v1/customer/partner-referrals this returns false as the API call fails.
			 */
			return apply_filters( 'woocommerce_paypal_payments_applepay_product_status', $status->apple_is_active() );
		}
		return true;
	},
	'applepay.server_supported'                  => static function ( ContainerInterface $container ): bool {
		return ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off';
	},
	'applepay.url'                               => static function ( ContainerInterface $container ): string {
		$path = realpath( __FILE__ );
		if ( false === $path ) {
			return '';
		}
		return plugins_url(
			'/modules/ppcp-applepay/',
			dirname( $path, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'applepay.sdk_script_url'                    => static function ( ContainerInterface $container ): string {
		return 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js';
	},
	'applepay.data_to_scripts'                   => static function ( ContainerInterface $container ): DataToAppleButtonScripts {
		return new DataToAppleButtonScripts( $container->get( 'applepay.sdk_script_url' ), $container->get( 'wcgateway.settings' ) );
	},
	'applepay.button'                            => static function ( ContainerInterface $container ): ApplePayButton {

		return new ApplePayButton(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'wcgateway.order-processor' ),
			$container->get( 'applepay.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'applepay.data_to_scripts' ),
			$container->get( 'wcgateway.settings.status' )
		);
	},
	'applepay.blocks-payment-method'             => static function ( ContainerInterface $container ): PaymentMethodTypeInterface {
		return new BlocksPaymentMethod(
			'ppcp-applepay',
			$container->get( 'applepay.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'applepay.button' ),
			$container->get( 'blocks.method' )
		);
	},
	/**
	 * The matrix which countries and currency combinations can be used for ApplePay.
	 */
	'applepay.supported-country-currency-matrix' => static function ( ContainerInterface $container ) : array {
		/**
		 * Returns which countries and currency combinations can be used for ApplePay.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_applepay_supported_country_currency_matrix',
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
