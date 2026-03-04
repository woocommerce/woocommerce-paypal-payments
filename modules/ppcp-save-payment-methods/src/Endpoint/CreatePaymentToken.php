<?php

/**
 * The Create Payment Token endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens;
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
    /**
     * CreatePaymentToken constructor.
     *
     * @param RequestData                 $request_data The request data.
     * @param PaymentMethodTokensEndpoint $payment_method_tokens_endpoint The payment method tokens endpoint.
     * @param WooCommercePaymentTokens    $wc_payment_tokens The WC payment tokens.
     */
    public function __construct(RequestData $request_data, PaymentMethodTokensEndpoint $payment_method_tokens_endpoint, WooCommercePaymentTokens $wc_payment_tokens)
    {
        $this->request_data = $request_data;
        $this->payment_method_tokens_endpoint = $payment_method_tokens_endpoint;
        $this->wc_payment_tokens = $wc_payment_tokens;
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
     * @return bool
     * @throws Exception On Error.
     */
    public function handle_request(): bool
    {
        try {
            $data = $this->request_data->read_request($this->nonce());
            $wc_token_id = 0;
            /**
             * Suppress ArgumentTypeCoercion
             *
             * @psalm-suppress ArgumentTypeCoercion
             */
            $payment_source = new PaymentSource('token', (object) array('id' => $data['vault_setup_token'], 'type' => 'SETUP_TOKEN'));
            $customer_id = get_user_meta(get_current_user_id(), '_ppcp_target_customer_id', \true);
            $result = $this->payment_method_tokens_endpoint->create_payment_token($payment_source, (string) $customer_id);
            if (is_user_logged_in() && isset($result->customer->id)) {
                $current_user_id = get_current_user_id();
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
                    $is_free_trial_cart = $data['is_free_trial_cart'] ?? '';
                    if ($is_free_trial_cart === '1') {
                        WC()->session->set('ppcp_card_payment_token_for_free_trial', $wc_token_id);
                    }
                }
            }
            wp_send_json_success($wc_token_id);
            return \true;
        } catch (Exception $exception) {
            wp_send_json_error();
            return \false;
        }
    }
}
