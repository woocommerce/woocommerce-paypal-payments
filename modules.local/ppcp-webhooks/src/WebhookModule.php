<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\Onboarding\Assets\OnboardingAssets;
use Inpsyde\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use Inpsyde\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class WebhookModule implements ModuleInterface
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
        add_action(
            'rest_init',
            function() use ($container) {
                $endpoint = $container->get('webhook.endpoint.controller');
                /**
                 * @var IncomingWebhookEndpoint $endpoint
                 */
                $endpoint->register();
            }
        );

    }
}
