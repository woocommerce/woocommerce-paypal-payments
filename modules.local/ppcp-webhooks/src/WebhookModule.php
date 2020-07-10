<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Webhook;
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
            'rest_api_init',
            static function () use ($container) {
                $endpoint = $container->get('webhook.endpoint.controller');
                /**
                 * @var IncomingWebhookEndpoint $endpoint
                 */
                $endpoint->register();
            }
        );

        add_action(
            WebhookRegistrar::EVENT_HOOK,
            static function () use ($container) {
                $registrar = $container->get('webhook.registrar');
                $registrar->register();
            }
        );

        add_action(
            'woocommerce-paypal-commerce-gateway.deactivate',
            static function () use ($container) {
                $registrar = $container->get('webhook.registrar');
                $registrar->unregister();
            }
        );
    }
}
