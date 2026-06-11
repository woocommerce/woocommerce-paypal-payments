<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\VaultComponent\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcPaymentTokens\PaymentTokenPayPal;
class CreateVaultOrderEndpoint implements EndpointInterface
{
    const ENDPOINT = 'ppc-vault-create-order';
    private RequestData $request_data;
    private OrderEndpoint $order_endpoint;
    private PurchaseUnitFactory $purchase_unit_factory;
    private ShippingPreferenceFactory $shipping_preference_factory;
    private LoggerInterface $logger;
    public function __construct(RequestData $request_data, OrderEndpoint $order_endpoint, PurchaseUnitFactory $purchase_unit_factory, ShippingPreferenceFactory $shipping_preference_factory, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
        $this->order_endpoint = $order_endpoint;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->shipping_preference_factory = $shipping_preference_factory;
        $this->logger = $logger;
    }
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    /**
     * Builds a PaymentSource that hints PayPal to open the paysheet pre-selecting the
     * buyer's existing vaulted funding instrument (the "change FI" flow). Returns null
     * when the current user has no saved PayPal token, in which case the order is
     * created without a payment_source.
     */
    private function preferred_vault_payment_source(): ?PaymentSource
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return null;
        }
        $tokens = WC_Payment_Tokens::get_customer_tokens($user_id, PayPalGateway::ID);
        $paypal_tokens = array_filter($tokens, static function ($token) {
            return $token instanceof PaymentTokenPayPal;
        });
        if (empty($paypal_tokens)) {
            return null;
        }
        $primary_token = reset($paypal_tokens);
        $vault_id = (string) $primary_token->get_token();
        if ('' === $vault_id) {
            return null;
        }
        // PayPal requires `return_url` whenever `experience_context` is set, even though
        // the vault component opens an in-page paysheet rather than redirecting.
        $return_url = wc_get_checkout_url();
        return new PaymentSource('paypal', (object) array('experience_context' => (object) array('return_url' => $return_url, 'cancel_url' => $return_url, 'user_action' => ExperienceContext::USER_ACTION_CONTINUE, 'preferred_payment_source' => (object) array('vault_id' => $vault_id))));
    }
    public function handle_request(): void
    {
        try {
            $this->request_data->read_request($this->nonce());
            $purchase_unit = $this->purchase_unit_factory->from_wc_cart();
            $shipping_preference = $this->shipping_preference_factory->from_state($purchase_unit, 'checkout');
            $payment_source = $this->preferred_vault_payment_source();
            // The Vault Component opens the PayPal paysheet so the consumer can edit
            // their funding instrument. We hint the buyer's existing vault via
            // `payment_source.paypal.experience_context.preferred_payment_source.vault_id`
            // (added through OrderEndpoint::create's $payment_source argument) — that
            // shape does NOT auto-capture. `strip_request_body()` still removes any
            // `payment_source.paypal.token` (which would auto-capture and skip the
            // paysheet) and the payer/items/etc. fields that can trigger Orders v2 5xx
            // errors here.
            add_filter('ppcp_create_order_request_body_data', array($this, 'strip_request_body'), 99);
            try {
                $order = $this->order_endpoint->create(array($purchase_unit), $shipping_preference, null, '', array(), $payment_source);
            } finally {
                remove_filter('ppcp_create_order_request_body_data', array($this, 'strip_request_body'), 99);
            }
            wp_send_json_success(array('id' => $order->id()));
        } catch (\Exception $exception) {
            $this->logger->error('Vault Component: Failed to create order. ' . $exception->getMessage());
            wp_send_json_error(array('message' => $exception->getMessage()));
        }
    }
    /**
     * Strips fields from the create-order request body that would either auto-capture
     * the order or trigger Orders v2 5xx errors for the vault "change FI" flow.
     *
     * Keeps only the amount of the first purchase unit, removes the payer, and removes
     * any `payment_source.paypal.token` (supporting both array and object shapes).
     *
     * @param array $data The create-order request body data.
     *
     * @return array
     */
    public function strip_request_body(array $data): array
    {
        if (isset($data['purchase_units'][0]['amount'])) {
            $data['purchase_units'] = array(array('amount' => $data['purchase_units'][0]['amount']));
        }
        unset($data['payer']);
        if (isset($data['payment_source']['paypal'])) {
            $paypal = $data['payment_source']['paypal'];
            if (is_array($paypal) && isset($paypal['token'])) {
                unset($data['payment_source']['paypal']['token']);
            } elseif (is_object($paypal) && isset($paypal->token)) {
                unset($data['payment_source']['paypal']->token);
            }
        }
        return $data;
    }
}
