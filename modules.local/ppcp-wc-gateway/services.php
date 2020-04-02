<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;

return [
    'wcgateway.gateway' => function(ContainerInterface $container) : WcGateway {
        $sessionHandler = $container->get('session.handler');
        $endpoint = $container->get('api.endpoint.order');
        return new WcGateway($sessionHandler, $endpoint);
    },
    'wcgateway.disabler' => function(ContainerInterface $container) : DisableGateways {
        $sessionHandler = $container->get('session.handler');
        return new DisableGateways($sessionHandler);
    }
];
