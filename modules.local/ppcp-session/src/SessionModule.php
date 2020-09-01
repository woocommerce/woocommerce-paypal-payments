<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Session;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\Session\Cancellation\CancelController;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class SessionModule implements ModuleInterface
{

    public function setup(): ServiceProviderInterface
    {
        return new ServiceProvider(
            require __DIR__.'/../services.php',
            require __DIR__.'/../extensions.php'
        );
    }

    public function run(ContainerInterface $container)
    {
        add_action(
            'woocommerce_init',
            function () use ($container) {
                $controller = $container->get('session.cancellation.controller');
                /**
                 * @var CancelController $controller
                 */
                $controller->run();
            }
        );
    }
}
