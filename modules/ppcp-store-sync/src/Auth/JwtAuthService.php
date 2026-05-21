<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Auth;

use Exception;
use WP_Error;
use Firebase\JWT\JWT;
use WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadataProvider;
class JwtAuthService
{
    /**
     * The exact issuer string that we expect to see in the JWT payload.
     */
    protected const EXPECTED_ISSUER = 'paypal.com';
    protected \WooCommerce\PayPalCommerce\StoreSync\Auth\PayPalJwkProvider $jwk_provider;
    protected MerchantMetadataProvider $metadata_provider;
    public function __construct(\WooCommerce\PayPalCommerce\StoreSync\Auth\PayPalJwkProvider $jwk_provider, MerchantMetadataProvider $metadata_provider)
    {
        $this->jwk_provider = $jwk_provider;
        $this->metadata_provider = $metadata_provider;
    }
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
        $keys = $this->jwk_provider->keys();
        if (!$keys) {
            return new WP_Error('key_unavailable', 'Could not retrieve public JWT key', array('status' => 503));
        }
        try {
            return JWT::decode($jwt, $keys);
        } catch (Exception $exception) {
            return new WP_Error('invalid_jwt', $exception->getMessage(), array('status' => 401));
        }
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
        // Verify required scopes are present.
        $token_scopes = $context->scope ?? array();
        if (!is_array($token_scopes)) {
            return new WP_Error('invalid_token', 'Token scopes are malformed', array('status' => 401));
        }
        $missing_scopes = array_diff($required_scopes, $token_scopes);
        if (!empty($missing_scopes)) {
            return new WP_Error('insufficient_scope', 'Token does not have required permissions', array('status' => 403));
        }
        // Verify merchant ID matches.
        $metadata = $this->metadata_provider->get_metadata();
        if (!$metadata->paypal_merchant_id) {
            return new WP_Error('merchant_not_configured', 'Merchant ID is not configured', array('status' => 500));
        }
        $external_ids = $context->external_id ?? array();
        if (!is_array($external_ids)) {
            return new WP_Error('invalid_token', 'Token merchant identifiers are malformed', array('status' => 401));
        }
        $expected_id = 'PayPal:' . $metadata->paypal_merchant_id;
        $has_merchant_id = in_array($expected_id, $external_ids, \true);
        if (!$has_merchant_id) {
            return new WP_Error('merchant_mismatch', 'Token is not valid for this merchant', array('status' => 403));
        }
        return \true;
    }
    /**
     * @param string|null $auth_header Bearer token from Authorization header.
     * @return string|WP_Error The encoded JWT string, or WP_Error on failure.
     */
    protected function extract_jwt_from_header(?string $auth_header)
    {
        $string_token = trim($auth_header ?? '');
        if ($string_token === '') {
            return new WP_Error('missing_token', 'Please provide a valid token', array('status' => 401));
        }
        if (0 !== stripos($string_token, 'Bearer')) {
            return new WP_Error('invalid_jwt', 'Please provide a valid token', array('status' => 401));
        }
        $jwt = trim((string) substr($string_token, 6));
        if (empty($jwt)) {
            return new WP_Error('missing_token', 'Bearer prefix without token found', array('status' => 401));
        }
        if (2 !== substr_count($jwt, '.')) {
            return new WP_Error('invalid_jwt', 'Wrong number of segments in the token', array('status' => 401));
        }
        return $jwt;
    }
}
