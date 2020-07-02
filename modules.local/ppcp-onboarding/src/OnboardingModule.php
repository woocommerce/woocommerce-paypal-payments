<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\Onboarding\Assets\OnboardingAssets;
use Inpsyde\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use Inpsyde\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
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

        add_filter(
            'woocommerce_form_field',
            static function ($field, $key, $config) use ($container) {
                if ($config['type'] !== 'ppcp_onboarding') {
                    return $field;
                }
                $renderer = $container->get('onboarding.render');
                /**
                 * @var OnboardingRenderer $renderer
                 */
                ob_start();
                $renderer->render();
                $content = ob_get_contents();
                ob_end_clean();
                return $content;
            },
            10,
            3
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
