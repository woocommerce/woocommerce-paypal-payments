<?php
/**
 * REST API endpoint for getting Zettle access tokens from authenticated plugin
 *
 * @package WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint;

use WooCommerce\PayPalCommerce\WooCommerceMobile\Zettle\ZettleOAuthClient;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class GetAccessTokenEndpoint
 * 
 * Provides fresh Zettle access tokens to the mobile app using site-managed OAuth
 * This eliminates the need for the mobile app to manage Zettle credentials directly
 */
class GetAccessTokenEndpoint {

    /**
     * The Zettle OAuth client.
     *
     * @var ZettleOAuthClient
     */
    private $zettle_client;

    /**
     * GetAccessTokenEndpoint constructor.
     *
     * @param ZettleOAuthClient $zettle_client The Zettle OAuth client.
     */
    public function __construct( ZettleOAuthClient $zettle_client ) {
        $this->zettle_client = $zettle_client;
    }

    /**
     * Registers the REST API route.
     */
    public function register_routes() {
        register_rest_route(
            'wc/v3',
            '/zettle/access-token',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_access_token' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'scope' => array(
                        'required' => false,
                        'type'     => 'string',
                        'default'  => 'READ:PAYMENT READ:USERINFO WRITE:PAYMENT WRITE:REFUND2 WRITE:USERINFO',
                        'description' => 'OAuth scope for the access token',
                    ),
                ),
            )
        );
    }

    /**
     * Gets a fresh Zettle access token using site-managed OAuth.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_access_token( WP_REST_Request $request ) {
        try {
            error_log( 'Zettle Mobile: GetAccessTokenEndpoint called' );
            
            // Check current token status for debugging
            $stored_token = get_option( 'zettle_access_token' );
            $stored_refresh = get_option( 'zettle_refresh_token' );
            $token_expires = get_option( 'zettle_token_expires', 0 );
            
            // Ensure token_expires is an integer
            $token_expires_int = is_numeric( $token_expires ) ? intval( $token_expires ) : 0;
            
            error_log( sprintf( 
                'Zettle Mobile: Token status - has_access: %s, has_refresh: %s, expires: %s (current: %s)',
                $stored_token ? 'yes' : 'no',
                $stored_refresh ? 'yes' : 'no',
                $token_expires_int ? gmdate( 'Y-m-d H:i:s', $token_expires_int ) : 'not set',
                gmdate( 'Y-m-d H:i:s', time() )
            ) );
            
            // Get a valid Zettle access token (refresh if necessary)
            $access_token = $this->zettle_client->get_valid_access_token();
            
            if ( is_wp_error( $access_token ) ) {
                error_log( 'Zettle Mobile: Token fetch error - ' . $access_token->get_error_message() );
                return new WP_Error(
                    'zettle_auth_failed',
                    __( 'Failed to obtain Zettle access token. Site may not be properly authenticated with Zettle.', 'woocommerce-paypal-payments' ),
                    array( 'status' => 401 )
                );
            }

            // Return the access token in the format expected by the mobile app
            $token_data = array(
                'access_token' => $access_token,
                'token_type'   => 'Bearer',
                'expires_in'   => 7200, // Zettle tokens typically last 2 hours
                'scope'        => $request->get_param( 'scope' ),
                'issued_at'    => time(),
            );

            // Log successful token generation
            error_log( sprintf(
                'Zettle Mobile: Generated access token for mobile app. Length: %d chars',
                strlen( $access_token )
            ) );

            return new WP_REST_Response( $token_data, 200 );

        } catch ( Exception $e ) {
            error_log( 'Zettle Mobile: Failed to generate access token: ' . $e->getMessage() );
            
            return new WP_Error(
                'zettle_token_error',
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
                __( 'Sorry, you are not allowed to access Zettle tokens.', 'woocommerce-paypal-payments' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        return true;
    }
}