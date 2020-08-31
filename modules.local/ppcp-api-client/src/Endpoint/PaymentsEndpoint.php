<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ErrorResponseCollectionFactory;
use Psr\Log\LoggerInterface;

class PaymentsEndpoint
{
    use RequestTrait;

    private $host;
    private $bearer;
    private $authorizationFactory;
    private $logger;

    public function __construct(
        string $host,
        Bearer $bearer,
        AuthorizationFactory $authorizationsFactory,
        LoggerInterface $logger
    ) {

        $this->host = $host;
        $this->bearer = $bearer;
        $this->authorizationFactory = $authorizationsFactory;
        $this->logger = $logger;
    }

    public function authorization(string $authorizationId): Authorization
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/payments/authorizations/' . $authorizationId;
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
        ];

        $response = $this->request($url, $args);
        $json = json_decode($response['body']);

        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Could not get authorized payment info.', 'woocommerce-paypal-commerce-gateway')
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

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
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

        $authorization = $this->authorizationFactory->fromPayPalRequest($json);
        return $authorization;
    }

    public function capture(string $authorizationId): Authorization
    {
        $bearer = $this->bearer->bearer();
        //phpcs:ignore Inpsyde.CodeQuality.LineLength.TooLong
        $url = trailingslashit($this->host) . 'v2/payments/authorizations/' . $authorizationId . '/capture';
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
        ];

        $response = $this->request($url, $args);
        $json = json_decode($response['body']);

        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Could not capture authorized payment.', 'woocommerce-paypal-commerce-gateway')
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

        $authorization = $this->authorizationFactory->fromPayPalRequest($json);
        return $authorization;
    }
}
