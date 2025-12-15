<?php

/**
 * The webhook module.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\FactoryModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\ResubscribeEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulationStateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Status\Assets\WebhooksStatusPageAssets;
/**
 * Class WebhookModule
 */
class WebhookModule implements ServiceModule, FactoryModule, ExtendingModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
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
    public function factories(): array
    {
        return require __DIR__ . '/../factories.php';
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
     */
    public function run(ContainerInterface $container): bool
    {
        add_action('rest_api_init', static function () use ($container) {
            $endpoint = $container->get('webhook.endpoint.controller');
            /**
             * The Incoming Webhook Endpoint.
             *
             * @var IncomingWebhookEndpoint $endpoint
             */
            $endpoint->register();
        });
        add_action(\WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar::EVENT_HOOK, static function () use ($container) {
            $registrar = $container->get('webhook.registrar');
            /**
             * The Webhook Registrar.
             *
             * @var WebhookRegistrar $endpoint
             */
            $registrar->register();
        });
        add_action('woocommerce_paypal_payments_gateway_deactivate', static function () use ($container) {
            $registrar = $container->get('webhook.registrar');
            /**
             * The Webhook Registrar.
             *
             * @var WebhookRegistrar $endpoint
             */
            $registrar->unregister();
        });
        add_action('wc_ajax_' . ResubscribeEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('webhook.endpoint.resubscribe');
            assert($endpoint instanceof ResubscribeEndpoint);
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . SimulateEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('webhook.endpoint.simulate');
            assert($endpoint instanceof SimulateEndpoint);
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . SimulationStateEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('webhook.endpoint.simulation-state');
            assert($endpoint instanceof SimulationStateEndpoint);
            $endpoint->handle_request();
        });
        add_action('init', function () use ($container) {
            $page_id = $container->get('wcgateway.current-ppcp-settings-page-id');
            if (Settings::CONNECTION_TAB_ID !== $page_id) {
                return;
            }
            $asset_loader = $container->get('webhook.status.assets');
            assert($asset_loader instanceof WebhooksStatusPageAssets);
            $asset_loader->register();
            add_action('admin_enqueue_scripts', array($asset_loader, 'enqueue'));
            try {
                $webhooks = $container->get('webhook.status.registered-webhooks');
                $is_connected = $container->get('settings.flag.is-connected');
                if (empty($webhooks) && $is_connected) {
                    $registrar = $container->get('webhook.registrar');
                    assert($registrar instanceof \WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar);
                    $registrar->register();
                }
            } catch (Exception $exception) {
                $container->get('woocommerce.logger.woocommerce')->error('Failed to load webhooks list: ' . $exception->getMessage());
            }
        });
        add_action('woocommerce_paypal_payments_gateway_migrate', static function () use ($container) {
            $registrar = $container->get('webhook.registrar');
            assert($registrar instanceof \WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar);
            add_action('init', function () use ($registrar) {
                $registrar->register();
            });
        });
        return \true;
    }
}
