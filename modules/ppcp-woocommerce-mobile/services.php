<?php
/**
 * WooCommerce Mobile Integration services
 *
 * @package WooCommerce\PayPalCommerce\WooCommerceMobile
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WooCommerceMobile;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint\CapturePayPalPaymentEndpoint;
use WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint\GetPluginCredentialsEndpoint;
use WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint\GetAccessTokenEndpoint;

return array(

    'woocommerce-mobile.capture-paypal-payment-endpoint' => static function ( ContainerInterface $container ): CapturePayPalPaymentEndpoint {
        return new CapturePayPalPaymentEndpoint(
            $container->get( 'api.endpoint.order' )
        );
    },

    'woocommerce-mobile.get-plugin-credentials-endpoint' => static function ( ContainerInterface $container ): GetPluginCredentialsEndpoint {
        return new GetPluginCredentialsEndpoint(
            $container->get( 'settings.data.general' )
        );
    },

    'woocommerce-mobile.get-access-token-endpoint' => static function ( ContainerInterface $container ): GetAccessTokenEndpoint {
        return new GetAccessTokenEndpoint(
            $container->get( 'api.bearer' )
        );
    },

);