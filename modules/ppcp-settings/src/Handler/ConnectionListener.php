<?php

/**
 * Handles connection-requests, that connect the current site to a PayPal
 * merchant account.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Handler
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Handler;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use RuntimeException;
use WooCommerce\PayPalCommerce\Settings\Service\AuthenticationManager;
use WooCommerce\PayPalCommerce\Settings\Service\OnboardingUrlManager;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Http\RedirectorInterface;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
/**
 * Provides a listener that handles merchant-connection requests.
 *
 * Those connection requests are made after the merchant logs into their PayPal
 * account (inside the login popup). At the last step, they see a "Return to
 * Store" button.
 * Clicking that button triggers the merchant-connection request.
 */
class ConnectionListener
{
    /**
     * Token processing states
     */
    private const TOKEN_STATE_PROCESSING = 'processing';
    private const TOKEN_STATE_PROCESSED = 'processed';
    /**
     * Transient key for storing token state.
     */
    private const TOKEN_STATE_TRANSIENT = 'ppcp_auth_token_state';
    /**
     * ID of the current settings page; empty if not on a PayPal settings page.
     *
     * @var string
     */
    private string $settings_page_id;
    /**
     * Access to the onboarding URL manager.
     *
     * @var OnboardingUrlManager
     */
    private OnboardingUrlManager $url_manager;
    /**
     * Authentication manager service, responsible to update connection details.
     *
     * @var AuthenticationManager
     */
    private AuthenticationManager $authentication_manager;
    /**
     * A redirector-instance to redirect the merchant after authentication.
     * ™
     *
     * @var RedirectorInterface
     */
    private RedirectorInterface $redirector;
    /**
     * Logger instance, mainly used for debugging purposes.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * ID of the current user, set by the process() method.
     *
     * Default value is 0 (guest), until the real ID is provided to process().
     *
     * @var int
     */
    private int $user_id = 0;
    /**
     * The request details (usually the GET data) which were provided.
     *
     * @var array
     */
    private array $request_data = array();
    /**
     * Prepare the instance.
     *
     * @param string                $settings_page_id       Current plugin settings page ID.
     * @param OnboardingUrlManager  $url_manager            Get OnboardingURL instances.
     * @param AuthenticationManager $authentication_manager Authentication manager service.
     * @param RedirectorInterface   $redirector             Redirect-handler.
     * @param ?LoggerInterface      $logger                 The logger, for debugging purposes.
     */
    public function __construct(string $settings_page_id, OnboardingUrlManager $url_manager, AuthenticationManager $authentication_manager, RedirectorInterface $redirector, ?LoggerInterface $logger = null)
    {
        $this->settings_page_id = $settings_page_id;
        $this->url_manager = $url_manager;
        $this->authentication_manager = $authentication_manager;
        $this->redirector = $redirector;
        $this->logger = $logger ?: new NullLogger();
    }
    /**
     * Process the request data, and extract connection details, if present.
     *
     * @param int   $user_id The current user ID.
     * @param array $request Request details to process.
     *
     * @throws RuntimeException If the merchant ID does not match the ID previously set via OAuth.
     */
    public function process(int $user_id, array $request): void
    {
        $this->user_id = $user_id;
        $this->request_data = $request;
        if (!$this->is_valid_request()) {
            return;
        }
        $token = $this->get_token_from_request();
        $this->process_oauth_token($token);
        $this->redirect_after_authentication();
    }
    /**
     * Processes the OAuth token from the request.
     *
     * @param string $token The OAuth token extracted from the request.
     * @return void
     */
    private function process_oauth_token(string $token): void
    {
        if (!$token) {
            return;
        }
        $log_token = (string) substr($token, 0, 2) . '...' . (string) substr($token, -6);
        if ($this->was_token_processed($token)) {
            /*
             * Token already processed:
             * Do nothing as the DB already contains the full connection details.
             */
            $this->logger->info('Token already processed, continuing silently', array('token' => $log_token));
            return;
        }
        if ($this->is_token_processing($token)) {
            /*
             * Authentication token is currently processed (in another request):
             * Briefly wait and then retry this request, basically waiting for
             * the above "was_processed" condition to become true.
             */
            $this->logger->info('Token is currently being processed, waiting and retrying', array('token' => $log_token));
            sleep(1);
            // Get the current URL.
            $current_url = add_query_arg(null, null);
            // Add time to the query parameter to prevent browser cache issues.
            $current_url = add_query_arg('retry', microtime(\true), $current_url);
            wp_safe_redirect($current_url);
            exit;
        }
        if (!$this->url_manager->validate_token_and_delete($token, $this->user_id)) {
            $this->logger->error('Token validation failed', array('token' => $log_token));
            return;
        }
        $data = $this->extract_data();
        if (!$data) {
            $this->logger->error('Failed to extract merchant data from request');
            return;
        }
        $this->logger->info('Found OAuth merchant data in request', $data);
        try {
            $this->set_token_state($token, self::TOKEN_STATE_PROCESSING);
            $this->authentication_manager->handle_oauth_authentication($data);
        } catch (\Exception $e) {
            $this->logger->error('Failed to complete authentication: ' . $e->getMessage());
        }
        $this->set_token_state($token, self::TOKEN_STATE_PROCESSED);
    }
    /**
     * Determine, if the request details contain connection data that should be
     * extracted and stored.
     *
     * @return bool True, if the request contains valid connection details.
     */
    private function is_valid_request(): bool
    {
        if ($this->user_id < 1 || !$this->settings_page_id) {
            return \false;
        }
        if (!user_can($this->user_id, 'manage_woocommerce')) {
            return \false;
        }
        $required_params = array('merchantIdInPayPal', 'merchantId', 'ppcpToken');
        foreach ($required_params as $param) {
            if (empty($this->request_data[$param])) {
                return \false;
            }
        }
        return \true;
    }
    /**
     * Sets the state for a token.
     *
     * @param string $token The token to set state for.
     * @param string $state The state to set.
     * @return void
     */
    private function set_token_state(string $token, string $state): void
    {
        $data = array('token' => $token, 'state' => $state);
        // 10 second expiration will block the page for max 10 seconds.
        set_transient(self::TOKEN_STATE_TRANSIENT, $data, 10);
    }
    /**
     * Gets the current state of a token.
     *
     * @param string $token The token to check.
     * @return string The current state of the token, or empty string if the token doesn't match.
     */
    private function get_token_state(string $token): string
    {
        $data = get_transient(self::TOKEN_STATE_TRANSIENT);
        if (empty($data) || !is_array($data) || empty($data['token']) || empty($data['state'])) {
            return '';
        }
        // Only return the state if the token matches.
        return $data['token'] === $token ? $data['state'] : '';
    }
    /**
     * Checks if the token is currently being processed.
     *
     * @param string $token The token to check.
     * @return bool True if the token is currently being processed, false otherwise.
     */
    private function is_token_processing(string $token): bool
    {
        return $this->get_token_state($token) === self::TOKEN_STATE_PROCESSING;
    }
    /**
     * Checks if the token has been fully processed.
     *
     * @param string $token The token to check.
     * @return bool True if the token has been processed, false otherwise.
     */
    private function was_token_processed(string $token): bool
    {
        return $this->get_token_state($token) === self::TOKEN_STATE_PROCESSED;
    }
    /**
     * Extract the merchant details (ID & email) from the request details.
     *
     * @return array Structured array with 'is_sandbox', 'merchant_id', and 'merchant_email' keys,
     *               or an empty array on failure.
     */
    private function extract_data(): array
    {
        $this->logger->info('Extracting connection data from request...');
        $merchant_id = $this->get_merchant_id_from_request($this->request_data);
        $merchant_email = $this->get_merchant_email_from_request($this->request_data);
        $seller_type = $this->get_seller_type_from_request($this->request_data);
        if (!$merchant_id || !$merchant_email) {
            return array();
        }
        return array('merchant_id' => $merchant_id, 'merchant_email' => $merchant_email, 'seller_type' => $seller_type);
    }
    /**
     * Redirects the browser page at the end of the authentication flow.
     *
     * @return void
     */
    private function redirect_after_authentication(): void
    {
        $redirect_url = $this->get_onboarding_redirect_url();
        $this->redirector->redirect($redirect_url);
        exit;
    }
    /**
     * Returns the sanitized connection token from the incoming request.
     *
     * @return string The sanitized token, or an empty string.
     */
    private function get_token_from_request(): string
    {
        return $this->sanitize_string($this->request_data['ppcpToken'] ?? '');
    }
    /**
     * Returns the sanitized merchant ID from the incoming request.
     *
     * @param array $request Full request details.
     *
     * @return string The sanitized merchant ID, or an empty string.
     */
    private function get_merchant_id_from_request(array $request): string
    {
        return $this->sanitize_string($request['merchantIdInPayPal'] ?? '');
    }
    /**
     * Returns the sanitized merchant email from the incoming request.
     *
     * Note that the email is provided via the argument "merchantId", which
     * looks incorrect at first, but PayPal uses the email address as merchant
     * ID, and offers a more anonymous ID via the "merchantIdInPayPal" argument.
     *
     * @param array $request Full request details.
     *
     * @return string The sanitized merchant email, or an empty string.
     */
    private function get_merchant_email_from_request(array $request): string
    {
        return $this->sanitize_merchant_email($request['merchantId'] ?? '');
    }
    /**
     * Returns the sanitized seller type, based on the incoming request.
     *
     * PayPal reports this via an `accountStatus` GET parameter, which can have
     * the value "BUSINESS_ACCOUNT", or "???". This method translates the GET
     * parameter into a SellerTypeEnum value.
     *
     * @param array $request Full request details.
     *
     * @return string A valid SellerTypeEnum value.
     */
    private function get_seller_type_from_request(array $request): string
    {
        $account_status = $request['accountStatus'] ?? '';
        if ('BUSINESS_ACCOUNT' === $account_status) {
            return SellerTypeEnum::BUSINESS;
        }
        if (!$account_status) {
            /**
             * We assume that a valid authentication request that has no
             * accountStatus property must be a personal account.
             *
             * This logic is not officially documented, but was discovered
             * by trial-and-error.
             */
            return SellerTypeEnum::PERSONAL;
        }
        $this->logger->info('Request with unknown accountStatus: ' . $account_status);
        return SellerTypeEnum::UNKNOWN;
    }
    /**
     * Sanitizes a request-argument for processing.
     *
     * @param string $value Value from the request argument.
     *
     * @return string Sanitized value.
     */
    private function sanitize_string(string $value): string
    {
        return trim(sanitize_text_field(wp_unslash($value)));
    }
    /**
     * Sanitizes the merchant's email address for processing.
     *
     * @param string $email The plain email.
     *
     * @return string Sanitized email address.
     */
    private function sanitize_merchant_email(string $email): string
    {
        return sanitize_text_field(str_replace(' ', '+', $email));
    }
    /**
     * Returns the URL opened at the end of onboarding.
     *
     * @return string
     */
    private function get_onboarding_redirect_url(): string
    {
        /**
         * The URL opened at the end of onboarding after saving the merchant ID/email.
         */
        return apply_filters('woocommerce_paypal_payments_onboarding_redirect_url', admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=' . Settings::CONNECTION_TAB_ID));
    }
}
