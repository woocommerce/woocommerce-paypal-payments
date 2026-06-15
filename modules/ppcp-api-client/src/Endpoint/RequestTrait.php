<?php

/**
 * The RequestTrait wraps the wp_remote_get functionality for the API client.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WP_Error;
/**
 * Trait RequestTrait
 */
trait RequestTrait
{
    /**
     * Whether to log the detailed request/response info.
     *
     * @var bool
     */
    protected $is_request_logging_enabled = \true;
    /**
     * Performs a request
     *
     * @param string $url The URL to request.
     * @param array  $args The arguments by which to request.
     *
     * @return array|WP_Error
     */
    private function request(string $url, array $args)
    {
        $args['timeout'] = 30;
        /**
         * This filter can be used to alter the request args.
         * For example, during testing, the PayPal-Mock-Response header could be
         * added here.
         */
        $args = apply_filters('ppcp_request_args', $args, $url);
        $response = wp_remote_get($url, $args);
        if ($this->is_request_logging_enabled) {
            $this->logger->debug($this->request_response_string($url, $args, $response));
        }
        if ($this->should_retry_after_auth_failure($url, $args, $response)) {
            /**
             * Filters the request args before a single retry that follows an
             * authentication failure. Listeners are expected to refresh the
             * cached access token and rebuild the `Authorization` header so the
             * retry uses a token with the current scopes.
             *
             * @param array  $args The request arguments.
             * @param string $url  The request URL.
             */
            $args = apply_filters('ppcp_retry_request_args', $args, $url);
            $response = wp_remote_get($url, $args);
            if ($this->is_request_logging_enabled) {
                $this->logger->debug($this->request_response_string($url, $args, $response));
            }
        }
        return $response;
    }
    /**
     * Determines whether a request should be retried once after an
     * authentication failure.
     *
     * A cached access token can outlive a change in the merchant's granted
     * scopes (for example, vaulting being enabled after the token was issued),
     * which surfaces as HTTP 401, or HTTP 403 with `NOT_AUTHORIZED`. Retrying
     * once with a freshly issued token recovers from that state instead of
     * failing for up to the token's lifetime.
     *
     * @param string         $url The request URL.
     * @param array          $args The request arguments.
     * @param array|WP_Error $response The response.
     * @return bool
     */
    private function should_retry_after_auth_failure(string $url, array $args, $response): bool
    {
        if ($response instanceof WP_Error) {
            return \false;
        }
        // Never retry the token request itself; that would risk a request loop.
        if (\false !== strpos($url, 'v1/oauth2/token')) {
            return \false;
        }
        // Only Bearer-authenticated requests can be recovered by a token refresh.
        $authorization = $args['headers']['Authorization'] ?? '';
        if (!is_string($authorization) || 0 !== strpos($authorization, 'Bearer ')) {
            return \false;
        }
        $status_code = (int) ($response['response']['code'] ?? 0);
        if (401 === $status_code) {
            return \true;
        }
        if (403 === $status_code) {
            $body = json_decode((string) ($response['body'] ?? ''));
            return isset($body->name) && 'NOT_AUTHORIZED' === $body->name;
        }
        return \false;
    }
    /**
     * Returns request and response information as string.
     *
     * @param string         $url The request URL.
     * @param array          $args The request arguments.
     * @param array|WP_Error $response The response.
     * @return string
     */
    private function request_response_string(string $url, array $args, $response): string
    {
        $method = $args['method'] ?? '';
        $output = $method . ' ' . $url . "\n";
        if (isset($args['body'])) {
            if (!in_array($url, array(trailingslashit($this->host) . 'v1/oauth2/token/', trailingslashit($this->host) . 'v1/oauth2/token?grant_type=client_credentials'), \true)) {
                $output .= 'Request Body: ' . wc_print_r($args['body'], \true) . "\n";
            }
        }
        if ($response instanceof WP_Error) {
            $output .= 'Request failed. WP error message: ' . implode("\n", $response->get_error_messages()) . "\n";
            return $output;
        }
        if (isset($response['headers']->getAll()['paypal-debug-id'])) {
            $output .= 'Response Debug ID: ' . $response['headers']->getAll()['paypal-debug-id'] . "\n";
        }
        if (isset($response['response'])) {
            $output .= 'Response: ' . wc_print_r($response['response'], \true) . "\n";
            if (isset($response['body']) && isset($response['response']['code']) && !in_array($response['response']['code'], array(200, 201, 202, 204), \true)) {
                $output .= 'Response Body: ' . wc_print_r($response['body'], \true) . "\n";
            }
        }
        return $output;
    }
}
