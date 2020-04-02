<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;


use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\Exception\ModuleExceptionInterface;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class WcGatewayModule implements ModuleInterface
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
        add_filter(
            'woocommerce_payment_gateways',
            function($methods) use ($c) : array {

                $methods[] = $c->get('wcgateway.gateway');
                return (array) $methods;
            }
        );

        add_filter(
            'woocommerce_available_payment_gateways',
            function($methods) use ($c) : array {
                $disabler = $c->get('wcgateway.disabler');
                /**
                 * @var DisableGateways $disabler
                 */
                return $disabler->handler((array) $methods);
            }
        );
    }
}