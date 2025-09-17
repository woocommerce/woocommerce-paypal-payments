<?php
/**
 * REST API endpoint for getting PayPal access tokens from authenticated plugin
 *
 * @package WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class GetAccessTokenEndpoint
 * 
 * Provides fresh PayPal access tokens to the mobile app using the plugin's existing authentication
 * This eliminates the need for the mobile app to manage PayPal credentials directly
 */
class GetAccessTokenEndpoint {

    /**
     * The PayPal bearer token handler.
     *
     * @var PayPalBearer
     */
    private $bearer;

    /**
     * GetAccessTokenEndpoint constructor.
     *
     * @param PayPalBearer $bearer The PayPal bearer token handler.
     */
    public function __construct( PayPalBearer $bearer ) {
        $this->bearer = $bearer;
    }

    /**
     * Registers the REST API route.
     */
    public function register_routes() {
        register_rest_route(
            'wc/v3',
            '/paypal/access-token',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_access_token' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'scope' => array(
                        'required' => false,
                        'type'     => 'string',
                        'default'  => 'https://uri.paypal.com/services/payments/payment',
                        'description' => 'OAuth scope for the access token',
                    ),
                ),
            )
        );
    }

    /**
     * Gets a fresh PayPal access token using the plugin's existing authentication.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_access_token( WP_REST_Request $request ) {
        try {
            // Use the plugin's existing PayPal authentication to get a fresh token
            $bearer_token = $this->bearer->bearer();
            
            if ( ! $bearer_token ) {
                return new WP_Error(
                    'paypal_auth_failed',
                    __( 'Failed to obtain PayPal access token. Plugin may not be properly authenticated.', 'woocommerce-paypal-payments' ),
                    array( 'status' => 401 )
                );
            }

            // Get token details
            $token_data = array(
                'access_token' => $bearer_token->token(),
                'token_type'   => 'Bearer',
                'expires_in'   => $bearer_token->expires_in(),
                'scope'        => $request->get_param( 'scope' ),
                'issued_at'    => time(),
            );

            // Log successful token generation
            error_log( sprintf(
                'PayPal Mobile: Generated access token for mobile app. Expires in: %d seconds',
                $bearer_token->expires_in()
            ) );

            return new WP_REST_Response( $token_data, 200 );

        } catch ( Exception $e ) {
            error_log( 'PayPal Mobile: Failed to generate access token: ' . $e->getMessage() );
            
            return new WP_Error(
                'paypal_token_error',
                sprintf( __( 'Failed to generate access token: %s', 'woocommerce-paypal-payments' ), $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Checks if the current user has permission to get access tokens.
     *
     * @param WP_REST_Request $request The REST request.
     * @return bool|WP_Error
     */
    public function check_permissions( WP_REST_Request $request ) {
        // Check if user has general WooCommerce management permissions
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error(
                'woocommerce_rest_cannot_view',
                __( 'Sorry, you are not allowed to access PayPal tokens.', 'woocommerce-paypal-payments' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        return true;
    }
}