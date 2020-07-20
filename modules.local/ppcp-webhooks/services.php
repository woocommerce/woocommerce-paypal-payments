<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks;

use Inpsyde\PayPalCommerce\Webhooks\Handler\CheckoutOrderCompleted;
use Inpsyde\PayPalCommerce\Webhooks\Handler\PaymentCaptureCompleted;
use Inpsyde\PayPalCommerce\Webhooks\Handler\PaymentCaptureRefunded;
use Inpsyde\PayPalCommerce\Webhooks\Handler\PaymentCaptureReversed;
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
        $webhookEndpoint = $container->get('api.endpoint.webhook');
        $webhookFactory = $container->get('api.factory.webhook');
        $handler = $container->get('webhook.endpoint.handler');
        $logger = $container->get('woocommerce.logger.woocommerce');
        $verifyRequest = ! defined('PAYPAL_WEBHOOK_REQUEST_VERIFICATION') || PAYPAL_WEBHOOK_REQUEST_VERIFICATION;

        return new IncomingWebhookEndpoint(
            $webhookEndpoint,
            $webhookFactory,
            $logger,
            $verifyRequest,
            ... $handler
        );
    },
    'webhook.endpoint.handler' => function(ContainerInterface $container) : array {
        $logger = $container->get('woocommerce.logger.woocommerce');
        return [
            new CheckoutOrderCompleted($logger),
            new PaymentCaptureRefunded($logger),
            new PaymentCaptureReversed($logger),
            new PaymentCaptureCompleted($logger),
        ];
    }
];
