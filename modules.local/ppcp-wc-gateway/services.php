<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;

return [
    'wcgateway.gateway' => function(ContainerInterface $container) : WcGateway {
        $sessionHandler = $container->get('session.handler');
        $cartRepository = $container->get('api.cart-repository');
        $endpoint = $container->get('api.endpoint.order');
        $orderFactory = $container->get('api.factory.order');
        return new WcGateway($sessionHandler, $cartRepository, $endpoint, $orderFactory);
    },
    'wcgateway.disabler' => function(ContainerInterface $container) : DisableGateways {
        $sessionHandler = $container->get('session.handler');
        return new DisableGateways($sessionHandler);
    }
];
