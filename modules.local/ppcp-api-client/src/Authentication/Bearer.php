<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Authentication;


use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class Bearer
{
    private const CACHE_KEY = 'ppcp-bearer';
    private $host;
    private $key;
    private $secret;
    public function __construct(string $host, string $key, string $secret)
    {
        $this->host = $host;
        $this->key = $key;
        $this->secret = $secret;
    }

    public function bearer() : string
    {
        //ToDo: Do not store with wp_cache_get but as transient.
        $bearer = wp_cache_get(self::CACHE_KEY);
        if ( ! $bearer) {
            return $this->newBearer();
        }
        return (string) $bearer;
    }

    public function newBearer() : string
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

        $json = json_decode($response['body']);
        if (! isset($json->access_token) || ! isset($json->expires_in)) {
            throw new RuntimeException(__('Could not find token.', 'woocommerce-paypal-commerce-gateway'));
        }
        $token = (string) $json->access_token;
        wp_cache_set(self::CACHE_KEY, $token, $json->expires_in);
        return $token;
    }
}