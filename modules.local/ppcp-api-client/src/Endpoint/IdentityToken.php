<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Token;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Psr\Log\LoggerInterface;

class IdentityToken
{
    use RequestTrait;

    private $bearer;
    private $host;
    private $logger;
    private $prefix;

    public function __construct(string $host, Bearer $bearer, LoggerInterface $logger, string $prefix)
    {
        $this->host = $host;
        $this->bearer = $bearer;
        $this->logger = $logger;
        $this->prefix = $prefix;
    }

    public function generateForCustomer(int $customerId): Token
    {

        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v1/identity/generate-token';
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer->token(),
                'Content-Type' => 'application/json',
            ],
        ];
        if ($customerId && defined('PPCP_FLAG_SUBSCRIPTION') && PPCP_FLAG_SUBSCRIPTION) {
            $args['body'] = json_encode(['customer_id' => $this->prefix . $customerId]);
        }

        $response = $this->request($url, $args);

        if (is_wp_error($response)) {
            $error = new RuntimeException(
                __(
                    'Could not create identity token.',
                    'woocommerce-paypal-commerce-gateway'
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

        $token = Token::fromJson($response['body']);
        return $token;
    }
}
