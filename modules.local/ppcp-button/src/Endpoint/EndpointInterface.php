<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

interface EndpointInterface
{

    public static function nonce() : string;

    public function handleRequest() : bool;
}
