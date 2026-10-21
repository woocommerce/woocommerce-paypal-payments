<?php

/**
 * The Create Payment Token endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Service\SetupTokenBinding;
use WooCommerce\PayPalCommerce\WcPaymentTokens\WooCommercePaymentTokens;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;
/**
 * Class CreatePaymentToken
 */
class CreatePaymentToken implements EndpointInterface
{
    const ENDPOINT = 'ppc-create-payment-token';
    /**
     * The request data.
     *
     * @var RequestData
     */
    private $request_data;
    /**
     * The payment method tokens endpoint.
     *
     * @var PaymentMethodTokensEndpoint
     */
    private $payment_method_tokens_endpoint;
    /**
     * The WC payment tokens.
     *
     * @var WooCommercePaymentTokens
     */
    private $wc_payment_tokens;
    private FreeTrialSubscriptionHelper $free_trial_helper;
    private SetupTokenBinding $setup_token_binding;
    private LoggerInterface $logger;
    public function __construct(RequestData $request_data, PaymentMethodTokensEndpoint $payment_method_tokens_endpoint, WooCommercePaymentTokens $wc_payment_tokens, FreeTrialSubscriptionHelper $free_trial_helper, SetupTokenBinding $setup_token_binding, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
        $this->payment_method_tokens_endpoint = $payment_method_tokens_endpoint;
        $this->wc_payment_tokens = $wc_payment_tokens;
        $this->free_trial_helper = $free_trial_helper;
        $this->setup_token_binding = $setup_token_binding;
        $this->logger = $logger;
    }
    /**
     * Returns the nonce.
     *
     * @return string
     */
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    /**
     * Handles the request.
     *
     * @throws Exception On Error.
     */
    public function handle_request(): void
    {
        try {
            $data = $this->request_data->read_request($this->nonce());
            $wc_token_id = 0;
            $setup_token_id = is_string($data['vault_setup_token'] ?? null) ? $data['vault_setup_token'] : '';
            if (!$this->setup_token_binding->consume($setup_token_id)) {
                $this->logger->warning('Rejected a setup token exchange: the token was not created by the current customer.');
                wp_send_json_error(null, 403);
            }
            /**
             * Suppress ArgumentTypeCoercion
             *
             * @psalm-suppress ArgumentTypeCoercion
             */
            $payment_source = new PaymentSource('token', (object) array('id' => $setup_token_id, 'type' => 'SETUP_TOKEN'));
            $customer_id = get_user_meta(get_current_user_id(), '_ppcp_target_customer_id', \true);
            $result = $this->payment_method_tokens_endpoint->create_payment_token($payment_source, (string) $customer_id);
            if (is_user_logged_in() && isset($result->customer->id)) {
                $current_user_id = get_current_user_id();
                if ($customer_id && (string) $customer_id !== (string) $result->customer->id) {
                    $this->logger->warning("Rejected a setup token exchange: PayPal returned a different customer than the one stored for user {$current_user_id}.");
                    wp_send_json_error(null, 403);
                }
                update_user_meta($current_user_id, '_ppcp_target_customer_id', $result->customer->id);
                if (isset($result->payment_source->paypal)) {
                    $email = '';
                    if (isset($result->payment_source->paypal->email_address)) {
                        $email = $result->payment_source->paypal->email_address;
                    }
                    $wc_token_id = $this->wc_payment_tokens->create_payment_token_paypal($current_user_id, $result->id, $email);
                }
                if (isset($result->payment_source->card)) {
                    $wc_token_id = $this->wc_payment_tokens->create_payment_token_card($current_user_id, $result);
                    // Asked of the cart, not the request: the client's answer
                    // predates any coupon applied on the checkout.
                    if ($this->free_trial_helper->is_free_trial_cart()) {
                        WC()->session->set('ppcp_card_payment_token_for_free_trial', $wc_token_id);
                    }
                }
            }
            wp_send_json_success($wc_token_id);
        } catch (NonceValidationException $error) {
            wp_send_json_error(array('message' => $error->getMessage()), 400);
        } catch (Exception $exception) {
            wp_send_json_error();
        }
    }
}
