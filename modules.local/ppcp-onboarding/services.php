<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\ConnectBearer;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
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
use WpOop\TransientCache\CachePoolFactory;

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

        //ToDo: Real connect.woocommerce.com
        if ($environment->currentEnvironmentIs(Environment::SANDBOX)) {
            return 'http://connect-woo.wpcust.com';
        }
        return 'http://connect-woo.wpcust.com';

    },

    'api.bearer' => static function (ContainerInterface $container): Bearer {

        $state = $container->get('onboarding.state');
        if ($state->currentState() < State::STATE_ONBOARDED) {
            return new ConnectBearer();
        }
        global $wpdb;
        $cacheFactory = new CachePoolFactory($wpdb);
        $pool = $cacheFactory->createCachePool('ppcp-token');
        $key = $container->get('api.key');
        $secret = $container->get('api.secret');
        //ToDo: Remove Test Key and Secret
        //$key = 'AQB97CzMsd58-It1vxbcDAGvMuXNCXRD9le_XUaMlHB_U7XsU9IiItBwGQOtZv9sEeD6xs2vlIrL4NiD';
        //$secret = 'EILGMYK_0iiSbja8hT-nCBGl0BvKxEB4riHgyEO7QWDeUzCJ5r42JUEvrI7gpGyw0Qww8AIXxSdCIAny';

        $host = $container->get('api.host');
        return new PayPalBearer(
            $pool,
            $host,
            $key,
            $secret
        );
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
            '/modules.local/ppcp-onboarding/',
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
