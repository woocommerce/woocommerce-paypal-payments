<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;
use Inpsyde\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsFields;

return [
    'wcgateway.gateway' => function (ContainerInterface $container) : WcGateway {
        $sessionHandler = $container->get('session.handler');
        $cartRepository = $container->get('api.cart-repository');
        $endpoint = $container->get('api.endpoint.order');
        $orderFactory = $container->get('api.factory.order');
        $settingsFields = $container->get('wcgateway.settings.fields');
        return new WcGateway($sessionHandler, $cartRepository, $endpoint, $orderFactory, $settingsFields);
    },
    'wcgateway.disabler' => function (ContainerInterface $container) : DisableGateways {
        $sessionHandler = $container->get('session.handler');
        return new DisableGateways($sessionHandler);
    },
    'wcgateway.settings' => function (ContainerInterface $container) : Settings {
        $gateway = $container->get('wcgateway.gateway');
        $settingsField = $container->get('wcgateway.settings.fields');
        return new Settings($gateway, $settingsField);
    },
    'wcgateway.notice.connect' => function (ContainerInterface $container) : ConnectAdminNotice {
        $settings = $container->get('wcgateway.settings');
        return new ConnectAdminNotice($settings);
    },
    'wcgateway.settings.fields' => function (ContainerInterface $container) : SettingsFields {
        return new SettingsFields();
    },
];
