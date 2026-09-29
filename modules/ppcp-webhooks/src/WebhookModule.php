<?php

/**
 * The webhook module.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\FactoryModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\ResubscribeEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulateEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Endpoint\SimulationStateEndpoint;
/**
 * Class WebhookModule
 */
class WebhookModule implements ServiceModule, FactoryModule, ExecutableModule
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
            assert($registrar instanceof \WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar);
            $registrar->register();
        });
        add_action('woocommerce_paypal_payments_gateway_deactivate', static function () use ($container) {
            $registrar = $container->get('webhook.registrar');
            assert($registrar instanceof \WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar);
            $registrar->unregister();
        });
        /**
         * Auto-recover missing webhook registration on admin pages.
         *
         * Registers webhooks when a connected merchant has no active subscription,
         * e.g. after a fresh install or if the webhook was lost. Throttled to once
         * every 5 minutes.
         */
        add_action('admin_init', static function () use ($container) {
            $is_connected = $container->get('settings.flag.is-connected');
            $is_registered = $container->get('webhook.is-registered');
            if (!$is_connected || $is_registered) {
                return;
            }
            $throttle_key = 'ppcp_webhook_auto_register_throttle';
            if (get_transient($throttle_key)) {
                return;
            }
            set_transient($throttle_key, \true, 5 * MINUTE_IN_SECONDS);
            $registrar = $container->get('webhook.registrar');
            assert($registrar instanceof \WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar);
            $registrar->register();
        });
        /**
         * Force webhook re-registration for PUI/OXXO merchants on plugin upgrade.
         *
         * Clears the stored webhook so the auto-recovery hook above re-registers it
         * with the full event list required by PUI/OXXO. Only runs on upgrades (not
         * fresh installs) when PUI or OXXO is enabled.
         */
        add_action('woocommerce_paypal_payments_gateway_migrate', static function ($installed_plugin_version) {
            if (!$installed_plugin_version) {
                return;
            }
            $pui_settings = get_option('woocommerce_ppcp-pay-upon-invoice-gateway_settings', array());
            $pui_enabled = is_array($pui_settings) && ($pui_settings['enabled'] ?? 'no') === 'yes';
            $oxxo_settings = get_option('woocommerce_ppcp-oxxo-gateway_settings', array());
            $oxxo_enabled = is_array($oxxo_settings) && ($oxxo_settings['enabled'] ?? 'no') === 'yes';
            if (!$pui_enabled && !$oxxo_enabled) {
                return;
            }
            delete_option(\WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar::KEY);
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
        return \true;
    }
}
