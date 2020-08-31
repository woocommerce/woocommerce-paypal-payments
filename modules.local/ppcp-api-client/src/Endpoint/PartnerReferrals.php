<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ErrorResponseCollectionFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use Psr\Log\LoggerInterface;

//phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
class PartnerReferrals
{
    use RequestTrait;

    private $host;
    private $bearer;
    private $data;
    private $logger;

    public function __construct(
        string $host,
        Bearer $bearer,
        PartnerReferralsData $data,
        LoggerInterface $logger
    ) {

        $this->host = $host;
        $this->bearer = $bearer;
        $this->data = $data;
        $this->logger = $logger;
    }

    public function signupLink(): string
    {
        $data = $this->data->data();
        $bearer = $this->bearer->bearer();
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'body' => json_encode($data),
        ];
        $url = trailingslashit($this->host) . 'v2/customer/partner-referrals';
        $response = $this->request($url, $args);

        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Could not create referral.', 'woocommerce-paypal-commerce-gateway')
            );
            $this->logger->log(
                'warning',
                $error->getMessage(),
                [
                    'args' => $args,
                    'response' => $response,
                ]
            );
            throw $error;
        }
        $json = json_decode($response['body']);
        $statusCode = (int) wp_remote_retrieve_response_code($response);
        if ($statusCode !== 201) {
            $error = new PayPalApiException(
                $json,
                $statusCode
            );
            $this->logger->log(
                'warning',
                $error->getMessage(),
                [
                    'args' => $args,
                    'response' => $response,
                ]
            );
            throw $error;
        }

        foreach ($json->links as $link) {
            if ($link->rel === 'action_url') {
                return (string) $link->href;
            }
        }

        $error = new RuntimeException(
            __('Action URL not found.', 'woocommerce-paypal-commerce-gateway')
        );
        $this->logger->log(
            'warning',
            $error->getMessage(),
            [
                'args' => $args,
                'response' => $response,
            ]
        );
        throw $error;
    }
}
