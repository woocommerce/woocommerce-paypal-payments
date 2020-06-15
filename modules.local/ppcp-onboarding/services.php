<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\Onboarding\Assets\OnboardingAssets;
use Inpsyde\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use Inpsyde\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
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

    'api.host' => static function (ContainerInterface $container): string {
        $state = $container->get('onboarding.state');
        $environment = $container->get('onboarding.environment');

        //ToDo: Correct the URLs
        /**
         * @var Environment $environment
         * @var State $state
         */
        if ($state->currentState() >= State::STATE_ONBOARDED) {
            if ($environment->currentEnvironmentIs(Environment::SANDBOX)) {
                return 'https://api.sandbox.paypal.com';
            }
            return 'https://api.sandbox.paypal.com';
        }

        if ($environment->currentEnvironmentIs(Environment::SANDBOX)) {
            return 'https://api.sandbox.paypal.com';
        }
        return 'https://api.sandbox.paypal.com';

    },
    'onboarding.state' => function(ContainerInterface $container) : State {
        $environment = $container->get('onboarding.environment');
        $settings = $container->get('wcgateway.settings');
        return new State($environment, $settings);
    },
    'onboarding.environment' => function(ContainerInterface $container) : Environment {
        return new Environment();
    },

    'onboarding.assets' => function(ContainerInterface $container) : OnboardingAssets {
        $state = $container->get('onboarding.state');
        $loginSellerEndpoint = $container->get('onboarding.endpoint.login-seller');
        return new OnboardingAssets(
            $container->get('onboarding.url'),
            $state,
            $loginSellerEndpoint
        );
    },

    'onboarding.url' => static function (ContainerInterface $container): string {
        return plugins_url(
            '/modules/ppcp-onboarding/',
            dirname(__FILE__, 3) . '/woocommerce-paypal-commerce-gateway.php'
        );
    },

    'onboarding.endpoint.login-seller' => static function (ContainerInterface $container) : LoginSellerEndpoint {

        $requestData = $container->get('button.request-data');
        $loginSellerEndpoint = $container->get('api.endpoint.login-seller');
        $partnerReferralsData = $container->get('api.repository.partner-referrals-data');
        $gateway = $container->get('wcgateway.gateway.base');
        return new LoginSellerEndpoint(
            $requestData,
            $loginSellerEndpoint,
            $partnerReferralsData,
            $gateway
        );
    },
    'onboarding.render' => static function (ContainerInterface $container) : OnboardingRenderer {

        $partnerReferrals = $container->get('api.endpoint.partner-referrals');
        return new OnboardingRenderer(
            $partnerReferrals,
        );
    },
];
