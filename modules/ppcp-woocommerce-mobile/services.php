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
use WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint\ZettleOAuthSetupEndpoint;
use WooCommerce\PayPalCommerce\WooCommerceMobile\Zettle\ZettleOAuthClient;
use WooCommerce\PayPalCommerce\WooCommerceMobile\Admin\ZettleSettingsPage;

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

    'woocommerce-mobile.zettle-oauth-client' => static function ( ContainerInterface $container ): ZettleOAuthClient {
        $client_id = get_option( 'zettle_oauth_client_id', '' );
        
        return new ZettleOAuthClient( $client_id );
    },

    'woocommerce-mobile.get-access-token-endpoint' => static function ( ContainerInterface $container ): GetAccessTokenEndpoint {
        return new GetAccessTokenEndpoint(
            $container->get( 'woocommerce-mobile.zettle-oauth-client' )
        );
    },

    'woocommerce-mobile.zettle-oauth-setup-endpoint' => static function ( ContainerInterface $container ): ZettleOAuthSetupEndpoint {
        return new ZettleOAuthSetupEndpoint(
            $container->get( 'woocommerce-mobile.zettle-oauth-client' )
        );
    },

    'woocommerce-mobile.zettle-settings-page' => static function ( ContainerInterface $container ): ZettleSettingsPage {
        return new ZettleSettingsPage(
            $container->get( 'woocommerce-mobile.zettle-oauth-client' )
        );
    },

);