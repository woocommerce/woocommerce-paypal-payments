<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Subscription;

use Inpsyde\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use Inpsyde\PayPalCommerce\Subscription\Repository\PaymentTokenRepository;
use Psr\Container\ContainerInterface;

return [
    'subscription.helper' => function(ContainerInterface $container) : SubscriptionHelper {
        return new SubscriptionHelper();
    },
    'subscription.renewal-handler' => function(ContainerInterface $container) : RenewalHandler {
        $logger = $container->get('woocommerce.logger.woocommerce');
        $repository = $container->get('subscription.repository.payment-token');
        $endpoint = $container->get('api.endpoint.order');
        $purchaseFactory = $container->get('api.factory.purchase-unit');
        $payerFactory = $container->get('api.factory.payer');
        return new RenewalHandler(
            $logger,
            $repository,
            $endpoint,
            $purchaseFactory,
            $payerFactory
        );
    },
    'subscription.repository.payment-token' => function(ContainerInterface $container) : PaymentTokenRepository {
        $factory = $container->get('api.factory.payment-token');
        $endpoint = $container->get('api.endpoint.payment-token');
        return new PaymentTokenRepository($factory, $endpoint);
    }
];
