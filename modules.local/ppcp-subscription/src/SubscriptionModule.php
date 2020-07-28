<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Subscription;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class SubscriptionModule implements ModuleInterface
{

    public function setup(): ServiceProviderInterface
    {
        return new ServiceProvider(
            require __DIR__ . '/../services.php',
            require __DIR__ . '/../extensions.php'
        );
    }

    /**
     * @inheritDoc
     */
    public function run(ContainerInterface $container)
    {
        add_action(
            'woocommerce_scheduled_subscription_payment_' . WcGateway::ID,
            static function ($amount, $order) use ($container) {
                if (! is_a($order, \WC_Order::class)) {
                    return;
                }
                $handler = $container->get('subscription.renewal-handler');
                $handler->renew($order);
            },
            10,
            2
        );
    }
}
