<?php
/**
 * The Applepay module services.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

return array(
	'apple.status-cache'                                     => static function( ContainerInterface $container ): Cache {
		return new Cache( 'ppcp-paypal-apple-status-cache' );
	},
	'applepay.apple-product-status'           => static function( ContainerInterface $container ): AppleProductStatus {
		return new AppleProductStatus(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'api.endpoint.partners' ),
			$container->get( 'apple.status-cache' ),
			$container->get( 'onboarding.state' )
		);
	},
	'applepay.enabled'                        => static function ( ContainerInterface $container ): bool {
		$status = $container->get( 'applepay.apple-product-status' );
		return $status->apple_is_active();
	},
	'applepay.server_supported'               => static function ( ContainerInterface $container ): bool {
		return ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off';
	},
	'applepay.merchant_validated'             => static function ( ContainerInterface $container ): bool {
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );
		try {
			$settings->get( 'applepay_validated' );
		} catch ( \Exception $e ) {
			return false;
		}
		return $settings->get( 'applepay_validated' ) === 'yes';
	},
	'applepay.payment_method'                 => static function ( ContainerInterface $container ): ApplepayPaymentMethod {
		$settings = $container->get( 'wcgateway.settings' );
		$logger  = $container->get( 'woocommerce.logger.woocommerce' );
		$order_processor = $container->get( 'wcgateway.order-processor' );

		return new ApplepayPaymentMethod( $settings, $logger, $order_processor );
	},
	'applepay.url'                            => static function ( ContainerInterface $container ): string {
		$path = realpath( __FILE__ );
		if ( false === $path ) {
			return '';
		}
		return plugins_url(
			'/modules/ppcp-applepay/',
			dirname( $path, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'applepay.sdk_script_url'                 => static function ( ContainerInterface $container ): string {
		return 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js';
	},
	'applepay.script_url'                     => static function ( ContainerInterface $container ): string {
		return trailingslashit( $container->get( 'applepay.url' ) ) . '/assets/js/applePayDirect.js';
	},
	'applepay.style_url'                      => static function ( ContainerInterface $container ): string {
		return trailingslashit( $container->get( 'applepay.url' ) ) . '/assets/css/applepaydirect.css';
	},
	'applepay.setting_button_enabled_product' => static function ( ContainerInterface $container ): bool {
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof ContainerInterface );

		return $settings->has( 'applepay_button_enabled_product' ) ?
			(bool) $settings->get( 'applepay_button_enabled_product' ) :
			false;
	},
	'applepay.setting_button_enabled_cart'    => static function ( ContainerInterface $container ): bool {
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof ContainerInterface );

		return $settings->has( 'applepay_button_enabled_cart' ) ?
			(bool) $settings->get( 'applepay_button_enabled_cart' ) :
			false;
	},
	'applepay.data_to_scripts'                => static function ( ContainerInterface $container ): DataToAppleButtonScripts {
		return new DataToAppleButtonScripts();
	},
);
