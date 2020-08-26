<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\IdentityToken;
use Inpsyde\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class DataClientIdEndpoint implements EndpointInterface
{

    public const ENDPOINT = 'ppc-data-client-id';

    private $requestData;
    private $identityToken;
    public function __construct(
        RequestData $requestData,
        IdentityToken $identityToken
    ) {

        $this->requestData = $requestData;
        $this->identityToken = $identityToken;
    }

    public static function nonce(): string
    {
        return self::ENDPOINT;
    }

    public function handleRequest(): bool
    {
        try {
            $this->requestData->readRequest($this->nonce());
            $userId = get_current_user_id();
            $token = $this->identityToken->generateForCustomer($userId);
            wp_send_json([
                'token' => $token->token(),
                'expiration' => $token->expirationTimestamp(),
                'user' => get_current_user_id(),
            ]);
            return true;
        } catch (RuntimeException $error) {
            wp_send_json_error(
                [
                    'name' => is_a($error, PayPalApiException::class) ? $error->name() : '',
                    'message' => $error->getMessage(),
                    'code' => $error->getCode(),
                    'details' => is_a($error, PayPalApiException::class) ? $error->details() : [],
                ]
            );
            return false;
        }
    }
}
