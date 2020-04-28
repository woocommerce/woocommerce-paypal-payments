<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\AdminNotices\Repository\Repository;
use Inpsyde\PayPalCommerce\WcGateway\Admin\OrderDetail;
use Inpsyde\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use Inpsyde\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class WcGatewayModule implements ModuleInterface
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
        add_filter(
            'woocommerce_payment_gateways',
            function ($methods) use ($container) : array {
                $methods[] = $container->get('wcgateway.gateway');
                return (array)$methods;
            }
        );

        add_filter(
            'woocommerce_available_payment_gateways',
            function ($methods) use ($container) : array {
                $disabler = $container->get('wcgateway.disabler');
                /**
                 * @var DisableGateways $disabler
                 */
                return $disabler->handler((array)$methods);
            }
        );

        add_filter(
            Repository::NOTICES_FILTER,
            function ($notices) use ($container) : array {
                $notice = $container->get('wcgateway.notice.connect');
                /**
                 * @var ConnectAdminNotice $notice
                 */
                $connectMessage = $notice->connectMessage();
                if ($connectMessage) {
                    $notices[] = $connectMessage;
                }
                $authorizeOrderAction = $container->get('wcgateway.notice.authorize-order-action');
                $authorizedMessage = $authorizeOrderAction->message();
                if ($authorizedMessage) {
                    $notices[] = $authorizedMessage;
                }

                return $notices;
            }
        );

        add_filter(
            'woocommerce_order_actions',
            function ($orderActions): array {
                $orderActions['ppcp_authorize_order'] = __(
                    'Capture authorized PayPal payment',
                    'woocommerce-paypal-gateway'
                );
                return $orderActions;
            }
        );

        add_action(
            'woocommerce_order_action_ppcp_authorize_order',
            function (\WC_Order $wcOrder) use ($container) {
                /**
                 * @var WcGateway $gateway
                 */
                $gateway = $container->get('wcgateway.gateway');
                $gateway->captureAuthorizedPayment($wcOrder);
            }
        );

        add_action(
            'woocommerce_order_actions_start',
            function ($wcOrderId) use ($container) {
                /**
                 * @var PaymentStatusOrderDetail $class
                 */
                $class = $container->get('wcgateway.admin.order-payment-status');
                $class->render(intval($wcOrderId));
            }
        );

        add_filter(
            'manage_edit-shop_order_columns',
            function ($columns) use ($container) {
                /**
                 * @var OrderTablePaymentStatusColumn $paymentStatusColumn
                 */
                $paymentStatusColumn = $container->get('wcgateway.admin.orders-payment-status-column');
                return $paymentStatusColumn->register($columns);
            }
        );

        add_action(
            'manage_shop_order_posts_custom_column',
            function ($column, $wcOrderId) use ($container) {
                /**
                 * @var OrderTablePaymentStatusColumn $paymentStatusColumn
                 */
                $paymentStatusColumn = $container->get('wcgateway.admin.orders-payment-status-column');
                $paymentStatusColumn->render($column, intval($wcOrderId));
            },
            10,
            2
        );
    }
}
