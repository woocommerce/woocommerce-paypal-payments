<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Inpsyde\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use Inpsyde\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\ResetGateway;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGatewayBase;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\FullyOnboardedSettings;
use Inpsyde\PayPalCommerce\WcGateway\Settings\ProgressiveSettings;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsFields;
use Inpsyde\PayPalCommerce\WcGateway\Settings\StartSettings;

return [
    'wcgateway.gateway.base' => static function (ContainerInterface $container): WcGatewayBase {
        return new WcGatewayBase();
    },
    'wcgateway.gateway.reset' => static function (ContainerInterface $container): ResetGateway {
        return new ResetGateway(
            $container->get('wcgateway.settings')
        );
    },
    'wcgateway.gateway' => static function (ContainerInterface $container): WcGateway {
        $orderProcessor = $container->get('wcgateway.order-processor');
        $settingsFields = $container->get('wcgateway.settings.fields');
        $authorizedPayments = $container->get('wcgateway.processor.authorized-payments');
        $notice = $container->get('wcgateway.notice.authorize-order-action');
        $onboardingRender = $container->get('onboarding.render');
        $reset = $container->get('wcgateway.gateway.reset');

        return new WcGateway(
            $settingsFields,
            $orderProcessor,
            $authorizedPayments,
            $notice,
            $onboardingRender,
            $reset
        );
    },
    'wcgateway.disabler' => static function (ContainerInterface $container): DisableGateways {
        $sessionHandler = $container->get('session.handler');
        return new DisableGateways($sessionHandler);
    },
    'wcgateway.settings' => static function (ContainerInterface $container): Settings {
        $gateway = $container->get('wcgateway.gateway.base');
        return new Settings($gateway);
    },
    'wcgateway.notice.connect' => static function (ContainerInterface $container): ConnectAdminNotice {
        $state = $container->get('onboarding.state');
        $settings = $container->get('wcgateway.settings');
        return new ConnectAdminNotice($state, $settings);
    },
    'wcgateway.notice.authorize-order-action' =>
        static function (ContainerInterface $container): AuthorizeOrderActionNotice {
            return new AuthorizeOrderActionNotice();
        },
    'wcgateway.settings.fields' => static function (ContainerInterface $container): SettingsFields {
        $state = $container->get('onboarding.state');
        /**
         * @var State $state
         */
        if ($state->currentState() === State::STATE_START) {
            return new StartSettings();
        }
        $environment = $container->get('onboarding.environment');
        if ($state->currentState() === State::STATE_PROGRESSIVE) {
            return new ProgressiveSettings($environment);
        }
        return new FullyOnboardedSettings($environment);
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
