<?php

namespace WooCommerce\PayPalCommerce\Axo\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\SdkClientToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
/**
 * Handles the request for the PayPal Axo script attributes.
 */
class AxoScriptAttributes implements EndpointInterface
{
    const ENDPOINT = 'ppc-axo-script-attributes';
    protected Context $context;
    private RequestData $request_data;
    private LoggerInterface $logger;
    private SdkClientToken $sdk_client_token;
    private bool $axo_eligible;
    public function __construct(RequestData $request_data, LoggerInterface $logger, SdkClientToken $sdk_client_token, bool $axo_eligible, Context $context)
    {
        $this->request_data = $request_data;
        $this->logger = $logger;
        $this->sdk_client_token = $sdk_client_token;
        $this->axo_eligible = $axo_eligible;
        $this->context = $context;
    }
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    public function handle_request(): bool
    {
        $this->request_data->read_request($this->nonce());
        if (!$this->axo_eligible || is_user_logged_in() || $this->context->is_paypal_continuation()) {
            wp_send_json_error('Failed to load axo script attributes.');
            return \false;
        }
        try {
            $token = $this->sdk_client_token->sdk_client_token();
        } catch (PayPalApiException $exception) {
            $this->logger->error($exception->getMessage());
            wp_send_json_error($exception->getMessage());
            return \false;
        }
        wp_send_json_success(array('sdk_client_token' => $token));
        return \true;
    }
}
