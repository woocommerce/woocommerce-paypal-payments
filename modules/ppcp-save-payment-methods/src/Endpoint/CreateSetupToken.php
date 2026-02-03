<?php

/**
 * The Create Setup Token endpoint.
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
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
/**
 * Class CreateSetupToken
 */
class CreateSetupToken implements EndpointInterface
{
    const ENDPOINT = 'ppc-create-setup-token';
    /**
     * The request data helper.
     *
     * @var RequestData
     */
    private $request_data;
    /**
     * Payment Method Tokens endpoint.
     *
     * @var PaymentMethodTokensEndpoint
     */
    private $payment_method_tokens_endpoint;
    /**
     * CreateSetupToken constructor.
     *
     * @param RequestData                 $request_data The request data helper.
     * @param PaymentMethodTokensEndpoint $payment_method_tokens_endpoint Payment Method Tokens endpoint.
     */
    public function __construct(RequestData $request_data, PaymentMethodTokensEndpoint $payment_method_tokens_endpoint)
    {
        $this->request_data = $request_data;
        $this->payment_method_tokens_endpoint = $payment_method_tokens_endpoint;
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
            $payment_source = new PaymentSource('paypal', (object) array('usage_type' => 'MERCHANT', 'experience_context' => (object) array('return_url' => esc_url(wc_get_account_endpoint_url('payment-methods')), 'cancel_url' => esc_url(wc_get_account_endpoint_url('add-payment-method')))));
            $payment_method = $data['payment_method'] ?? '';
            if ($payment_method === CreditCardGateway::ID) {
                $properties = (object) array();
                $verification_method = $data['verification_method'] ?? '';
                if ($verification_method === 'SCA_WHEN_REQUIRED' || $verification_method === 'SCA_ALWAYS') {
                    $properties = (object) array('verification_method' => $verification_method, 'usage_type' => 'MERCHANT', 'experience_context' => (object) array('return_url' => esc_url(wc_get_account_endpoint_url('payment-methods')), 'cancel_url' => esc_url(wc_get_account_endpoint_url('add-payment-method'))));
                }
                $payment_source = new PaymentSource('card', $properties);
            }
            $customer_id = get_user_meta(get_current_user_id(), '_ppcp_target_customer_id', \true);
            $result = $this->payment_method_tokens_endpoint->setup_tokens($payment_source, (string) $customer_id);
            wp_send_json_success($result);
            return \true;
        } catch (Exception $exception) {
            wp_send_json_error();
            return \false;
        }
    }
}
