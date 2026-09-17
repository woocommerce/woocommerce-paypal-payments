<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Auth;

use Exception;
use WP_Error;
use Firebase\JWT\JWT;
/**
 * JWT Authentication layer for local testing and development.
 *
 * The decision to use this service is made in `AuthServiceProvider`
 *
 * This service is automatically used when the site connects to a
 * sandbox merchant account (cannot process real payments).
 *
 * To simulate full production authentication for a sandbox merchant,
 * add this flag to wp-config:
 *   define( 'PPCP_AGENTIC_FULL_AUTH', true )
 */
class SandboxAuthService extends \WooCommerce\PayPalCommerce\StoreSync\Auth\JwtAuthService
{
    /**
     * Parses and validates JWT token.
     *
     * @param string|null $auth_header Bearer token from Authorization header.
     * @return object|WP_Error Decoded token or validation error.
     */
    public function get_token(?string $auth_header)
    {
        $jwt = $this->extract_jwt_from_header($auth_header);
        if (is_wp_error($jwt)) {
            return $jwt;
        }
        $encoded_parts = explode('.', $jwt);
        try {
            $payload_json = JWT::urlsafeB64Decode($encoded_parts[1]);
            $payload = (object) JWT::jsonDecode($payload_json);
        } catch (Exception $exception) {
            return new WP_Error('invalid_jwt', $exception->getMessage(), array('status' => 401));
        }
        return $payload;
    }
    /**
     * Verifies token claims against business requirements.
     *
     * @param object $context         Decoded JWT payload.
     * @param array  $required_scopes Required permission scopes.
     * @return true|WP_Error
     */
    public function verify_claims(object $context, array $required_scopes)
    {
        // Verify issuer.
        if (!isset($context->iss) || $context->iss !== self::EXPECTED_ISSUER) {
            return new WP_Error('invalid_issuer', 'Token issuer is not recognized', array('status' => 401));
        }
        return \true;
    }
}
