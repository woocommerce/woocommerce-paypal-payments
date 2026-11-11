<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Auth;

use DomainException;
use Exception;
use InvalidArgumentException;
use WP_Error;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
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
            return $this->key_unavailable('Could not retrieve public JWT key');
        }
        try {
            return JWT::decode($jwt, $keys);
        } catch (InvalidArgumentException $e) {
            // Key object was empty or malformed — corrupt cache.
            \WooCommerce\PayPalCommerce\StoreSync\Auth\PayPalJwkProvider::flush();
            return $this->key_unavailable($e->getMessage());
        } catch (DomainException $e) {
            return $this->malformed_token($e->getMessage());
        } catch (SignatureInvalidException|Exception $e) {
            return $this->invalid_jwt($e->getMessage());
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
            return $this->invalid_payload('Token issuer is not recognized');
        }
        // Verify required scopes are present.
        $token_scopes = $context->scope ?? array();
        if (!is_array($token_scopes)) {
            return $this->invalid_payload('Token scopes are malformed');
        }
        $missing_scopes = array_diff($required_scopes, $token_scopes);
        if (!empty($missing_scopes)) {
            return $this->insufficient_scope('Token does not have required permissions');
        }
        // Verify merchant ID matches.
        $metadata = $this->metadata_provider->get_metadata();
        if (!$metadata->paypal_merchant_id) {
            return $this->merchant_not_configured('Merchant ID is not configured');
        }
        $external_ids = $context->external_id ?? array();
        if (!is_array($external_ids)) {
            return $this->invalid_payload('Token merchant identifiers are malformed');
        }
        $expected_id = 'PayPal:' . $metadata->paypal_merchant_id;
        $has_merchant_id = in_array($expected_id, $external_ids, \true);
        if (!$has_merchant_id) {
            return $this->merchant_mismatch('Token is not valid for this merchant');
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
            return $this->missing_token('Please provide a valid token');
        }
        if (0 !== stripos($string_token, 'Bearer')) {
            return $this->malformed_token('Please provide a valid token');
        }
        $jwt = trim((string) substr($string_token, 6));
        if (empty($jwt)) {
            return $this->missing_token('Bearer prefix without token found');
        }
        if (2 !== substr_count($jwt, '.')) {
            return $this->malformed_token('Wrong number of segments in the token');
        }
        return $jwt;
    }
    private function missing_token(string $message): WP_Error
    {
        return new WP_Error('missing_token', $message, array('status' => 401));
    }
    private function malformed_token(string $message): WP_Error
    {
        return new WP_Error('malformed_token', $message, array('status' => 401));
    }
    private function invalid_jwt(string $message): WP_Error
    {
        return new WP_Error('invalid_jwt', $message, array('status' => 401));
    }
    private function invalid_payload(string $message): WP_Error
    {
        return new WP_Error('invalid_payload', $message, array('status' => 401));
    }
    private function merchant_mismatch(string $message): WP_Error
    {
        return new WP_Error('merchant_mismatch', $message, array('status' => 403));
    }
    private function insufficient_scope(string $message): WP_Error
    {
        return new WP_Error('insufficient_scope', $message, array('status' => 403));
    }
    private function merchant_not_configured(string $message): WP_Error
    {
        return new WP_Error('merchant_not_configured', $message, array('status' => 500));
    }
    private function key_unavailable(string $message): WP_Error
    {
        return new WP_Error('key_unavailable', $message, array('status' => 503));
    }
}
