<?php

/**
 * The Pay Later configurator module.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterConfigurator;

use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\GetConfig;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\SaveConfig;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\Data\PayLaterMessagingSettings;
/**
 * Class PayLaterConfiguratorModule
 */
class PayLaterConfiguratorModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * Returns whether the module should be loaded.
     */
    public static function is_enabled(): bool
    {
        return apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
            'woocommerce.feature-flags.woocommerce_paypal_payments.paylater_configurator_enabled',
            getenv('PCP_PAYLATER_CONFIGURATOR') !== '0'
        );
    }
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
    public function run(ContainerInterface $c): bool
    {
        add_action('init', static function () use ($c) {
            $is_available = $c->get('paylater-configurator.is-available');
            if (!$is_available) {
                return;
            }
            $settings_provider = $c->get('settings.settings-provider');
            assert($settings_provider instanceof SettingsProvider);
            $paylater_settings = $c->get('settings.data.paylater-messaging-settings');
            assert($paylater_settings instanceof PayLaterMessagingSettings);
            add_action('wc_ajax_' . SaveConfig::ENDPOINT, static function () use ($c) {
                $endpoint = $c->get('paylater-configurator.endpoint.save-config');
                assert($endpoint instanceof SaveConfig);
                $endpoint->handle_request();
            });
            add_action('wc_ajax_' . GetConfig::ENDPOINT, static function () use ($c) {
                $endpoint = $c->get('paylater-configurator.endpoint.get-config');
                assert($endpoint instanceof GetConfig);
                $endpoint->handle_request();
            });
        });
        return \true;
    }
}
