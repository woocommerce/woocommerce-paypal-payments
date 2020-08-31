<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentToken;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;
use Psr\Log\LoggerInterface;

class PaymentTokenEndpoint
{
    use RequestTrait;

    private $bearer;
    private $host;
    private $factory;
    private $logger;
    private $prefix;
    public function __construct(
        string $host,
        Bearer $bearer,
        PaymentTokenFactory $factory,
        LoggerInterface $logger,
        string $prefix
    ) {

        $this->host = $host;
        $this->bearer = $bearer;
        $this->factory = $factory;
        $this->logger = $logger;
        $this->prefix = $prefix;
    }

    // phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
    /**
     * @param int $id
     * @return PaymentToken[]
     */
    public function forUser(int $id): array
    {
        $bearer = $this->bearer->bearer();

        $customerId = $this->prefix . $id;
        $url = trailingslashit($this->host) . 'v2/vault/payment-tokens/?customer_id=' . $customerId;
        $args = [
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
            ],
        ];

        $response = $this->request($url, $args);
        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Could not fetch payment token.', 'woocommerce-paypal-commerce-gateway')
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

        $tokens = [];
        foreach ($json->payment_tokens as $tokenValue) {
            $tokens[] = $this->factory->fromPayPalResponse($tokenValue);
        }
        if (empty($tokens)) {
            $error = new RuntimeException(
                sprintf(
                    // translators: %d is the customer id.
                    __('No token stored for customer %d.', 'woocommerce-paypal-commerce-gateway'),
                    $id
                )
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
        return $tokens;
    }
    // phpcs:enable Inpsyde.CodeQuality.FunctionLength.TooLong

    public function deleteToken(PaymentToken $token): bool
    {

        $bearer = $this->bearer->bearer();

        $url = trailingslashit($this->host) . 'v2/vault/payment-tokens/' . $token->id();
        $args = [
            'method' => 'DELETE',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
            ],
        ];

        $response = $this->request($url, $args);

        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __('Could not delete payment token.', 'woocommerce-paypal-commerce-gateway')
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

        return wp_remote_retrieve_response_code($response) === 204;
    }
}
