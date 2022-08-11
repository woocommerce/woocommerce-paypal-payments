<?php
/**
 * The order tracking module.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking;

use Dhii\Container\ServiceProvider;
use Dhii\Modular\Module\ModuleInterface;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\OrderTracking\Assets\OrderEditPageAssets;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class OrderTrackingModule
 */
class OrderTrackingModule implements ModuleInterface {

    /**
     * {@inheritDoc}
     */
    public function setup(): ServiceProviderInterface {
        return new ServiceProvider(
            require __DIR__ . '/../services.php',
            require __DIR__ . '/../extensions.php'
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param ContainerInterface $c A services container instance.
     */
    public function run( ContainerInterface $c ): void {
        $settings           = $c->get( 'wcgateway.settings' );
        $trackingEnabled = $settings->has( 'tracking_enabled' ) && $settings->get( 'tracking_enabled' );

        if ( !$trackingEnabled ) {
            return;
        }

        $asset_loader = $c->get('order-tracking.assets');
        assert( $asset_loader instanceof OrderEditPageAssets );
        $isPayPalOrderEditPage           = $c->get( 'order-tracking.is-paypal-order-edit-page' );

        add_action(
            'init',
            function () use ($asset_loader, $isPayPalOrderEditPage) {
                if(!$isPayPalOrderEditPage) {
                    return;
                }

                $asset_loader->register();
            }
        );

        add_action(
            'admin_enqueue_scripts',
            function () use ($asset_loader, $isPayPalOrderEditPage) {
                if(!$isPayPalOrderEditPage) {
                    return;
                }

                $asset_loader->enqueue();
            }
        );

        add_action(
            'wc_ajax_' . OrderTrackingEndpoint::ENDPOINT,
            static function () use ( $c ) {
                $endpoint = $c->get( 'order-tracking.endpoint.controller' );
                /**
                 * The tracking Endpoint.
                 *
                 * @var OrderTrackingEndpoint $endpoint
                 */
                $endpoint->handle_request();
            }
        );

        $meta_box_renderer = $c->get('order-tracking.meta-box.renderer');
        add_action( 'add_meta_boxes', function() use ($meta_box_renderer, $isPayPalOrderEditPage) {
            if(!$isPayPalOrderEditPage) {
                return;
            }

            add_meta_box( 'ppcp_order-tracking', __('Tracking Information','woocommerce-paypal-payments'), [$meta_box_renderer, 'render'], 'shop_order', 'side');
        },10, 2 );
    }

    /**
     * {@inheritDoc}
     */
    public function getKey() {  }
}
