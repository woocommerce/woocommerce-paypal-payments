<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\WcGateway\Admin\AuthorizedPaymentStatus;
use Inpsyde\PayPalCommerce\WcGateway\Admin\AuthorizedPaymentStatusColumn;
use Inpsyde\PayPalCommerce\WcGateway\Admin\OrderDetail;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGatewayBase;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\Processor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsFields;

return [
    'wcgateway.gateway.base' => function (ContainerInterface $container) : WcGatewayBase {
        return new WcGatewayBase();
    },
    'wcgateway.gateway' => function (ContainerInterface $container) : WcGateway {
        $sessionHandler = $container->get('session.handler');
        $cartRepository = $container->get('api.repository.cart');
        // TODO eventuall get rid of the endpoints as the processor is sufficient
        $orderEndpoint = $container->get('api.endpoint.order');
        $paymentsEndpoint = $container->get('api.endpoint.payments');
        $orderFactory = $container->get('api.factory.order');
        $settingsFields = $container->get('wcgateway.settings.fields');
        $processor = $container->get('wcgateway.processor');
        return new WcGateway(
            $sessionHandler,
            $cartRepository,
            $orderEndpoint,
            $paymentsEndpoint,
            $orderFactory,
            $settingsFields,
            $processor
        );
    },
    'wcgateway.disabler' => function (ContainerInterface $container) : DisableGateways {
        $sessionHandler = $container->get('session.handler');
        return new DisableGateways($sessionHandler);
    },
    'wcgateway.settings' => function (ContainerInterface $container) : Settings {
        $gateway = $container->get('wcgateway.gateway.base');
        $settingsField = $container->get('wcgateway.settings.fields');
        return new Settings($gateway, $settingsField);
    },
    'wcgateway.notice.connect' => function (ContainerInterface $container) : ConnectAdminNotice {
        $settings = $container->get('wcgateway.settings');
        return new ConnectAdminNotice($settings);
    },
    'wcgateway.notice.authorize-order-action' =>
        function (ContainerInterface $container): AuthorizeOrderActionNotice {
            return new AuthorizeOrderActionNotice();
        },
    'wcgateway.settings.fields' => function (ContainerInterface $container): SettingsFields {
        return new SettingsFields();
    },
    'wcgateway.processor' => function (ContainerInterface $container): Processor {
        $authorizedPaymentsProcessor = $container->get('wcgateway.processor.authorized-payments');
        return new Processor($authorizedPaymentsProcessor);
    },
    'wcgateway.processor.authorized-payments' => function (ContainerInterface $container): AuthorizedPaymentsProcessor {
        $orderEndpoint = $container->get('api.endpoint.order');
        $paymentsEndpoint = $container->get('api.endpoint.payments');
        return new AuthorizedPaymentsProcessor($orderEndpoint, $paymentsEndpoint);
    },
    'wcgateway.admin.authorized-payment-status' => function(ContainerInterface $container): AuthorizedPaymentStatus {
        return new AuthorizedPaymentStatus();
    },
    'wcgateway.admin.authorized-payment-status-column' => function(ContainerInterface $container): AuthorizedPaymentStatusColumn {
        $settings = $container->get('wcgateway.settings');
        return new AuthorizedPaymentStatusColumn($settings);
    }
];
