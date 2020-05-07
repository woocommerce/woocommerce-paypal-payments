<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;


class CreateWcOrderEndpoint implements EndpointInterface
{

    public const ENDPOINT = 'ppc-create-wc-order';

    public static function nonce(): string
    {
        return self::ENDPOINT;
    }

    public function handleRequest(): bool
    {

    }
}