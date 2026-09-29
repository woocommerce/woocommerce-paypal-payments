<?php

/**
 * The Endpoint interface.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Endpoint;

/**
 * Interface EndpointInterface
 */
interface EndpointInterface
{
    /**
     * Returns the nonce for an endpoint.
     */
    public static function nonce(): string;
    /**
     * Handles the request for an endpoint.
     */
    public function handle_request(): void;
}
