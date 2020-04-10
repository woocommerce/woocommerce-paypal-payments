<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Inpsyde\PayPalCommerce\WcGateway\Notice\ConnectNotice;
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

    public function run(ContainerInterface $container)
    {
        add_filter(
            'woocommerce_payment_gateways',
            function ($methods) use ($container) : array {

                $methods[] = $container->get('wcgateway.gateway');
                return (array) $methods;
            }
        );

        add_filter(
            'woocommerce_available_payment_gateways',
            function ($methods) use ($container) : array {
                $disabler = $container->get('wcgateway.disabler');
                /**
                 * @var DisableGateways $disabler
                 */
                return $disabler->handler((array) $methods);
            }
        );

        add_action(
            'admin_notices',
            function () use ($container) : void {
                $notice = $container->get('wcgateway.notice.connect');
                /**
                 * @var ConnectNotice $notice
                 */
                $notice->display();
            }
        );
    }
}
