<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Authorization;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ErrorResponseCollectionFactory;

class PaymentsEndpoint
{
    private $host;
    private $bearer;
    private $authorizationFactory;
    private $errorResponseFactory;

    public function __construct(
        string $host,
        Bearer $bearer,
        AuthorizationFactory $authorizationsFactory,
        ErrorResponseCollectionFactory $errorResponseFactory
    ) {
        $this->host = $host;
        $this->bearer = $bearer;
        $this->authorizationFactory = $authorizationsFactory;
        $this->errorResponseFactory = $errorResponseFactory;
    }

    public function authorization(string $authorizationId): Authorization
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/payments/authorizations/' . $authorizationId;
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
        ];

        $response = wp_remote_get($url, $args);
        $json = json_decode($response['body']);

        if (is_wp_error($response)) {
            $this->handleResponseWpError($url, $args);
            throw new RuntimeException(
                __(
                    'Could not get authorized payment info.',
                    'woocommerce-paypal-commerce-gateway'
                )
            );
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            $errors = $this->errorResponseFactory->fromPayPalResponse(
                $json,
                (int)wp_remote_retrieve_response_code($response),
                $url,
                $args
            );
            add_action('woocommerce-paypal-commerce-gateway.error', $errors);
            throw new RuntimeException(
                __(
                    'Could not get authorized payment info.',
                    'woocommerce-paypal-commerce-gateway'
                )
            );
        }

        $authorization = $this->authorizationFactory->fromPayPalRequest($json);
        return $authorization;
    }

    public function capture(string $authorizationId): Authorization
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/payments/authorizations/' . $authorizationId . '/capture';
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
        ];

        $response = wp_remote_post($url, $args);
        $json = json_decode($response['body']);

        if (is_wp_error($response)) {
            $this->handleResponseWpError($url, $args);
            throw new RuntimeException(
                __(
                    'Could not capture authorized payment.',
                    'woocommerce-paypal-commerce-gateway'
                )
            );
        }

        if (wp_remote_retrieve_response_code($response) !== 201) {
            $errors = $this->errorResponseFactory->fromPayPalResponse(
                $json,
                (int)wp_remote_retrieve_response_code($response),
                $url,
                $args
            );
            add_action('woocommerce-paypal-commerce-gateway.error', $errors);
            throw new RuntimeException(
                __(
                    'Could not capture authorized payment.',
                    'woocommerce-paypal-commerce-gateway'
                )
            );
        }

        $authorization = $this->authorizationFactory->fromPayPalRequest($json);
        return $authorization;
    }

    private function handleResponseWpError(string $url, array $args)
    {
        $errors = $this->errorResponseFactory->unknownError(
            $url,
            $args
        );
        add_action('woocommerce-paypal-commerce-gateway.error', $errors);
    }
}
