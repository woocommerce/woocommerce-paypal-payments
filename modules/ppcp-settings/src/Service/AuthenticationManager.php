<?php

/**
 * Manages the merchant connection between this plugin and PayPal.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

use Throwable;
use JsonException;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\LoginSeller;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Helper\InMemoryCache;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\WcGateway\Helper\EnvironmentConfig;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\Settings\DTO\OAuthConnectionDTO;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
use WooCommerce\PayPalCommerce\Settings\Endpoint\CommonRestEndpoint;
/**
 * Class that manages the connection to PayPal.
 */
class AuthenticationManager
{
    /**
     * Option name used to store the OAuth connection details.
     */
    private const OAUTH_OPTION_NAME = 'ppcp_oauth_connection_details';
    /**
     * Data model that stores the connection details.
     *
     * @var GeneralSettings
     */
    private GeneralSettings $common_settings;
    /**
     * Logging instance.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * Base URLs for the manual connection attempt, by environment.
     *
     * @var EnvironmentConfig<string>
     */
    private EnvironmentConfig $connection_host;
    /**
     * Login API handler instances, by environment.
     *
     * @var EnvironmentConfig<LoginSeller>
     */
    private EnvironmentConfig $login_endpoint;
    /**
     * Onboarding referrals data.
     *
     * @var PartnerReferralsData
     */
    private PartnerReferralsData $referrals_data;
    /**
     * The connection state manager.
     *
     * @var ConnectionState
     */
    private ConnectionState $connection_state;
    /**
     * Internal REST service, to consume own REST handlers in a separate request.
     *
     * @var InternalRestService
     */
    private \WooCommerce\PayPalCommerce\Settings\Service\InternalRestService $rest_service;
    /**
     * Constructor.
     *
     * @param GeneralSettings      $common_settings  Data model that stores the connection details.
     * @param EnvironmentConfig    $connection_host  API host for direct authentication.
     * @param EnvironmentConfig    $login_endpoint   API handler to fetch merchant credentials.
     * @param PartnerReferralsData $referrals_data   Partner referrals data.
     * @param ConnectionState      $connection_state Connection state manager.
     * @param InternalRestService  $rest_service     Allows calling internal REST endpoints.
     * @param ?LoggerInterface     $logger           Logging instance.
     */
    public function __construct(GeneralSettings $common_settings, EnvironmentConfig $connection_host, EnvironmentConfig $login_endpoint, PartnerReferralsData $referrals_data, ConnectionState $connection_state, \WooCommerce\PayPalCommerce\Settings\Service\InternalRestService $rest_service, ?LoggerInterface $logger = null)
    {
        $this->common_settings = $common_settings;
        $this->connection_host = $connection_host;
        $this->login_endpoint = $login_endpoint;
        $this->referrals_data = $referrals_data;
        $this->connection_state = $connection_state;
        $this->rest_service = $rest_service;
        $this->logger = $logger ?: new NullLogger();
    }
    /**
     * Returns details about the currently connected merchant.
     *
     * @return array
     */
    public function get_account_details(): array
    {
        return array('is_sandbox' => $this->common_settings->is_sandbox_merchant(), 'is_connected' => $this->common_settings->is_merchant_connected(), 'merchant_id' => $this->common_settings->get_merchant_id(), 'merchant_email' => $this->common_settings->get_merchant_email());
    }
    /**
     * Removes any connection details we currently have stored.
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->logger->info('Disconnecting merchant from PayPal...');
        $this->common_settings->reset_merchant_data();
        $this->common_settings->save();
        // Update the connection status and clear the environment flags.
        $this->connection_state->disconnect();
        /**
         * Broadcast, that the plugin disconnected from PayPal. This allows other
         * modules to clean up merchant-related details, such as eligibility flags.
         */
        do_action('woocommerce_paypal_payments_merchant_disconnected');
        /**
         * Request to flush caches after disconnecting the merchant. While there
         * is no need for it here, it's good house-keeping practice to clean up.
         */
        do_action('woocommerce_paypal_payments_flush_api_cache');
        /**
         * Clear the APM eligibility flags from the default settings object.
         */
        do_action('woocommerce_paypal_payments_clear_apm_product_status', null);
    }
    /**
     * Checks if the provided ID and secret have a valid format.
     *
     * Part of the "Direct Connection" (Manual Connection) flow.
     *
     * On failure, an Exception is thrown, while a successful check does not
     * generate any return value.
     *
     * @param string $client_id     The client ID.
     * @param string $client_secret The client secret.
     * @return void
     * @throws RuntimeException When invalid client ID or secret provided.
     */
    public function validate_id_and_secret(string $client_id, string $client_secret): void
    {
        if (empty($client_id)) {
            throw new RuntimeException('No client ID provided.');
        }
        if (\false === preg_match('/^A[\w-]{79}$/', $client_secret)) {
            throw new RuntimeException('Invalid client ID provided.');
        }
        if (empty($client_secret)) {
            throw new RuntimeException('No client secret provided.');
        }
    }
    /**
     * Disconnects the current merchant, and then attempts to connect to a
     * PayPal account using a client ID and secret.
     *
     * Part of the "Direct Connection" (Manual Connection) flow.
     * This connection type is only available to business merchants.
     *
     * @param bool   $use_sandbox   Whether to use the sandbox mode.
     * @param string $client_id     The client ID.
     * @param string $client_secret The client secret.
     * @return void
     * @throws RuntimeException When failed to retrieve payee.
     */
    public function authenticate_via_direct_api(bool $use_sandbox, string $client_id, string $client_secret): void
    {
        $this->logger->info('Attempting manual connection to PayPal...', array('sandbox' => $use_sandbox, 'client_id' => $client_id));
        $payee = $this->request_payee($client_id, $client_secret, $use_sandbox);
        $connection = new MerchantConnectionDTO($use_sandbox, $client_id, $client_secret, $payee['merchant_id'], $payee['email_address'], '', SellerTypeEnum::BUSINESS);
        $this->update_connection_details($connection);
    }
    /**
     * Stores the OAuth details in the DB.
     *
     * Those details are used in another request to complete the OAuth login process.
     *
     * @param string $shared_id   The shared onboarding ID.
     * @param string $auth_code   The authorization code.
     * @param bool   $use_sandbox Whether it's a sandbox or production account.
     * @return void
     */
    public function remember_oauth_connection_details(string $shared_id, string $auth_code, bool $use_sandbox): void
    {
        $this->logger->info('Storing one-time OAuth credentials...', array('shared_id' => $shared_id, 'auth_code' => $auth_code, 'use_sandbox' => $use_sandbox));
        $oauth_connection = new OAuthConnectionDTO($use_sandbox, $shared_id, $auth_code);
        update_option(self::OAUTH_OPTION_NAME, $oauth_connection, \false);
    }
    /**
     * Remove the temporary oauth credentials from the DB.
     *
     * @return void
     */
    private function remove_oauth_connection_details(): void
    {
        delete_option(self::OAUTH_OPTION_NAME);
    }
    /**
     * Returns the previously remembered oauth details.
     *
     * @return OAuthConnectionDTO The stored oauth credentials.
     * @throws RuntimeException When no stored credentials are found, or they have expired.
     */
    private function retrieve_oauth_connection_details(): OAuthConnectionDTO
    {
        $oauth_data = get_option(self::OAUTH_OPTION_NAME);
        if (!$oauth_data instanceof OAuthConnectionDTO) {
            throw new RuntimeException('No stored OAuth credentials found');
        }
        // Check for expiration (credentials expire after 1 hour).
        if (time() - $oauth_data->timestamp > 3600) {
            $this->remove_oauth_connection_details();
            throw new RuntimeException('Stored OAuth credentials have expired');
        }
        return $oauth_data;
    }
    /**
     * Checks if the provided ID and auth-code have a valid format.
     *
     * Part of the "ISU Connection" (login via Popup) flow.
     *
     * On failure, an Exception is thrown, while a successful check does not
     * generate any return value. Note, that we did not find official documentation
     * on those values, so we only check if they are non-empty strings.
     *
     * @param string $shared_id The shared onboarding ID.
     * @param string $auth_code The authorization code.
     * @return void
     * @throws RuntimeException When invalid shared ID or auth provided.
     */
    private function validate_id_and_auth_code(string $shared_id, string $auth_code): void
    {
        if (empty($shared_id)) {
            throw new RuntimeException('No onboarding ID provided.');
        }
        if (empty($auth_code)) {
            throw new RuntimeException('No authorization code provided.');
        }
    }
    /**
     * Disconnects the current merchant, and then attempts to connect to a
     * PayPal account the onboarding ID and authorization ID.
     *
     * Part of the "ISU Connection" (login via Popup) flow.
     *
     * @param bool   $use_sandbox Whether to use the sandbox mode.
     * @param string $shared_id   The OAuth client ID.
     * @param string $auth_code   The OAuth authorization code.
     * @return MerchantConnectionDTO A DTO containing the connection details.
     * @throws RuntimeException When failed to retrieve payee.
     */
    private function authenticate_via_oauth(bool $use_sandbox, string $shared_id, string $auth_code): MerchantConnectionDTO
    {
        $this->logger->info('Attempting OAuth login to PayPal...', array('sandbox' => $use_sandbox, 'shared_id' => $shared_id));
        $credentials = $this->get_credentials($shared_id, $auth_code, $use_sandbox);
        /**
         * Some details are set by `ConnectionListener`. That listener
         * is invoked during the page reload, once the user clicks the blue
         * "Return to Store" button in PayPal's login popup.
         *
         * It sets: merchant_email, seller_type.
         */
        $connection = $this->common_settings->get_merchant_data();
        $connection->is_sandbox = $use_sandbox;
        $connection->client_id = $credentials['client_id'];
        $connection->client_secret = $credentials['client_secret'];
        $connection->merchant_id = $credentials['merchant_id'];
        return $connection;
    }
    /**
     * Handles the full OAuth authentication flow.
     *
     * 1. Verify that the provided request contains required details.
     * 2. Retrieve the oauth-connection details and validate them.
     * 3. Convert the oauth-connection details into a permanent id/secret.
     * 4. Complete the authentication by storing all details in the DB.
     *
     * @param array $request_data Array of request parameters to process.
     * @return void
     *
     * @throws RuntimeException Missing or invalid credentials.
     */
    public function handle_oauth_authentication(array $request_data): void
    {
        $merchant_id = $request_data['merchant_id'] ?? '';
        $merchant_email = $request_data['merchant_email'] ?? '';
        $seller_type = $request_data['seller_type'] ?? '';
        // 1. Verify the request details.
        if (empty($merchant_id) || empty($merchant_email)) {
            throw new RuntimeException('Missing merchant ID or email in request');
        }
        // 2. Retrieve and validate the oauth connection.
        $oauth_connection = $this->retrieve_oauth_connection_details();
        $this->validate_id_and_auth_code($oauth_connection->shared_id, $oauth_connection->auth_token);
        // 3. Trade oauth connection details to permanent client credentials.
        $connection = $this->authenticate_via_oauth($oauth_connection->is_sandbox, $oauth_connection->shared_id, $oauth_connection->auth_token);
        // 4. Complete the authentication checks and persist details.
        if ($connection->merchant_id && $connection->merchant_id !== $merchant_id) {
            throw new RuntimeException('Unexpected merchant ID in request');
        }
        $connection->merchant_id = $merchant_id;
        $connection->merchant_email = $merchant_email;
        if (SellerTypeEnum::is_valid($seller_type)) {
            $connection->seller_type = $seller_type;
        }
        $this->remove_oauth_connection_details();
        $this->update_connection_details($connection);
    }
    // ----------------------------------------------------------------------------
    // Internal helper methods
    /**
     * Retrieves the payee object with the merchant data by creating a minimal PayPal order.
     *
     * Part of the "Direct Connection" (Manual Connection) flow.
     *
     * @param string $client_id     The client ID.
     * @param string $client_secret The client secret.
     * @param bool   $use_sandbox   Whether to use the sandbox mode.
     *
     * @return array Payee details, containing 'merchant_id' and 'merchant_email' keys.
     * @throws RuntimeException When failed to retrieve payee.
     */
    private function request_payee(string $client_id, string $client_secret, bool $use_sandbox): array
    {
        $host = $this->connection_host->get_value($use_sandbox);
        $bearer = new PayPalBearer(new InMemoryCache(), $host, $client_id, $client_secret, $this->logger, null);
        $orders = new Orders($host, $bearer, $this->logger);
        $request_body = array('intent' => 'CAPTURE', 'purchase_units' => array(array('amount' => array('currency_code' => 'USD', 'value' => 1.0))));
        try {
            $response = $orders->create($request_body);
            $body = json_decode($response['body'], \false, 512, \JSON_THROW_ON_ERROR);
            $order_id = $body->id;
            $order_response = $orders->order($order_id);
            $order_body = json_decode($order_response['body'], \false, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            // Cast JsonException to a RuntimeException.
            throw new RuntimeException('Could not decode JSON response: ' . $exception->getMessage());
        } catch (Throwable $exception) {
            // Cast any other Throwable to a RuntimeException.
            throw new RuntimeException($exception->getMessage());
        }
        $pu = $order_body->purchase_units[0];
        $payee = $pu->payee;
        if (!is_object($payee)) {
            throw new RuntimeException('Payee not found.');
        }
        if (!isset($payee->merchant_id, $payee->email_address)) {
            throw new RuntimeException('Payee info not found.');
        }
        return array('merchant_id' => $payee->merchant_id, 'email_address' => $payee->email_address);
    }
    /**
     * Fetches merchant API credentials using a shared onboarding ID and
     * authorization code.
     *
     * Part of the "ISU Connection" (login via Popup) flow.
     *
     * @param string $shared_id   The shared onboarding ID.
     * @param string $auth_code   The authorization code.
     * @param bool   $use_sandbox Whether to use the sandbox mode.
     * @return array
     * @throws RuntimeException When failed to fetch credentials.
     */
    private function get_credentials(string $shared_id, string $auth_code, bool $use_sandbox): array
    {
        $login_handler = $this->login_endpoint->get_value($use_sandbox);
        $nonce = $this->referrals_data->nonce();
        $response = $login_handler->credentials_for($shared_id, $auth_code, $nonce);
        return array('client_id' => (string) ($response->client_id ?? ''), 'client_secret' => (string) ($response->client_secret ?? ''), 'merchant_id' => (string) ($response->payer_id ?? ''));
    }
    /**
     * Fetches additional details about the connected merchant from PayPal
     * and stores them in the DB.
     *
     * This process only works after persisting basic connection details.
     *
     * @return void
     */
    private function enrich_merchant_details(): void
    {
        if (!$this->common_settings->is_merchant_connected()) {
            return;
        }
        try {
            $endpoint = CommonRestEndpoint::seller_account_route(\true);
            $response = $this->rest_service->get_response($endpoint);
            if (!$response['success']) {
                $this->enrichment_failed('Server failed to provide data', $response);
                return;
            }
            $details = $response['data'];
        } catch (Throwable $exception) {
            $this->enrichment_failed($exception->getMessage());
            return;
        }
        if (!isset($details['country'])) {
            $this->enrichment_failed('Missing country in merchant details');
            return;
        }
        // Request the merchant details via a PayPal API request.
        $connection = $this->common_settings->get_merchant_data();
        // Enrich the connection details with additional details.
        $connection->merchant_country = $details['country'];
        // Persist the changes.
        $this->common_settings->set_merchant_data($connection);
        $this->common_settings->save();
    }
    /**
     * When the `enrich_merchant_details()` call fails, this method might
     * set up a cron task to retry the attempt after some time.
     *
     * @param string $reason  Reason for the failure, will be logged.
     * @param mixed  $details Optional. Additional details to log.
     * @return void
     */
    private function enrichment_failed(string $reason, $details = null): void
    {
        $this->logger->warning('Failed to enrich merchant details: ' . $reason, array('reason' => $reason, 'details' => $details));
        // TODO: Schedule a cron task to retry the enrichment, e.g. with wp_schedule_single_event().
    }
    /**
     * Stores the provided details in the data model.
     *
     * @param MerchantConnectionDTO $connection Connection details to persist.
     * @return void
     */
    private function update_connection_details(MerchantConnectionDTO $connection): void
    {
        $this->logger->info('Updating connection details', (array) $connection);
        $this->common_settings->set_merchant_data($connection);
        $this->common_settings->save();
        if ($this->common_settings->is_merchant_connected()) {
            $this->logger->info('Merchant successfully connected to PayPal');
            // Update the connection status and set the environment flags.
            $this->connection_state->connect($connection->is_sandbox);
            // At this point, we can use the PayPal API to get more details about the seller.
            $this->enrich_merchant_details();
            /**
             * Request to flush caches before authenticating the merchant, to
             * ensure the new merchant does not use stale data from previous
             * connections.
             */
            do_action('woocommerce_paypal_payments_flush_api_cache');
            /**
             * Broadcast that the plugin connected to a new PayPal merchant account.
             * This is the right time to initialize merchant relative flags for the
             * first time.
             */
            do_action('woocommerce_paypal_payments_authenticated_merchant');
            /**
             * Clear the APM eligibility flags from the default settings object.
             */
            do_action('woocommerce_paypal_payments_clear_apm_product_status', null);
            /**
             * Subscribe the new merchant to relevant PayPal webhooks.
             */
            do_action(WebhookRegistrar::EVENT_HOOK);
        }
    }
}
