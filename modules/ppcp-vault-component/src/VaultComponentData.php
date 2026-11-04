<?php

/**
 * Business logic for the vault component: builds the localized script data for the
 * front-end and updates the stored WC payment token with refreshed funding-instrument
 * details after a capture.
 *
 * @package WooCommerce\PayPalCommerce\VaultComponent
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\VaultComponent;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Payment_Token;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\VaultComponent\Authentication\VaultClientToken;
use WooCommerce\PayPalCommerce\VaultComponent\Endpoint\CreateVaultOrderEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenPayPal;
/**
 * Class VaultComponentData
 */
class VaultComponentData
{
    private VaultClientToken $client_token;
    private LoggerInterface $logger;
    public function __construct(VaultClientToken $client_token, LoggerInterface $logger)
    {
        $this->client_token = $client_token;
        $this->logger = $logger;
    }
    /**
     * Adds the vault component configuration to the localized script data for logged-in
     * buyers that have a saved PayPal token.
     *
     * @param array $localized_script_data The localized script data.
     *
     * @return array
     */
    public function add_localized_data(array $localized_script_data): array
    {
        if (!is_user_logged_in()) {
            return $localized_script_data;
        }
        $customer_id = get_current_user_id();
        $wc_tokens = WC_Payment_Tokens::get_customer_tokens($customer_id, PayPalGateway::ID);
        $paypal_tokens = array_filter($wc_tokens, static function ($token) {
            return $token instanceof PaymentTokenPayPal;
        });
        if (empty($paypal_tokens)) {
            return $localized_script_data;
        }
        $primary_token = reset($paypal_tokens);
        $vault_id = (string) $primary_token->get_token();
        $localized_script_data['vault_component'] = array('is_eligible' => \true, 'token_id' => $vault_id, 'wc_token_id' => (string) $primary_token->get_id(), 'ajax' => array('create_order' => array('endpoint' => \WC_AJAX::get_endpoint(CreateVaultOrderEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(CreateVaultOrderEndpoint::nonce()))));
        try {
            $localized_script_data['vault_component']['sdk_client_token'] = $this->client_token->client_token($vault_id);
        } catch (RuntimeException $exception) {
            $message = $exception instanceof PayPalApiException ? $exception->get_details($exception->getMessage()) : $exception->getMessage();
            $this->logger->error('Failed to mint vault client_token: ' . $message);
        }
        return $localized_script_data;
    }
    /**
     * After Path A capture, updates the WC payment token with the new FI details
     * from the PayPal order response.
     *
     * @param Order $order The PayPal order.
     */
    public function update_token_fi_details(Order $order): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $approved_order_id = wc_clean(wp_unslash($_POST['paypal_order_id'] ?? ''));
        if (!$approved_order_id) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $token_db_id = wc_clean(wp_unslash($_POST['wc-ppcp-gateway-payment-token'] ?? ''));
        if (!$token_db_id || 'new' === $token_db_id) {
            return;
        }
        // A token id is always a positive integer; reject anything else before a DB lookup.
        if ((int) $token_db_id < 1) {
            return;
        }
        $token = WC_Payment_Tokens::get((int) $token_db_id);
        if (!$token instanceof WC_Payment_Token) {
            return;
        }
        $payment_source = $order->payment_source();
        if (!$payment_source) {
            return;
        }
        $props = $payment_source->properties();
        $card_brand = $props->brand ?? '';
        $card_last4 = $props->last_digits ?? '';
        if ($card_brand) {
            $token->update_meta_data('_ppcp_card_brand', $card_brand);
        }
        if ($card_last4) {
            $token->update_meta_data('_ppcp_card_last4', $card_last4);
        }
        if ($card_brand || $card_last4) {
            $token->save();
        }
    }
}
