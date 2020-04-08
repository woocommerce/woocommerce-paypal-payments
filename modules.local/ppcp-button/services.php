<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button;

use Inpsyde\PayPalCommerce\Button\Assets\SmartButton;
use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\RequestData;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;

return [
    'button.smart-button' => function (ContainerInterface $container) : SmartButton {
        $isSandbox = true;
        return new SmartButton($container->get('button.url'), $isSandbox);
    },
    'button.url' => function (ContainerInterface $container) : string {
        return plugins_url(
            '/modules/ppcp-button/',
            dirname(__FILE__, 3) . '/woocommerce-paypal-commerce-gateway.php'
        );
    },
    'button.request-data' => function (ContainerInterface $container) : RequestData {
        return new RequestData();
    },
    'button.endpoint.change-cart' => function (ContainerInterface $container) : ChangeCartEndpoint {
        if (! \WC()->cart) {
            throw new RuntimeException('cant initialize endpoint at this moment');
        }
        $cart = WC()->cart;
        $shipping = WC()->shipping();
        $requestData = $container->get('button.request-data');
        $repository = $container->get('api.cart-repository');
        return new ChangeCartEndpoint($cart, $shipping, $requestData, $repository);
    },
    'button.endpoint.create-order' => function (ContainerInterface $container) : CreateOrderEndpoint {
        $requestData = $container->get('button.request-data');
        $repository = $container->get('api.cart-repository');
        $apiClient = $container->get('api.endpoint.order');
        return new CreateOrderEndpoint($requestData, $repository, $apiClient);
    },
    'button.endpoint.approve-order' => function (ContainerInterface $container) : ApproveOrderEndpoint {
        $requestData = $container->get('button.request-data');
        $apiClient = $container->get('api.endpoint.order');
        $sessionHandler = $container->get('session.handler');
        return new ApproveOrderEndpoint($requestData, $apiClient, $sessionHandler);
    },
];
