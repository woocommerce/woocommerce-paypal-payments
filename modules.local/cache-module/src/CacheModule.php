<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class CacheModule implements ModuleInterface
{

    public function setup(): ServiceProviderInterface
    {
        return new ServiceProvider(
            require __DIR__.'/../services.php',
            []
        );
    }

    public function run(ContainerInterface $container)
    {
    }
}
