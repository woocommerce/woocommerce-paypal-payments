<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class Token
{

    private $json;
    private $created;

    public function __construct(\stdClass $json)
    {
        if (! isset($json->created)) {
            $json->created = time();
        }
        if (! $this->validate($json)) {
            throw new RuntimeException("Token not valid");
        }
        $this->json = $json;
    }

    public function expirationTimestamp(): int
    {

        return $this->json->created + $this->json->expires_in;
    }

    public function token(): string
    {
        return (string) $this->json->token;
    }

    public function isValid(): bool
    {
        return time() < $this->json->created + $this->json->expires_in;
    }

    public function asJson(): string
    {
        return json_encode($this->json);
    }

    public static function fromJson(string $json): self
    {
        $json = (object) json_decode($json);
        if (isset($json->access_token) || isset($json->client_token)) {
            $json->token = isset($json->access_token) ? $json->access_token : $json->client_token;
        }

        return new Token($json);
    }

    private function validate(\stdClass $json): bool
    {
        $propertyMap = [
            'created' => 'is_int',
            'expires_in' => 'is_int',
            'token' => 'is_string',
        ];

        foreach ($propertyMap as $property => $validator) {
            if (! isset($json->{$property}) || ! $validator($json->{$property})) {
                return false;
            }
        }
        return true;
    }
}
