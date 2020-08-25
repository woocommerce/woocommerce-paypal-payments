<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\Button\Assets\SmartButton;
use Inpsyde\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\DataClientIdEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\RequestData;
use Inpsyde\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class ButtonModule implements ModuleInterface
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
        /**
         * @var SmartButton $smartButton
         */
        add_action(
            'wp',
            static function () use ($container) {
                if (is_admin()) {
                    return;
                }
                $smartButton = $container->get('button.smart-button');
                $smartButton->renderWrapper();
            }
        );
        add_action('wp_enqueue_scripts', static function () use ($container) {

            $smartButton = $container->get('button.smart-button');
            $smartButton->enqueue();
        });

        add_filter(
            'woocommerce_create_order',
            static function ($value) use ($container) {
                $earlyOrderHelper = $container->get('button.helper.early-order-handler');
                if (! is_null($value)) {
                    $value = (int) $value;
                }
                /**
                 * @var EarlyOrderHandler $earlyOrderHelper
                 */
                return $earlyOrderHelper->determineWcOrderId($value);
            }
        );

        $this->registerAjaxEndpoints($container);
    }

    private function registerAjaxEndpoints(ContainerInterface $container)
    {
        add_action(
            'wc_ajax_' . DataClientIdEndpoint::ENDPOINT,
            static function () use ($container) {
                $endpoint = $container->get('button.endpoint.data-client-id');
                /**
                 * @var DataClientIdEndpoint $endpoint
                 */
                $endpoint->handleRequest();
            }
        );

        add_action(
            'wc_ajax_' . ChangeCartEndpoint::ENDPOINT,
            static function () use ($container) {
                $endpoint = $container->get('button.endpoint.change-cart');
                /**
                 * @var ChangeCartEndpoint $endpoint
                 */
                $endpoint->handleRequest();
            }
        );

        add_action(
            'wc_ajax_' . ApproveOrderEndpoint::ENDPOINT,
            static function () use ($container) {
                $endpoint = $container->get('button.endpoint.approve-order');
                /**
                 * @var ApproveOrderEndpoint $endpoint
                 */
                $endpoint->handleRequest();
            }
        );

        add_action(
            'wc_ajax_' . CreateOrderEndpoint::ENDPOINT,
            static function () use ($container) {
                $endpoint = $container->get('button.endpoint.create-order');
                /**
                 * @var CreateOrderEndpoint $endpoint
                 */
                $endpoint->handleRequest();
            }
        );
    }
}
