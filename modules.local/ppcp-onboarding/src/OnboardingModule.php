<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\Onboarding\Assets\OnboardingAssets;
use Inpsyde\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class OnboardingModule implements ModuleInterface
{
    public function setup(): ServiceProviderInterface
    {
        return new ServiceProvider(
            require __DIR__ . '/../services.php',
            require __DIR__ . '/../extensions.php'
        );
    }

    public function run(ContainerInterface $container)
    {

        $assetLoader = $container->get('onboarding.assets');
        /**
         * @var OnboardingAssets $assetLoader
         */
        add_action(
            'admin_enqueue_scripts',
            [
                $assetLoader,
                'register',
            ]
        );
        add_action(
            'woocommerce_settings_checkout',
            [
                $assetLoader,
                'enqueue',
            ]
        );


        add_action(
            'wc_ajax_' . LoginSellerEndpoint::ENDPOINT,
            static function () use ($container) {
                $endpoint = $container->get('onboarding.endpoint.login-seller');
                /**
                 * @var ChangeCartEndpoint $endpoint
                 */
                $endpoint->handleRequest();
            }
        );
    }
}
