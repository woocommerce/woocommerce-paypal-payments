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
    'api.paypal-host' => function(ContainerInterface $container) : string {
        $environment = $container->get('onboarding.environment');
        if ($environment->currentEnvironmentIs(Environment::SANDBOX)) {
            return 'https://api.sandbox.paypal.com';
        }
        return 'https://api.paypal.com';
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

        $host = $container->get('api.host');
        $logger = $container->get('woocommerce.logger.woocommerce');
        return new PayPalBearer(
            $pool,
            $host,
            $key,
            $secret,
            $logger
        );
    },
    'onboarding.state' => function(ContainerInterface $container) : State {
        $environment = $container->get('onboarding.environment');
        $settings = $container->get('wcgateway.settings');
        return new State($environment, $settings);
    },
    'onboarding.environment' => function(ContainerInterface $container) : Environment {
        $settings = $container->get('wcgateway.settings');
        return new Environment($settings);
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
        $settings = $container->get('wcgateway.settings');

        global $wpdb;
        $cacheFactory = new CachePoolFactory($wpdb);
        $pool = $cacheFactory->createCachePool('ppcp-token');
        return new LoginSellerEndpoint(
            $requestData,
            $loginSellerEndpoint,
            $partnerReferralsData,
            $settings,
            $pool
        );
    },
    'onboarding.render' => static function (ContainerInterface $container) : OnboardingRenderer {

        $partnerReferrals = $container->get('api.endpoint.partner-referrals');
        return new OnboardingRenderer(
            $partnerReferrals,
        );
    },
];
