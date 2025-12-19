<?php

/**
 * The Axo Block module.
 *
 * @package WooCommerce\PayPalCommerce\AxoBlock
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AxoBlock;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\SdkClientToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * Class AxoBlockModule
 */
class AxoBlockModule implements ServiceModule, ExtendingModule, ExecutableModule
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
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType') || !function_exists('woocommerce_store_api_register_payment_requirements')) {
            add_action('admin_notices', function () {
                printf('<div class="notice notice-error"><p>%1$s</p></div>', wp_kses_post(__('Fastlane checkout block initialization failed, possibly old WooCommerce version or disabled WooCommerce Blocks plugin.', 'woocommerce-paypal-payments')));
            });
        }
        add_action('wp_loaded', function () use ($c) {
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            add_filter('woocommerce_paypal_payments_sdk_components_hook', function ($components) use ($c) {
                if (!$c->has('axo.available') || !$c->get('axo.available')) {
                    return $components;
                }
                $components[] = 'fastlane';
                return $components;
            });
        });
        add_action('woocommerce_blocks_payment_method_type_registration', function (PaymentMethodRegistry $payment_method_registry) use ($c): void {
            /*
             * Only register the method if we are not in the admin or the customer is not logged in.
             */
            if (!is_user_logged_in()) {
                $payment_method_registry->register($c->get('axoblock.method'));
            }
        });
        // Enqueue frontend scripts.
        add_action('wp_enqueue_scripts', static function () use ($c) {
            if (!has_block('woocommerce/checkout') && !has_block('woocommerce/cart')) {
                return;
            }
            $module_url = $c->get('axoblock.url');
            $asset_version = $c->get('ppcp.asset-version');
            wp_register_style('wc-ppcp-axo-block', untrailingslashit($module_url) . '/assets/css/gateway.css', array(), $asset_version);
            wp_enqueue_style('wc-ppcp-axo-block');
        });
        add_action('wp_enqueue_scripts', function () use ($c) {
            $this->enqueue_paypal_insights_script($c);
        });
        return \true;
    }
    /**
     * Enqueues PayPal Insights analytics script for the Checkout block.
     *
     * @param ContainerInterface $c The service container.
     * @return void
     */
    private function enqueue_paypal_insights_script(ContainerInterface $c): void
    {
        if (!has_block('woocommerce/checkout') || WC()->cart->is_empty()) {
            return;
        }
        $dcc_configuration = $c->get('wcgateway.configuration.card-configuration');
        if (!$dcc_configuration->use_fastlane()) {
            return;
        }
        $module_url = $c->get('axoblock.url');
        $asset_version = $c->get('ppcp.asset-version');
        wp_register_script('wc-ppcp-paypal-insights', untrailingslashit($module_url) . '/assets/js/PayPalInsightsLoader.js', array('wp-plugins', 'wp-data', 'wp-element', 'wc-blocks-registry'), $asset_version, \true);
        wp_localize_script('wc-ppcp-paypal-insights', 'ppcpPayPalInsightsData', array('isAxoEnabled' => \true));
        wp_enqueue_script('wc-ppcp-paypal-insights');
    }
}
