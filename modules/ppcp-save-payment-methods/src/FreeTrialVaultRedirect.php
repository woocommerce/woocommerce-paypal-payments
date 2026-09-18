<?php

/**
 * Creates the PayPal vault-without-purchase approval redirect for a $0 free-trial
 * subscription placed through the native "Place order" button.
 *
 * Builds a Vault v3 setup token whose approval returns to the vault-return
 * endpoint, stashes the token id and a one-time nonce on the order, and hands back
 * the PayPal approval URL for the gateway to redirect to.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_AJAX;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\FreeTrialVaultReturnEndpoint;
/**
 * Class FreeTrialVaultRedirect
 */
class FreeTrialVaultRedirect
{
    /**
     * The payment method tokens endpoint.
     *
     * @var PaymentMethodTokensEndpoint
     */
    private $payment_method_tokens_endpoint;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * FreeTrialVaultRedirect constructor.
     *
     * @param PaymentMethodTokensEndpoint $payment_method_tokens_endpoint The payment method tokens endpoint.
     * @param LoggerInterface             $logger                         The logger.
     */
    public function __construct(PaymentMethodTokensEndpoint $payment_method_tokens_endpoint, LoggerInterface $logger)
    {
        $this->payment_method_tokens_endpoint = $payment_method_tokens_endpoint;
        $this->logger = $logger;
    }
    /**
     * Creates a setup token and returns the PayPal approval URL to redirect to.
     *
     * Returns an empty string on any failure (no approval link, API error), so the
     * caller can fall back to its existing "no saved PayPal account" handling.
     *
     * @param WC_Order $wc_order The pending WC order.
     * @return string The approval URL, or '' when one could not be obtained.
     */
    public function create_redirect_url(WC_Order $wc_order): string
    {
        $nonce = wp_generate_password(32, \false);
        $return_url = add_query_arg(array('ppcp_vault_wc_order' => $wc_order->get_id(), 'ppcp_vault_nonce' => $nonce), home_url(WC_AJAX::get_endpoint(FreeTrialVaultReturnEndpoint::ENDPOINT)));
        $customer_id = is_user_logged_in() ? (string) get_user_meta(get_current_user_id(), '_ppcp_target_customer_id', \true) : '';
        /**
         * Suppress ArgumentTypeCoercion
         *
         * @psalm-suppress ArgumentTypeCoercion
         */
        $payment_source = new PaymentSource('paypal', (object) array('usage_type' => 'MERCHANT', 'experience_context' => (object) array('return_url' => esc_url_raw($return_url), 'cancel_url' => esc_url_raw(wc_get_checkout_url()), 'user_action' => 'CONTINUE')));
        try {
            $result = $this->payment_method_tokens_endpoint->setup_tokens($payment_source, $customer_id);
        } catch (Exception $exception) {
            $this->logger->error("Free-trial vault redirect: could not create setup token for WC order {$wc_order->get_id()}: " . $exception->getMessage());
            return '';
        }
        $setup_token_id = isset($result->id) ? (string) $result->id : '';
        $approve_url = $this->approve_url($result);
        if (!$setup_token_id || !$approve_url) {
            $this->logger->error("Free-trial vault redirect: setup token response for WC order {$wc_order->get_id()} has no id or approval link.");
            return '';
        }
        $wc_order->update_meta_data(FreeTrialVaultReturnEndpoint::SETUP_TOKEN_META, $setup_token_id);
        $wc_order->update_meta_data(FreeTrialVaultReturnEndpoint::RETURN_NONCE_META, $nonce);
        $wc_order->save();
        return $approve_url;
    }
    /**
     * Extracts the buyer-approval URL from a setup token response.
     *
     * @param object $result The decoded setup token response.
     * @return string The approval URL, or '' when absent.
     */
    private function approve_url(object $result): string
    {
        foreach ((array) ($result->links ?? array()) as $link) {
            $rel = $link->rel ?? '';
            if (('approve' === $rel || 'payer-action' === $rel) && isset($link->href)) {
                return (string) $link->href;
            }
        }
        return '';
    }
}
