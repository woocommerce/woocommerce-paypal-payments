<?php

/**
 * HTTP redirection.
 *
 * @package WooCommerce\PayPalCommerce\Api
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Http;

/**
 * Interface for HTTP redirection.
 */
interface RedirectorInterface
{
    /**
     * Starts HTTP redirection and shutdowns.
     *
     * @param string $location The URL to redirect to.
     */
    public function redirect(string $location): void;
}
