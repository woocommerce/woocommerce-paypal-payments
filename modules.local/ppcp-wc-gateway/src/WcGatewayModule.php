<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
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

        add_filter(
            'woocommerce_order_actions',
            function ($orderActions) : array {
                $orderActions['ppcp_authorize_order'] = __(
                    'Authorize PayPal Order',
                    'woocommerce-paypal-gateway'
                );
                return $orderActions;
            }
        );

        add_action(
            'woocommerce_order_action_ppcp_authorize_order',
            function (\WC_Order $wcOrder) use ($container) {
                $payPalOrderId = get_post_meta($wcOrder->get_id(), '_paypal_order_id', true);

                /**
                 * @var OrderEndpoint $orderEndpoint
                 */
                $orderEndpoint = $container->get('api.endpoint.order');

                $orderEndpoint->authorize($payPalOrderId);

                $wcOrder->update_status('processing');
            }
        );
    }
}
