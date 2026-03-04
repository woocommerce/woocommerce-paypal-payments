<?php

/**
 * The order tracking module.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderTracking;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Exception;
use WC_Order;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\OrderTracking\Assets\OrderEditPageAssets;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WP_Post;
use function WooCommerce\PayPalCommerce\Api\ppcp_get_paypal_order;
/**
 * Class OrderTrackingModule
 */
class OrderTrackingModule implements ServiceModule, ExtendingModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    use \WooCommerce\PayPalCommerce\OrderTracking\TrackingAvailabilityTrait;
    use TransactionIdHandlingTrait;
    public const PPCP_TRACKING_INFO_META_NAME = '_ppcp_paypal_tracking_info_meta_name';
    /**
     * {@inheritDoc}
     */
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
    /**
     * {@inheritDoc}
     */
    public function extensions(): array
    {
        return require __DIR__ . '/../extensions.php';
    }
    /**
     * {@inheritDoc}
     *
     * @param ContainerInterface $c A services container instance.
     * @throws NotFoundException
     */
    public function run(ContainerInterface $c): bool
    {
        add_action('wc_ajax_' . OrderTrackingEndpoint::ENDPOINT, function () use ($c) {
            $c->get('order-tracking.endpoint.controller')->handle_request();
        });
        $asset_loader = $c->get('order-tracking.assets');
        assert($asset_loader instanceof OrderEditPageAssets);
        add_action('init', function () use ($asset_loader, $c) {
            if (!$this->is_tracking_enabled($c->get('api.bearer'))) {
                return;
            }
            $asset_loader->register();
        });
        add_action('init', function () use ($asset_loader, $c) {
            if (!$this->is_tracking_enabled($c->get('api.bearer'))) {
                return;
            }
            $asset_loader->enqueue();
        });
        add_action(
            'add_meta_boxes',
            /**
             * Adds the tracking metabox.
             *
             * @param string $post_type The post type.
             * @param WP_Post|WC_Order $post_or_order_object The post/order object.
             * @return void
             *
             * @psalm-suppress MissingClosureParamType
             */
            function (string $post_type, $post_or_order_object) use ($c) {
                if (!$this->is_tracking_enabled($c->get('api.bearer'))) {
                    return;
                }
                $wc_order = $post_or_order_object instanceof WP_Post ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
                if (!$wc_order instanceof WC_Order) {
                    return;
                }
                try {
                    $paypal_order = ppcp_get_paypal_order($wc_order);
                } catch (Exception $exception) {
                    return;
                }
                $capture_id = $this->get_paypal_order_transaction_id($paypal_order) ?? '';
                if (!$capture_id) {
                    return;
                }
                /**
                 * Class and function exist in WooCommerce.
                 *
                 * @psalm-suppress UndefinedClass
                 * @psalm-suppress UndefinedFunction
                 */
                $screen = class_exists(CustomOrdersTableController::class) && wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';
                $meta_box_renderer = $c->get('order-tracking.meta-box.renderer');
                assert($meta_box_renderer instanceof \WooCommerce\PayPalCommerce\OrderTracking\MetaBoxRenderer);
                add_meta_box('ppcp_order-tracking', __('PayPal Package Tracking', 'woocommerce-paypal-payments'), static function () use ($meta_box_renderer, $wc_order, $capture_id): void {
                    $meta_box_renderer->render($wc_order, $capture_id);
                }, $screen, 'side', 'high');
            },
            10,
            2
        );
        return \true;
    }
}
