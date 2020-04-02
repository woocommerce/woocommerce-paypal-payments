<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient;


use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\Exception\ModuleExceptionInterface;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class ApiModule implements ModuleInterface
{

    public function setup(): ServiceProviderInterface
    {
        return new ServiceProvider(
            require __DIR__.'/../services.php',
            require __DIR__.'/../extensions.php'
        );
    }

    public function run(ContainerInterface $c)
    {
        // TODO: Implement run() method.
    }
}