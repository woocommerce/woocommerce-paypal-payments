<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\WcGateway\Admin\OrderDetail;
use Inpsyde\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use Inpsyde\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGatewayBase;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsFields;

return [
    'wcgateway.gateway.base' => static function (ContainerInterface $container): WcGatewayBase {
        return new WcGatewayBase();
    },
    'wcgateway.gateway' => static function (ContainerInterface $container): WcGateway {
        $orderProcessor = $container->get('wcgateway.order-processor');
        $settingsFields = $container->get('wcgateway.settings.fields');
        $authorizedPayments = $container->get('wcgateway.processor.authorized-payments');
        $notice = $container->get('wcgateway.notice.authorize-order-action');
        return new WcGateway(
            $settingsFields,
            $orderProcessor,
            $authorizedPayments,
            $notice
        );
    },
    'wcgateway.disabler' => static function (ContainerInterface $container): DisableGateways {
        $sessionHandler = $container->get('session.handler');
        return new DisableGateways($sessionHandler);
    },
    'wcgateway.settings' => static function (ContainerInterface $container): Settings {
        $gateway = $container->get('wcgateway.gateway.base');
        $settingsField = $container->get('wcgateway.settings.fields');
        return new Settings($gateway, $settingsField);
    },
    'wcgateway.notice.connect' => static function (ContainerInterface $container): ConnectAdminNotice {
        $settings = $container->get('wcgateway.settings');
        return new ConnectAdminNotice($settings);
    },
    'wcgateway.notice.authorize-order-action' =>
        static function (ContainerInterface $container): AuthorizeOrderActionNotice {
            return new AuthorizeOrderActionNotice();
        },
    'wcgateway.settings.fields' => static function (ContainerInterface $container): SettingsFields {
        return new SettingsFields();
    },
    'wcgateway.order-processor' => static function (ContainerInterface $container): OrderProcessor {

        $sessionHandler = $container->get('session.handler');
        $cartRepository = $container->get('api.repository.cart');
        $orderEndpoint = $container->get('api.endpoint.order');
        $paymentsEndpoint = $container->get('api.endpoint.payments');
        $orderFactory = $container->get('api.factory.order');
        return new OrderProcessor(
            $sessionHandler,
            $cartRepository,
            $orderEndpoint,
            $paymentsEndpoint,
            $orderFactory
        );
    },
    'wcgateway.processor.authorized-payments' => static function (ContainerInterface $container): AuthorizedPaymentsProcessor {
        $orderEndpoint = $container->get('api.endpoint.order');
        $paymentsEndpoint = $container->get('api.endpoint.payments');
        return new AuthorizedPaymentsProcessor($orderEndpoint, $paymentsEndpoint);
    },
    'wcgateway.admin.order-payment-status' => static function (ContainerInterface $container): PaymentStatusOrderDetail {
        return new PaymentStatusOrderDetail();
    },
    'wcgateway.admin.orders-payment-status-column' => static function (ContainerInterface $container): OrderTablePaymentStatusColumn {
        $settings = $container->get('wcgateway.settings');
        return new OrderTablePaymentStatusColumn($settings);
    },
];
