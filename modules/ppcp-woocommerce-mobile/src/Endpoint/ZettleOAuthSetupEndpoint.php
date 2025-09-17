<?php
/**
 * REST API endpoint for Zettle OAuth setup and authorization
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
 * Class ZettleOAuthSetupEndpoint
 * 
 * Handles Zettle OAuth authorization flow setup
 */
class ZettleOAuthSetupEndpoint {

    /**
     * The Zettle OAuth client.
     *
     * @var ZettleOAuthClient
     */
    private $zettle_client;

    /**
     * ZettleOAuthSetupEndpoint constructor.
     *
     * @param ZettleOAuthClient $zettle_client The Zettle OAuth client.
     */
    public function __construct( ZettleOAuthClient $zettle_client ) {
        $this->zettle_client = $zettle_client;
    }

    /**
     * Registers the REST API routes.
     */
    public function register_routes() {
        // Get authorization URL
        register_rest_route(
            'wc/v3',
            '/zettle/oauth/authorize',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_authorization_url' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );

        // Handle OAuth callback
        register_rest_route(
            'wc/v3',
            '/zettle/oauth/callback',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_oauth_callback' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'code' => array(
                        'required' => true,
                        'type'     => 'string',
                        'description' => 'Authorization code from Zettle',
                    ),
                    'state' => array(
                        'required' => true,
                        'type'     => 'string',
                        'description' => 'State parameter for CSRF protection',
                    ),
                ),
            )
        );

        // Check connection status
        register_rest_route(
            'wc/v3',
            '/zettle/oauth/status',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_connection_status' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );

        // Disconnect/clear tokens
        register_rest_route(
            'wc/v3',
            '/zettle/oauth/disconnect',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'disconnect' ),
                'permission_callback' => array( $this, 'check_permissions' ),
            )
        );
    }

    /**
     * Get the Zettle OAuth authorization URL.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function get_authorization_url( WP_REST_Request $request ) {
        try {
            $redirect_uri = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paypal&zettle_oauth_callback=1' );
            
            $auth_data = $this->zettle_client->get_authorization_url( $redirect_uri );
            
            // Store PKCE parameters for later use
            update_option( 'zettle_oauth_code_verifier', $auth_data['code_verifier'] );
            update_option( 'zettle_oauth_state', $auth_data['state'] );
            update_option( 'zettle_oauth_redirect_uri', $redirect_uri );
            
            return new WP_REST_Response( array(
                'authorization_url' => $auth_data['authorization_url'],
                'state' => $auth_data['state'],
            ), 200 );

        } catch ( Exception $e ) {
            return new WP_Error(
                'zettle_auth_url_error',
                sprintf( __( 'Failed to generate authorization URL: %s', 'woocommerce-paypal-payments' ), $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Handle the OAuth callback from Zettle.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_oauth_callback( WP_REST_Request $request ) {
        $code = $request->get_param( 'code' );
        $state = $request->get_param( 'state' );
        
        // Verify state parameter for CSRF protection
        $stored_state = get_option( 'zettle_oauth_state' );
        if ( $state !== $stored_state ) {
            return new WP_Error(
                'zettle_oauth_invalid_state',
                __( 'Invalid state parameter. Possible CSRF attack.', 'woocommerce-paypal-payments' ),
                array( 'status' => 400 )
            );
        }
        
        $code_verifier = get_option( 'zettle_oauth_code_verifier' );
        $redirect_uri = get_option( 'zettle_oauth_redirect_uri' );
        
        if ( ! $code_verifier || ! $redirect_uri ) {
            return new WP_Error(
                'zettle_oauth_missing_params',
                __( 'Missing OAuth parameters. Please restart the authorization process.', 'woocommerce-paypal-payments' ),
                array( 'status' => 400 )
            );
        }
        
        try {
            $token_data = $this->zettle_client->exchange_code_for_token( $code, $redirect_uri, $code_verifier );
            
            if ( is_wp_error( $token_data ) ) {
                return $token_data;
            }
            
            // Clean up temporary OAuth parameters
            delete_option( 'zettle_oauth_code_verifier' );
            delete_option( 'zettle_oauth_state' );
            delete_option( 'zettle_oauth_redirect_uri' );
            
            return new WP_REST_Response( array(
                'success' => true,
                'message' => __( 'Successfully connected to Zettle!', 'woocommerce-paypal-payments' ),
                'expires_in' => $token_data['expires_in'] ?? 7200,
            ), 200 );
            
        } catch ( Exception $e ) {
            return new WP_Error(
                'zettle_oauth_callback_error',
                sprintf( __( 'OAuth callback failed: %s', 'woocommerce-paypal-payments' ), $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Get the current connection status.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function get_connection_status( WP_REST_Request $request ) {
        $access_token = get_option( 'zettle_access_token' );
        $token_expires = get_option( 'zettle_token_expires', 0 );
        
        $is_connected = ! empty( $access_token );
        $is_expired = $token_expires <= time();
        
        return new WP_REST_Response( array(
            'is_connected' => $is_connected,
            'is_expired' => $is_expired,
            'expires_at' => $token_expires > 0 ? gmdate( 'Y-m-d H:i:s', $token_expires ) : null,
            'can_refresh' => ! empty( get_option( 'zettle_refresh_token' ) ),
        ), 200 );
    }

    /**
     * Disconnect and clear all stored tokens.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response
     */
    public function disconnect( WP_REST_Request $request ) {
        $this->zettle_client->clear_tokens();
        
        return new WP_REST_Response( array(
            'success' => true,
            'message' => __( 'Successfully disconnected from Zettle.', 'woocommerce-paypal-payments' ),
        ), 200 );
    }

    /**
     * Checks if the current user has permission to manage Zettle OAuth.
     *
     * @param WP_REST_Request $request The REST request.
     * @return bool|WP_Error
     */
    public function check_permissions( WP_REST_Request $request ) {
        // Check if user has general WooCommerce management permissions
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error(
                'woocommerce_rest_cannot_edit',
                __( 'Sorry, you are not allowed to manage Zettle connections.', 'woocommerce-paypal-payments' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        return true;
    }
}