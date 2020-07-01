<?php declare(strict_types=1);

namespace Inpsyde\Woocommerce\Logging;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class WoocommerceLoggingModule implements ModuleInterface
{

    /**
     * @inheritDoc
     */
    public function setup(): ServiceProviderInterface
    {
        return new ServiceProvider(
            require __DIR__.'/../services.php',
            require __DIR__.'/../extensions.php'
        );
    }

    /**
     * @inheritDoc
     */
    public function run(ContainerInterface $container)
    {
    }
}
