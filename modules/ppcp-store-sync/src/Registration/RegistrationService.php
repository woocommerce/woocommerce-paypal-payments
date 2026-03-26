<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Registration;

use Firebase\JWT\JWT;
use Exception;
use WP_Error;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadataProvider;
use WooCommerce\PayPalCommerce\StoreSync\Config\AgenticWebhookConfiguration;
class RegistrationService
{
    private const REGISTRATION_TOKEN_KEY = 'ppcp_agentic_registration_token';
    private const ERROR_REGISTRATION_FAILED = 'registration_failed';
    private const ERROR_DEREGISTRATION_FAILED = 'deregistration_failed';
    private const ERROR_WEBHOOK_REQUEST = 'webhook_request_failed';
    private const ERROR_WEBHOOK_RESPONSE = 'webhook_response_failed';
    private AgenticWebhookConfiguration $webhook_urls;
    private MerchantMetadataProvider $metadata_provider;
    private LoggerInterface $logger;
    public function __construct(AgenticWebhookConfiguration $webhook_urls, MerchantMetadataProvider $metadata_provider, LoggerInterface $logger)
    {
        $this->webhook_urls = $webhook_urls;
        $this->metadata_provider = $metadata_provider;
        $this->logger = $logger;
    }
    /**
     * Register this store with PayPal Agentic Commerce.
     *
     * @return RegistrationResult|WP_Error
     */
    public function register()
    {
        if ($this->is_registered()) {
            return new WP_Error(self::ERROR_REGISTRATION_FAILED, 'Already registered');
        }
        $token = $this->create_token();
        $result = $this->call_installation_endpoint($token);
        if (is_wp_error($result)) {
            $this->logger->error('Registration failed: Endpoint returned error', array('endpoint' => $this->webhook_urls->get_registration_install_url(), 'error_code' => $result->get_error_code(), 'error_message' => $result->get_error_message()));
            return $result;
        }
        if ($result->success) {
            $this->save_registration_token($token);
            do_action('woocommerce_paypal_payments_store_sync_registered');
        } else {
            $this->delete_registration_token();
            $this->logger->error('Registration failed: Endpoint rejected registration', array('endpoint' => $this->webhook_urls->get_registration_install_url(), 'error' => $result->error ?? 'Registration failed', 'message' => $result->message, 'payload' => $this->metadata_provider->get_metadata()));
            return new WP_Error(self::ERROR_REGISTRATION_FAILED, $result->error ?? 'Registration failed');
        }
        return $result;
    }
    /**
     * Deregister store from PayPal Agentic Commerce.
     *
     * @return RegistrationResult|WP_Error|null Null if the store was not registered.
     */
    public function deregister()
    {
        if (!$this->is_registered()) {
            return null;
        }
        $token = (string) $this->get_registration_token();
        $result = $this->call_uninstallation_endpoint($token);
        if (is_wp_error($result)) {
            $this->logger->error('Deregistration failed: Endpoint returned error', array('endpoint' => $this->webhook_urls->get_registration_uninstall_url(), 'error_code' => $result->get_error_code(), 'error_message' => $result->get_error_message()));
            return $result;
        }
        if (!$result->success) {
            $this->logger->error('Deregistration failed: Endpoint rejected deregistration', array('endpoint' => $this->webhook_urls->get_registration_uninstall_url(), 'error' => $result->error ?? 'Deregistration failed', 'message' => $result->message));
            return new WP_Error(self::ERROR_DEREGISTRATION_FAILED, $result->error ?? 'Deregistration failed');
        }
        $this->delete_registration_token();
        do_action('woocommerce_paypal_payments_store_sync_deregistered');
        return $result;
    }
    /**
     * Checks if the current store is registered to support PayPal Agentic Commerce.
     *
     * @return bool
     */
    public function is_registered(): bool
    {
        return (bool) $this->get_registration_token();
    }
    /**
     * Returns the store data used to register the store with PayPal Agentic Commerce.
     *
     * @return null|array
     */
    public function get_registration_data(): ?array
    {
        $jwt_token = $this->get_registration_token();
        if (!$jwt_token) {
            return null;
        }
        $parts = explode('.', $jwt_token);
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Partially decode a token, no obfuscation.
        $body = base64_decode($parts[1]);
        if (!$body) {
            return null;
        }
        try {
            return json_decode($body, \true, 512, \JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            return null;
        }
    }
    /**
     * Create a JWT token with store metadata.
     *
     * The token is signed with a dummy key (HS256) as PayPal does not validate
     * the signature - it only serves as a transport mechanism for store metadata.
     */
    private function create_token(): string
    {
        $metadata = $this->metadata_provider->get_metadata();
        $payload = array('storeName' => $metadata->store_name, 'storeUrl' => $metadata->store_url, 'apiBaseUrl' => $metadata->api_base_url, 'country' => $metadata->store_country, 'currency' => $metadata->currency, 'paypalMerchantId' => $metadata->paypal_merchant_id, 'wooSydeCommerceId' => $metadata->store_url, 'catalogDownloadUrl' => $metadata->catalog_url, 'favIcon' => '', 'shippingCountries' => array('US'));
        return JWT::encode($payload, 'no-signature', 'HS256');
    }
    /**
     * Call the "installation" (registration) endpoint.
     *
     * @param string $token JWT token with store metadata.
     * @return RegistrationResult|WP_Error
     */
    private function call_installation_endpoint(string $token)
    {
        return $this->webhook_call($token, $this->webhook_urls->get_registration_install_url());
    }
    /**
     * Call the "uninstallation" (deregistration) endpoint.
     *
     * @param string $token Previously generated registration token.
     * @return RegistrationResult|WP_Error
     */
    private function call_uninstallation_endpoint(string $token)
    {
        return $this->webhook_call($token, $this->webhook_urls->get_registration_uninstall_url());
    }
    /**
     * Make a call to PayPal's webhook endpoints.
     *
     * @param string $token       JWT token with store metadata.
     * @param string $webhook_url The absolute webhook URL to call.
     * @return RegistrationResult|WP_Error
     */
    private function webhook_call(string $token, string $webhook_url)
    {
        $response = wp_remote_post($webhook_url, array('body' => $token, 'headers' => array('Content-Type' => 'text/plain')));
        if (is_wp_error($response)) {
            $this->logger->error('Webhook request failed: HTTP request error', array('endpoint' => $webhook_url, 'error_code' => $response->get_error_code(), 'error_message' => $response->get_error_message()));
            return new WP_Error(self::ERROR_WEBHOOK_REQUEST, $response->get_error_message());
        }
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        try {
            $body = json_decode($body, \true, 512, \JSON_THROW_ON_ERROR);
        } catch (Exception $exception) {
            $this->logger->error('Webhook response parse failed: Invalid JSON received from endpoint', array('endpoint' => $webhook_url, 'http_status_code' => $status_code, 'exception_message' => $exception->getMessage(), 'raw_response_body' => $body));
            return new WP_Error(self::ERROR_WEBHOOK_RESPONSE, $exception->getMessage());
        }
        return new \WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationResult($body['success'] ?? \false, $body['message'] ?? '', $body['error'] ?? null);
    }
    /**
     * Return the previously stored registration token.
     *
     * Protected to allow mocking in tests.
     *
     * @return string|false
     */
    protected function get_registration_token()
    {
        return get_option(self::REGISTRATION_TOKEN_KEY);
    }
    /**
     * Save the new registration token.
     *
     * Protected to allow mocking in tests.
     *
     * @param string $token Registration token.
     */
    protected function save_registration_token(string $token): void
    {
        update_option(self::REGISTRATION_TOKEN_KEY, $token);
    }
    /**
     * Delete registration token.
     *
     * Protected to allow mocking in tests.
     */
    protected function delete_registration_token(): void
    {
        delete_option(self::REGISTRATION_TOKEN_KEY);
    }
}
