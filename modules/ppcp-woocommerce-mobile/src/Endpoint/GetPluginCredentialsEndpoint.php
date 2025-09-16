<?php
/**
 * REST API endpoint for getting PayPal plugin credentials for mobile app
 *
 * @package WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class GetPluginCredentialsEndpoint
 * 
 * Provides PayPal credentials from the plugin to the mobile app for seamless authentication
 */
class GetPluginCredentialsEndpoint {

    /**
     * The general settings.
     *
     * @var GeneralSettings
     */
    private $general_settings;

    /**
     * GetPluginCredentialsEndpoint constructor.
     *
     * @param GeneralSettings $general_settings The general settings.
     */
    public function __construct( GeneralSettings $general_settings ) {
        $this->general_settings = $general_settings;
    }

    /**
     * Registers the REST API route.
     */
    public function register_routes() {
        register_rest_route(
            'wc/v3',
            '/paypal/credentials',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_credentials' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );
    }

    /**
     * Gets the PayPal credentials for mobile app authentication.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_credentials( WP_REST_Request $request ) {
        try {
            $merchant_data = $this->general_settings->get_merchant_data();

            // Only return credentials if merchant is connected
            if ( ! $this->general_settings->is_merchant_connected() ) {
                return new WP_Error(
                    'paypal_not_connected',
                    __( 'PayPal account is not connected.', 'woocommerce-paypal-payments' ),
                    array( 'status' => 400 )
                );
            }

            $credentials = array(
                'client_id'        => $merchant_data->client_id,
                'client_secret'    => $merchant_data->client_secret,
                'merchant_id'      => $merchant_data->merchant_id,
                'merchant_email'   => $merchant_data->merchant_email,
                'merchant_country' => $merchant_data->merchant_country,
                'sandbox_merchant' => $merchant_data->is_sandbox,
                'seller_type'      => $merchant_data->seller_type,
            );

            return new WP_REST_Response( $credentials, 200 );

        } catch ( Exception $e ) {
            return new WP_Error(
                'paypal_credentials_error',
                sprintf( __( 'Failed to get PayPal credentials: %s', 'woocommerce-paypal-payments' ), $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Checks if the current user has permission to access PayPal credentials.
     *
     * @param WP_REST_Request $request The REST request.
     * @return bool|WP_Error
     */
    public function check_permissions( WP_REST_Request $request ) {
        // Use the same permission check as WooCommerce orders endpoint  
        if ( ! wc_rest_check_manager_permissions( 'orders', 'read' ) ) {
            return new WP_Error(
                'woocommerce_rest_cannot_view',
                __( 'Sorry, you are not allowed to access PayPal credentials.', 'woocommerce-paypal-payments' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        return true;
    }
}