<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Authentication;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Token;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class PayPalBearer implements Bearer
{
    use RequestTrait;

    public const CACHE_KEY = 'ppcp-bearer';
    private $cache;
    private $host;
    private $key;
    private $secret;
    private $logger;
    public function __construct(
        CacheInterface $cache,
        string $host,
        string $key,
        string $secret,
        LoggerInterface $logger
    ) {

        $this->cache = $cache;
        $this->host = $host;
        $this->key = $key;
        $this->secret = $secret;
        $this->logger = $logger;
    }

    public function bearer(): Token
    {
        try {
            $bearer = Token::fromJson((string) $this->cache->get(self::CACHE_KEY));
            return ($bearer->isValid()) ? $bearer : $this->newBearer();
        } catch (RuntimeException $error) {
            return $this->newBearer();
        }
    }

    private function newBearer(): Token
    {
        $url = trailingslashit($this->host) . 'v1/oauth2/token?grant_type=client_credentials';
        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->key . ':' . $this->secret),
            ],
        ];
        $response = $this->request(
            $url,
            $args
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
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

        $token = Token::fromJson($response['body']);
        $this->cache->set(self::CACHE_KEY, $token->asJson());
        return $token;
    }
}
