<?php

/**
 * Service that allows calling internal REST endpoints from server-side.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

use Throwable;
use RuntimeException;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WP_Http_Cookie;
/**
 * Consume internal REST endpoints from server-side.
 *
 * This service makes a real HTTP request to the endpoint, forwarding the
 * authentication cookies of the current request to maintain the user session
 * while invoking a completely isolated and freshly initialized server request.
 */
class InternalRestService
{
    /**
     * Logger instance.
     *
     * In this case, the logger is quite important for debugging, because the main
     * functionality of this class cannot be step-debugged using Xdebug: While
     * a Xdebug session is active, the remote call to the current server is also
     * blocked and will end in a timeout.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * Constructor.
     *
     * @param LoggerInterface $logger Logger instance.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    /**
     * Performs a REST call to the defined local REST endpoint.
     *
     * @param string $endpoint The endpoint for which the token is generated.
     * @return mixed The REST response.
     * @throws RuntimeException In case the remote request fails, an exception is thrown.
     */
    public function get_response(string $endpoint)
    {
        $rest_url = rest_url($endpoint);
        $rest_nonce = wp_create_nonce('wp_rest');
        $auth_cookies = $this->build_authentication_cookie();
        $this->logger->info("Calling internal REST endpoint [{$rest_url}]");
        $response = wp_remote_request($rest_url, array('method' => 'GET', 'headers' => array('Content-Type' => 'application/json', 'X-WP-Nonce' => $rest_nonce), 'cookies' => $auth_cookies));
        $this->logger->debug("Finished internal REST call [{$rest_url}]");
        if (is_wp_error($response)) {
            // Error: The wp_remote_request() call failed (timeout or similar).
            $error = new RuntimeException('Internal REST error');
            $this->logger->error($error->getMessage(), array('response' => $response));
            throw $error;
        }
        $body = wp_remote_retrieve_body($response);
        try {
            $json = json_decode($body, \true, 512, \JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            // Error: The returned body-string is not valid JSON.
            $error = new RuntimeException('Internal REST error: Invalid JSON response');
            $this->logger->error($error->getMessage(), array('error' => $exception->getMessage(), 'response_body' => $body));
            throw $error;
        }
        $this->logger->info('Internal REST success!', array('json' => $json));
        return $json;
    }
    /**
     * Generate the cookie collection with relevant WordPress authentication
     * cookies, which allows us to extend the current user's session to the
     * called REST endpoint.
     *
     * @return array A list of cookies that are required to authenticate the user.
     */
    private function build_authentication_cookie(): array
    {
        $cookies = array();
        // Cookie names are defined in constants and can be changed by site owners.
        $wp_cookie_constants = array('AUTH_COOKIE', 'SECURE_AUTH_COOKIE', 'LOGGED_IN_COOKIE');
        foreach ($wp_cookie_constants as $cookie_const) {
            $cookie_name = (string) constant($cookie_const);
            if (!isset($_COOKIE[$cookie_name])) {
                continue;
            }
            $cookies[] = new WP_Http_Cookie(array(
                'name' => $cookie_name,
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                'value' => wp_unslash($_COOKIE[$cookie_name]),
            ));
        }
        return $cookies;
    }
}
