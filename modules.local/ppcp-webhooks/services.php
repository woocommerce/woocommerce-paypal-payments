<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks;

use Inpsyde\PayPalCommerce\Webhooks\Handler\PaymentCaptureCompleted;
use Psr\Container\ContainerInterface;

return [

    'webhook.registrar' => function(ContainerInterface $container) : WebhookRegistrar {
        $factory = $container->get('api.factory.webhook');
        $endpoint = $container->get('api.endpoint.webhook');
        $restEndpoint = $container->get('webhook.endpoint.controller');
        return new WebhookRegistrar(
            $factory,
            $endpoint,
            $restEndpoint
        );
    },
    'webhook.endpoint.controller' => function(ContainerInterface $container) : IncomingWebhookEndpoint {
        $handler = $container->get('webhook.endpoint.handler');
        return new IncomingWebhookEndpoint(... $handler);
    },
    'webhook.endpoint.handler' => function(ContainerInterface $container) : array {
        return [
            new PaymentCaptureCompleted(),
        ];
    }
];
