<?php

/**
 * The Create Payment Token endpoint.
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcSubscriptions\Endpoint;

use Exception;
use WC_Order;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
/**
 * Class SubscriptionChangePaymentMethod
 */
class SubscriptionChangePaymentMethod implements EndpointInterface
{
    const ENDPOINT = 'ppc-subscription-change-payment-method';
    /**
     * The request data.
     *
     * @var RequestData
     */
    private $request_data;
    /**
     * SubscriptionChangePaymentMethod constructor.
     *
     * @param RequestData $request_data $request_data The request data.
     */
    public function __construct(RequestData $request_data)
    {
        $this->request_data = $request_data;
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
            $subscription = wcs_get_subscription($data['subscription_id']);
            if ($subscription instanceof WC_Order) {
                $subscription->set_payment_method($data['payment_method']);
                $wc_payment_token = WC_Payment_Tokens::get($data['wc_payment_token_id']);
                if ($wc_payment_token) {
                    $subscription->add_payment_token($wc_payment_token);
                    $subscription->save();
                }
                wp_send_json_success();
                return \true;
            }
            wp_send_json_error();
            return \false;
        } catch (Exception $exception) {
            wp_send_json_error();
            return \false;
        }
    }
}
