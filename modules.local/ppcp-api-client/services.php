<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Factory\LineItemFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;

return [

    'api.host' => function(ContainerInterface $container) : string {
        return 'https://api.sandbox.paypal.com';
    },
    'api.key' => function(ContainerInterface $container) : string {
        return 'AQB97CzMsd58-It1vxbcDAGvMuXNCXRD9le_XUaMlHB_U7XsU9IiItBwGQOtZv9sEeD6xs2vlIrL4NiD';
    },
    'api.secret' => function(ContainerInterface $container) : string {
        return 'EILGMYK_0iiSbja8hT-nCBGl0BvKxEB4riHgyEO7QWDeUzCJ5r42JUEvrI7gpGyw0Qww8AIXxSdCIAny';
    },
    'api.bearer' => function(ContainerInterface $container) : Bearer {
        return new Bearer(
            $container->get('api.host'),
            $container->get('api.key'),
            $container->get('api.secret')
        );
    },
    'api.endpoint.order' => function(ContainerInterface $container) : OrderEndpoint {
        $sessionHandler = $container->get('session.handler');
        return new OrderEndpoint(
            $container->get('api.host'),
            $container->get('api.bearer'),
            $sessionHandler
        );
    },
    'api.cart-repository' => function(ContainerInterface $container) : CartRepository {
        $cart = WC()->cart;
        $factory = $container->get('api.line-item-factory');
        return new CartRepository($cart, $factory);
    },
    'api.line-item-factory' => function(ContainerInterface $container) : LineItemFactory {
        return new LineItemFactory();
    },
];
