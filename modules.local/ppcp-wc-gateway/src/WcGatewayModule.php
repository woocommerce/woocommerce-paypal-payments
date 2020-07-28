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
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
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
        $this->registerPaymentGateway($container);
        $this->registerOrderFunctionality($container);
        $this->registerColumns($container);

        add_filter(
            Repository::NOTICES_FILTER,
            static function ($notices) use ($container): array {
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

        add_action(
            'wp_ajax_woocommerce_toggle_gateway_enabled',
            static function () use ($container) {
                if (
                    ! current_user_can('manage_woocommerce')
                    || ! check_ajax_referer(
                        'woocommerce-toggle-payment-gateway-enabled',
                        'security'
                    )
                    || ! isset($_POST['gateway_id'])
                ) {
                    return;
                }

                /**
                 * @var Settings $settings
                 */
                $settings = $container->get('wcgateway.settings');
                $enabled = $settings->has('enabled') ? $settings->get('enabled') : false;
                if (! $enabled) {
                    return;
                }
                $settings->set('enabled', false);
                $settings->persist();
            },
            9
        );
        add_action(
            'woocommerce-paypal-commerce-gateway.deactivate',
            static function () use ($container) {
                delete_option(Settings::KEY);
            }
        );
    }

    private function registerPaymentGateWay(ContainerInterface $container)
    {

        add_filter(
            'woocommerce_payment_gateways',
            static function ($methods) use ($container): array {
                $methods[] = $container->get('wcgateway.gateway');
                return (array)$methods;
            }
        );

        add_action(
            'woocommerce_settings_save_checkout',
            static function () use ($container) {
                $listener = $container->get('wcgateway.settings.listener');
                $listener->listen();
            }
        );

        add_filter(
            'woocommerce_form_field',
            static function ($field, $key, $args, $value) use ($container) {
                $renderer = $container->get('wcgateway.settings.render');
                /**
                 * @var SettingsRenderer $renderer
                 */
                return $renderer->renderPassword(
                    $renderer->renderTextInput(
                        $renderer->renderMultiSelect(
                            $field,
                            $key,
                            $args,
                            $value
                        ),
                        $key,
                        $args,
                        $value
                    ),
                    $key,
                    $args,
                    $value
                );
            },
            10,
            4
        );

        add_filter(
            'woocommerce_available_payment_gateways',
            static function ($methods) use ($container): array {
                $disabler = $container->get('wcgateway.disabler');
                /**
                 * @var DisableGateways $disabler
                 */
                return $disabler->handler((array)$methods);
            }
        );
    }

    private function registerOrderFunctionality(ContainerInterface $container)
    {
        add_filter(
            'woocommerce_order_actions',
            static function ($orderActions): array {
                $orderActions['ppcp_authorize_order'] = __(
                    'Capture authorized PayPal payment',
                    'woocommerce-paypal-commerce-gateway'
                );
                return $orderActions;
            }
        );

        add_action(
            'woocommerce_order_action_ppcp_authorize_order',
            static function (\WC_Order $wcOrder) use ($container) {
                /**
                 * @var WcGateway $gateway
                 */
                $gateway = $container->get('wcgateway.gateway');
                $gateway->captureAuthorizedPayment($wcOrder);
            }
        );
    }

    private function registerColumns(ContainerInterface $container)
    {
        add_action(
            'woocommerce_order_actions_start',
            static function ($wcOrderId) use ($container) {
                /**
                 * @var PaymentStatusOrderDetail $class
                 */
                $class = $container->get('wcgateway.admin.order-payment-status');
                $class->render(intval($wcOrderId));
            }
        );

        add_filter(
            'manage_edit-shop_order_columns',
            static function ($columns) use ($container) {
                /**
                 * @var OrderTablePaymentStatusColumn $paymentStatusColumn
                 */
                $paymentStatusColumn = $container->get('wcgateway.admin.orders-payment-status-column');
                return $paymentStatusColumn->register($columns);
            }
        );

        add_action(
            'manage_shop_order_posts_custom_column',
            static function ($column, $wcOrderId) use ($container) {
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
