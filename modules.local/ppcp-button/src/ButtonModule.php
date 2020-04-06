<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Inpsyde\PayPalCommerce\Button\Assets\SmartButton;
use Inpsyde\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\RequestData;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

class ButtonModule implements ModuleInterface
{

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
        $smartButton = $container->get('button.smart-button');
        /**
         * @var SmartButton $smartButton
         */
        add_action(
            'wp',
            function () use ($smartButton) {
                if (is_admin()) {
                    return;
                }
                $smartButton->renderWrapper();
            }
        );
        add_action('wp_enqueue_scripts', function () use ($smartButton) {
            $smartButton->enqueue();
        });

        add_action(
            'wc_ajax_' . ChangeCartEndpoint::ENDPOINT,
            function () use ($container) {
                $endpoint = $container->get('button.endpoint.change-cart');
                /**
                 * @var ChangeCartEndpoint $endpoint
                 */
                $endpoint->handleRequest();
            }
        );

        add_action(
            'wc_ajax_' . CreateOrderEndpoint::ENDPOINT,
            function () use ($container) {
                $endpoint = $container->get('button.endpoint.create-order');
                /**
                 * @var ChangeCartEndpoint $endpoint
                 */
                $endpoint->handleRequest();
            }
        );
    }
}
