<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding\Endpoint;


use Inpsyde\PayPalCommerce\ApiClient\Endpoint\LoginSeller;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use Inpsyde\PayPalCommerce\Button\Endpoint\EndpointInterface;
use Inpsyde\PayPalCommerce\Button\Endpoint\RequestData;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGatewayInterface;

class LoginSellerEndpoint implements EndpointInterface
{
    public const ENDPOINT = 'ppc-login-seller';

    private $requestData;
    private $loginSellerEndpoint;
    private $partnerReferralsData;
    private $gateway;
    public function __construct(
        RequestData $requestData,
        LoginSeller $loginSellerEndpoint,
        PartnerReferralsData $partnerReferralsData,
        \WC_Payment_Gateway $gateway
    ) {
        $this->requestData = $requestData;
        $this->loginSellerEndpoint = $loginSellerEndpoint;
        $this->partnerReferralsData = $partnerReferralsData;
        $this->gateway = $gateway;
    }

    public static function nonce(): string
    {
        return self::ENDPOINT;
    }

    public function handleRequest(): bool
    {

        try {
            $data = $this->requestData->readRequest($this->nonce());
            $credentials = $this->loginSellerEndpoint->credentialsFor(
                $data['sharedId'],
                $data['authCode'],
                $this->partnerReferralsData->nonce()
            );
            $this->gateway->update_option('client_secret', $credentials->client_secret);
            $this->gateway->update_option('client_id', $credentials->client_id);
            wp_send_json_success();
            return true;
        } catch (\RuntimeException $error) {
            wp_send_json_error($error->getMessage());
            return false;
        }
    }
}
