<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

trait RequestTrait
{

    //phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
    private function request(string $url, array $args)
    {

        /**
         * This filter can be used to alter the request args.
         * For example, during testing, the PayPal-Mock-Response header could be
         * added here.
         */
        $args = apply_filters('ppcp-request-args', $args, $url);
        if (! isset($args['headers']['PayPal-Partner-Attribution-Id'])) {
            $args['headers']['PayPal-Partner-Attribution-Id'] = 'Woo_PPCP';
        }

        return wp_remote_get($url, $args);
    }
}
