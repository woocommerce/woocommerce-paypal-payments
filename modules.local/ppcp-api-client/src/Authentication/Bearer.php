<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Authentication;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Token;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Psr\SimpleCache\CacheInterface;

class Bearer
{
    private const CACHE_KEY = 'ppcp-bearer';
    private $cache;
    private $host;
    private $key;
    private $secret;
    public function __construct(CacheInterface $cache, string $host, string $key, string $secret)
    {
        $this->cache = $cache;
        $this->host = $host;
        $this->key = $key;
        $this->secret = $secret;
    }

    public function bearer() : Token
    {
        try {
            $bearer = Token::fromJson((string) $this->cache->get(self::CACHE_KEY));
            return ($bearer->isValid()) ? $bearer : $this->newBearer();
        } catch (RuntimeException $error) {
            return $this->newBearer();
        }
    }

    private function newBearer() : Token
    {
        $url = trailingslashit($this->host) . 'v1/oauth2/token?grant_type=client_credentials';
        $args = [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->key . ':' . $this->secret),
            ],
        ];
        $response = wp_remote_post(
            $url,
            $args
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            throw new RuntimeException(__('Could not create token.', 'woocommerce-paypal-commerce-gateway'));
        }

        $token = Token::fromJson($response['body']);
        $this->cache->set(self::CACHE_KEY, $token, $token->asJson());
        return $token;
    }
}
