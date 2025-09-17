<?php
/**
 * Zettle OAuth client for handling authentication and token management
 *
 * @package WooCommerce\PayPalCommerce\WooCommerceMobile\Zettle
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WooCommerceMobile\Zettle;

/**
 * Class ZettleOAuthClient
 * 
 * Handles Zettle OAuth 2.0 authorization code flow with PKCE
 * Manages access and refresh token lifecycle
 */
class ZettleOAuthClient {

    /**
     * Zettle OAuth endpoint (production only)
     */
    private const OAUTH_BASE_URL = 'https://oauth.zettle.com';
    
    /**
     * The client ID for Zettle OAuth
     *
     * @var string
     */
    private $client_id;

    /**
     * ZettleOAuthClient constructor.
     *
     * @param string $client_id The Zettle client ID.
     */
    public function __construct( string $client_id ) {
        $this->client_id = $client_id;
    }

    /**
     * Get the authorization URL for Zettle OAuth flow.
     *
     * @param string $redirect_uri The redirect URI after authorization.
     * @param array  $scopes The requested OAuth scopes.
     * @return array Authorization URL and PKCE code verifier.
     */
    public function get_authorization_url( string $redirect_uri, array $scopes = [] ): array {
        
        // Generate PKCE parameters
        $code_verifier = $this->generate_code_verifier();
        $code_challenge = $this->generate_code_challenge( $code_verifier );
        
        // Default scopes for payment operations
        if ( empty( $scopes ) ) {
            $scopes = [ 'READ:PAYMENT', 'READ:USERINFO', 'WRITE:PAYMENT', 'WRITE:REFUND2', 'WRITE:USERINFO' ];
        }
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $redirect_uri,
            'scope' => implode( ' ', $scopes ),
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 'S256',
            'state' => wp_generate_uuid4(), // CSRF protection
        ];
        
        $auth_url = self::OAUTH_BASE_URL . '/authorize?' . http_build_query( $params );
        
        return [
            'authorization_url' => $auth_url,
            'code_verifier' => $code_verifier,
            'state' => $params['state'],
        ];
    }

    /**
     * Exchange authorization code for access token.
     *
     * @param string $authorization_code The authorization code from OAuth callback.
     * @param string $redirect_uri The same redirect URI used in authorization.
     * @param string $code_verifier The PKCE code verifier.
     * @return array|WP_Error Token response or error.
     */
    public function exchange_code_for_token( string $authorization_code, string $redirect_uri, string $code_verifier ) {
        $token_url = self::OAUTH_BASE_URL . '/token';
        
        $body = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->client_id,
            'code' => $authorization_code,
            'redirect_uri' => $redirect_uri,
            'code_verifier' => $code_verifier,
        ];
        
        $response = wp_remote_post( $token_url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'body' => http_build_query( $body ),
            'timeout' => 30,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        if ( $status_code !== 200 ) {
            error_log( 'Zettle OAuth error: ' . $response_body );
            return new WP_Error( 'zettle_oauth_error', 'Failed to exchange code for token', [
                'status' => $status_code,
                'response' => $response_body,
            ] );
        }
        
        $token_data = json_decode( $response_body, true );
        
        if ( ! $token_data || ! isset( $token_data['access_token'] ) ) {
            return new WP_Error( 'zettle_oauth_invalid_response', 'Invalid token response from Zettle' );
        }
        
        // Store tokens securely
        $this->store_tokens( $token_data );
        
        return $token_data;
    }

    /**
     * Refresh the access token using refresh token.
     *
     * @param string $refresh_token The refresh token.
     * @return array|WP_Error New token data or error.
     */
    public function refresh_access_token( string $refresh_token ) {
        $token_url = self::OAUTH_BASE_URL . '/token';
        
        $body = [
            'grant_type' => 'refresh_token',
            'client_id' => $this->client_id,
            'refresh_token' => $refresh_token,
        ];
        
        $response = wp_remote_post( $token_url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'body' => http_build_query( $body ),
            'timeout' => 30,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        if ( $status_code !== 200 ) {
            error_log( 'Zettle token refresh error: ' . $response_body );
            return new WP_Error( 'zettle_refresh_error', 'Failed to refresh access token', [
                'status' => $status_code,
                'response' => $response_body,
            ] );
        }
        
        $token_data = json_decode( $response_body, true );
        
        if ( ! $token_data || ! isset( $token_data['access_token'] ) ) {
            return new WP_Error( 'zettle_refresh_invalid_response', 'Invalid refresh response from Zettle' );
        }
        
        // Update stored tokens
        $this->store_tokens( $token_data );
        
        return $token_data;
    }

    /**
     * Get a valid access token (refresh if necessary).
     *
     * @return string|WP_Error Valid access token or error.
     */
    public function get_valid_access_token() {
        $stored_token = get_option( 'zettle_access_token' );
        $stored_refresh = get_option( 'zettle_refresh_token' );
        $token_expires = get_option( 'zettle_token_expires', 0 );
        
        // Check if current token is still valid (with 5 minute buffer)
        if ( $stored_token && $token_expires > ( time() + 300 ) ) {
            return $stored_token;
        }
        
        // Try to refresh token
        if ( $stored_refresh ) {
            $refresh_result = $this->refresh_access_token( $stored_refresh );
            
            if ( ! is_wp_error( $refresh_result ) ) {
                return $refresh_result['access_token'];
            }
            
            error_log( 'Zettle token refresh failed: ' . $refresh_result->get_error_message() );
        }
        
        return new WP_Error( 'zettle_no_valid_token', 'No valid Zettle access token available. Re-authorization required.' );
    }

    /**
     * Store tokens securely in WordPress options.
     *
     * @param array $token_data Token data from Zettle OAuth response.
     */
    private function store_tokens( array $token_data ): void {
        update_option( 'zettle_access_token', $token_data['access_token'] );
        
        if ( isset( $token_data['refresh_token'] ) ) {
            update_option( 'zettle_refresh_token', $token_data['refresh_token'] );
        }
        
        $expires_at = time() + ( $token_data['expires_in'] ?? 7200 );
        update_option( 'zettle_token_expires', $expires_at );
        
        // Log successful token storage (without exposing sensitive data)
        error_log( sprintf(
            'Zettle: Stored access token (expires: %s)',
            gmdate( 'Y-m-d H:i:s', $expires_at )
        ) );
    }

    /**
     * Generate PKCE code verifier.
     *
     * @return string Random code verifier.
     */
    private function generate_code_verifier(): string {
        return rtrim( strtr( base64_encode( wp_generate_password( 32, false ) ), '+/', '-_' ), '=' );
    }

    /**
     * Generate PKCE code challenge from verifier.
     *
     * @param string $code_verifier The code verifier.
     * @return string Base64 URL-encoded SHA256 hash of verifier.
     */
    private function generate_code_challenge( string $code_verifier ): string {
        return rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );
    }

    /**
     * Clear all stored tokens.
     */
    public function clear_tokens(): void {
        delete_option( 'zettle_access_token' );
        delete_option( 'zettle_refresh_token' );
        delete_option( 'zettle_token_expires' );
        
        error_log( 'Zettle: Cleared all stored tokens' );
    }
}