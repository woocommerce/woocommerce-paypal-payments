<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

return [

    'api.merchant_email' => function ($container) : string {
        $settings = $container->get('wcgateway.settings');
        return $settings->has('merchant_email') ? (string) $settings->get('merchant_email') : '';
    },
    'api.merchant_id' => function ($container) : string {
        $settings = $container->get('wcgateway.settings');
        return $settings->has('merchant_id') ? (string) $settings->get('merchant_id') : '';
    },
    'api.partner_merchant_id' => static function () : string {
        // ToDo: Replace with the real merchant id of platform
        return 'KQ8FCM66JFGDL';
    },
    'api.key' => function ($container) : string {
        $settings = $container->get('wcgateway.settings');
        $key = $settings->has('client_id') ? (string) $settings->get('client_id') : '';
        return $key;
    },
    'api.secret' => function ($container) : string {
        $settings = $container->get('wcgateway.settings');
        return $settings->has('client_secret') ? (string) $settings->get('client_secret') : '';
    },
];
