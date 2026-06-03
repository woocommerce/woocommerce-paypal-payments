<?php

/**
 * Fetches credentials for an instance.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
/**
 * Class LoginSeller
 */
class LoginSeller
{
    use \WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
    /**
     * The host.
     *
     * @var string
     */
    private $host;
    /**
     * The partner merchant id.
     *
     * @var string
     */
    private $partner_merchant_id;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * LoginSeller constructor.
     *
     * @param string          $host The host.
     * @param string          $partner_marchant_id The partner merchant id.
     * @param LoggerInterface $logger The logger.
     */
    public function __construct(string $host, string $partner_marchant_id, LoggerInterface $logger)
    {
        $this->host = $host;
        $this->partner_merchant_id = $partner_marchant_id;
        $this->logger = $logger;
    }
    /**
     * Fetches credentials for a shared id, auth code and seller nonce.
     *
     * @param string $shared_id The shared id.
     * @param string $auth_code The auth code.
     * @param string $seller_nonce The seller nonce.
     *
     * @return \stdClass
     * @throws RuntimeException If the request fails.
     */
    public function credentials_for(string $shared_id, string $auth_code, string $seller_nonce): \stdClass
    {
        $token = $this->generate_token_for($shared_id, $auth_code, $seller_nonce);
        $url = trailingslashit($this->host) . 'v1/customer/partners/' . $this->partner_merchant_id . '/merchant-integrations/credentials/';
        $args = array('method' => 'GET', 'headers' => array('Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'));
        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            $error = new RuntimeException(__('Could not fetch credentials.', 'woocommerce-paypal-payments'));
            $this->logger->log('warning', $error->getMessage(), array('args' => $args, 'response' => $response));
            throw $error;
        }
        $json = json_decode($response['body']);
        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (!isset($json->client_id) || !isset($json->client_secret)) {
            $error = isset($json->details) ? new PayPalApiException($json, $status_code) : new RuntimeException(__('Credentials not found.', 'woocommerce-paypal-payments'));
            $this->logger->log('warning', $error->getMessage(), array('args' => $args, 'response' => $response));
            throw $error;
        }
        return $json;
    }
    /**
     * Generates a token for a shared id and auth token and seller nonce.
     *
     * @param string $shared_id The shared id.
     * @param string $auth_code The auth code.
     * @param string $seller_nonce The seller nonce.
     *
     * @return string
     * @throws RuntimeException If the request fails.
     */
    private function generate_token_for(string $shared_id, string $auth_code, string $seller_nonce): string
    {
        $url = trailingslashit($this->host) . 'v1/oauth2/token/';
        $args = array('method' => 'POST', 'headers' => array(
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            'Authorization' => 'Basic ' . base64_encode($shared_id . ':'),
        ), 'body' => array('grant_type' => 'authorization_code', 'code' => $auth_code, 'code_verifier' => $seller_nonce));
        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            $error = new RuntimeException(__('Could not create token.', 'woocommerce-paypal-payments'));
            $this->logger->log('warning', $error->getMessage(), array('args' => $args, 'response' => $response));
            throw $error;
        }
        $json = json_decode($response['body']);
        $status_code = (int) wp_remote_retrieve_response_code($response);
        if (!isset($json->access_token)) {
            $error = isset($json->details) ? new PayPalApiException($json, $status_code) : new RuntimeException(__('No token found.', 'woocommerce-paypal-payments'));
            $this->logger->log('warning', $error->getMessage(), array('args' => $args, 'response' => $response));
            throw $error;
        }
        return (string) $json->access_token;
    }
}
