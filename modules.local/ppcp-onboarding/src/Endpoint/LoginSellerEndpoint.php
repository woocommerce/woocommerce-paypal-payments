<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\LoginSeller;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use Inpsyde\PayPalCommerce\Button\Endpoint\EndpointInterface;
use Inpsyde\PayPalCommerce\Button\Endpoint\RequestData;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGatewayInterface;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\Webhooks\WebhookRegistrar;

class LoginSellerEndpoint implements EndpointInterface
{
    public const ENDPOINT = 'ppc-login-seller';

    private $requestData;
    private $loginSellerEndpoint;
    private $partnerReferralsData;
    private $settings;
    public function __construct(
        RequestData $requestData,
        LoginSeller $loginSellerEndpoint,
        PartnerReferralsData $partnerReferralsData,
        Settings $settings
    ) {

        $this->requestData = $requestData;
        $this->loginSellerEndpoint = $loginSellerEndpoint;
        $this->partnerReferralsData = $partnerReferralsData;
        $this->settings = $settings;
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
            $this->settings->set('client_secret', $credentials->client_secret);
            $this->settings->set('client_id', $credentials->client_id);
            $this->settings->persist();
            wp_schedule_single_event(
                time() - 1,
                WebhookRegistrar::EVENT_HOOK
            );
            wp_send_json_success();
            return true;
        } catch (\RuntimeException $error) {
            wp_send_json_error($error->getMessage());
            return false;
        }
    }
}
