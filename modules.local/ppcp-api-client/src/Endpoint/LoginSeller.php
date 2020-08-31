<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ErrorResponseCollectionFactory;
use Psr\Log\LoggerInterface;

class LoginSeller
{
    use RequestTrait;

    private $host;
    private $partnerMerchantId;
    private $logger;
    public function __construct(
        string $host,
        string $partnerMerchantId,
        LoggerInterface $logger
    ) {

        $this->host = $host;
        $this->partnerMerchantId = $partnerMerchantId;
        $this->logger = $logger;
    }

    public function credentialsFor(
        string $sharedId,
        string $authCode,
        string $sellerNonce
    ): \stdClass {

        $token = $this->generateTokenFor($sharedId, $authCode, $sellerNonce);
        $url = trailingslashit($this->host) .
            'v1/customer/partners/' . $this->partnerMerchantId .
            '/merchant-integrations/credentials/';
        $args = [
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
        ];
        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Could not fetch credentials.', 'woocommerce-paypal-commerce-gateway')
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
        if (! isset($json->client_id) || ! isset($json->client_secret)) {
            $error = isset($json->details) ?
                new PayPalApiException(
                    $json,
                    $statusCode
                ) : new RuntimeException(
                    __('Credentials not found.', 'woocommerce-paypal-commerce-gateway')
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

        return $json;
    }

    private function generateTokenFor(
        string $sharedId,
        string $authCode,
        string $sellerNonce
    ): string {

        $url = trailingslashit($this->host) . 'v1/oauth2/token/';
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($sharedId . ':'),
            ],
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $authCode,
                'code_verifier' => $sellerNonce,
            ],
        ];
        $response = $this->request($url, $args);

        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Could not create token.', 'woocommerce-paypal-commerce-gateway')
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
        if (! isset($json->access_token)) {
            $error = isset($json->details) ?
                new PayPalApiException(
                    $json,
                    $statusCode
                ) : new RuntimeException(
                    __('No token found.', 'woocommerce-paypal-commerce-gateway')
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
        return (string) $json->access_token;
    }
}
