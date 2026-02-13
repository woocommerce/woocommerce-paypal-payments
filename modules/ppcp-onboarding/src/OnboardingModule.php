<?php

/**
 * The onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Onboarding;

use WooCommerce\PayPalCommerce\Onboarding\Endpoint\UpdateSignupLinksEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Assets\OnboardingAssets;
use WooCommerce\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingRenderer;
use WooCommerce\PayPalCommerce\Settings\SettingsModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * Class OnboardingModule
 */
class OnboardingModule implements ServiceModule, ExtendingModule, ExecutableModule
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
    public function extensions(): array
    {
        return require __DIR__ . '/../extensions.php';
    }
    /**
     * {@inheritDoc}
     */
    public function run(ContainerInterface $c): bool
    {
        add_action('admin_enqueue_scripts', function () use ($c) {
            if (apply_filters(
                // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
                'woocommerce.feature-flags.woocommerce_paypal_payments.settings_enabled',
                '1' === get_option('woocommerce-ppcp-is-new-merchant') || getenv('PCP_SETTINGS_ENABLED') === '1'
            )) {
                return;
            }
            $asset_loader = $c->get('onboarding.assets');
            assert($asset_loader instanceof OnboardingAssets);
            $asset_loader->register();
            add_action('woocommerce_settings_checkout', array($asset_loader, 'enqueue'));
        });
        add_filter('woocommerce_form_field', static function ($field, $key, $config) use ($c) {
            if ('ppcp_onboarding' !== $config['type']) {
                return $field;
            }
            $renderer = $c->get('onboarding.render');
            assert($renderer instanceof OnboardingRenderer);
            $is_production = 'production' === $config['env'];
            $products = $config['products'];
            ob_start();
            $renderer->render($is_production, $products);
            $content = ob_get_contents();
            ob_end_clean();
            return $content;
        }, 10, 3);
        add_action('wc_ajax_' . LoginSellerEndpoint::ENDPOINT, static function () use ($c) {
            $endpoint = $c->get('onboarding.endpoint.login-seller');
            /**
             * The LoginSellerEndpoint.
             *
             * @var LoginSellerEndpoint $endpoint
             */
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . UpdateSignupLinksEndpoint::ENDPOINT, static function () use ($c) {
            $endpoint = $c->get('onboarding.endpoint.pui');
            $endpoint->handle_request();
        });
        // Initialize REST routes at the appropriate time.
        $rest_controller = $c->get('onboarding.rest');
        add_action('rest_api_init', array($rest_controller, 'register_routes'));
        return \true;
    }
}
