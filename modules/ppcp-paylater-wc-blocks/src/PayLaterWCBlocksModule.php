<?php

/**
 * The Pay Later WooCommerce Blocks module.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Endpoint\CartScriptParamsEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory\ConfigFactory;
use WooCommerce\PayPalCommerce\Settings\Data\PayLaterMessagingSettings;
use WooCommerce\PayPalCommerce\SdkV6\Helper\MessageStyleMapper;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
/**
 * Class PayLaterWCBlocksModule
 */
class PayLaterWCBlocksModule implements ServiceModule, ExecutableModule
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
     * Returns whether the block module should be loaded.
     *
     * @return bool true if the module should be loaded, otherwise false.
     */
    public static function is_module_loading_required(): bool
    {
        return apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
            'woocommerce.feature-flags.woocommerce_paypal_payments.paylater_wc_blocks_enabled',
            getenv('PCP_PAYLATER_WC_BLOCKS') !== '0'
        );
    }
    /**
     * Returns whether the block is enabled.
     *
     * @param SettingsStatus $settings_status The Settings status helper.
     * @param string         $location The location to check.
     * @return bool true if the block is enabled, otherwise false.
     */
    public static function is_block_enabled(SettingsStatus $settings_status, string $location): bool
    {
        return self::is_module_loading_required() && $settings_status->is_pay_later_messaging_enabled_for_location($location);
    }
    /**
     * Returns whether the placement is enabled.
     *
     * @param SettingsStatus $settings_status The Settings status helper.
     * @param string         $location The location to check.
     * @return bool true if the placement is enabled, otherwise false.
     */
    public static function is_placement_enabled(SettingsStatus $settings_status, string $location): bool
    {
        return self::is_block_enabled($settings_status, $location);
    }
    /**
     * Whether the SDK v6 stack is rendering the current page.
     *
     * Per page, not per site: where v6 stands down, v5 can still draw a banner.
     * Mirrors GooglepayModule::v6_owns_current_page().
     *
     * @param ContainerInterface $c The container.
     */
    public static function v6_owns_current_page(ContainerInterface $c): bool
    {
        if (!$c->has('sdk-v6.owns-current-page')) {
            return \false;
        }
        $owns_current_page = $c->get('sdk-v6.owns-current-page');
        return $owns_current_page();
    }
    /**
     * The data the block editor needs to preview a message with the SDK v6.
     *
     * The v6 frontend config (wc_ppcp_sdk_v6) is never printed in admin, so the
     * editor gets its own: the public client id instead of a client token, and
     * the location's v6 style from the same mapper the frontend uses.
     *
     * @param ContainerInterface $c        The container.
     * @param string             $location The messaging location, 'cart' or 'checkout'.
     * @return array{sdkV6: ?array, messageStyle: ?array} Null values when the v6 module is not loaded.
     */
    private static function sdk_v6_preview_data(ContainerInterface $c, string $location): array
    {
        if (!$c->has('sdk-v6.message-style-mapper')) {
            return array('sdkV6' => null, 'messageStyle' => null);
        }
        $environment = $c->get('settings.environment');
        assert($environment instanceof Environment);
        $style_mapper = $c->get('sdk-v6.message-style-mapper');
        assert($style_mapper instanceof MessageStyleMapper);
        // Same script URL as SdkV6Manager::script_data().
        $base_url = $environment->is_sandbox() ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
        return array('sdkV6' => array('sdkUrl' => $base_url . '/web-sdk/v6/core', 'clientId' => (string) $c->get('button.client_id'), 'currency' => get_woocommerce_currency(), 'locale' => str_replace('_', '-', get_locale())), 'messageStyle' => $style_mapper->styles_for_location($location));
    }
    /**
     * Returns whether the under cart totals placement is enabled.
     *
     * @return bool true if the under cart totals placement is enabled, otherwise false.
     */
    public function is_under_cart_totals_placement_enabled(): bool
    {
        return apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
            'woocommerce.feature-flags.woocommerce_paypal_payments.paylater_wc_blocks_cart_under_totals_enabled',
            \true
        );
    }
    /**
     * {@inheritDoc}
     */
    public function run(ContainerInterface $c): bool
    {
        $messages_apply = $c->get('button.helper.messages-apply');
        assert($messages_apply instanceof MessagesApply);
        if (!$messages_apply->for_country()) {
            return \true;
        }
        add_action('init', function () use ($c): void {
            $paylater_settings = $c->get('settings.data.paylater-messaging-settings');
            assert($paylater_settings instanceof PayLaterMessagingSettings);
            $config_factory = $c->get('paylater-configurator.factory.config');
            assert($config_factory instanceof ConfigFactory);
            $script_handle = 'ppcp-cart-paylater-block';
            $asset_getter = $c->get('paylater-wc-blocks.asset_getter');
            assert($asset_getter instanceof AssetGetter);
            wp_register_script($script_handle, $asset_getter->get_asset_url('CartPayLaterMessagesBlock/cart-paylater-block.js'), array(), $c->get('ppcp.asset-version'), \true);
            wp_localize_script($script_handle, 'PcpCartPayLaterBlock', array(
                'ajax' => array('cart_script_params' => array('endpoint' => \WC_AJAX::get_endpoint(CartScriptParamsEndpoint::ENDPOINT))),
                'config' => $config_factory->from_settings($paylater_settings),
                'settingsUrl' => admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway'),
                'placementEnabled' => self::is_placement_enabled($c->get('wcgateway.settings.status'), 'cart'),
                'payLaterSettingsUrl' => admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway'),
                'underTotalsPlacementEnabled' => self::is_under_cart_totals_placement_enabled(),
                // Module loaded, not page ownership: the editor has no page
                // to own.
                'isSdkV6Active' => $c->has('sdk-v6.owns-current-page'),
            ) + self::sdk_v6_preview_data($c, 'cart'));
            $script_handle = 'ppcp-checkout-paylater-block';
            wp_register_script($script_handle, $asset_getter->get_asset_url('CheckoutPayLaterMessagesBlock/checkout-paylater-block.js'), array(), $c->get('ppcp.asset-version'), \true);
            wp_localize_script($script_handle, 'PcpCheckoutPayLaterBlock', array(
                'ajax' => array('cart_script_params' => array('endpoint' => \WC_AJAX::get_endpoint(CartScriptParamsEndpoint::ENDPOINT))),
                'config' => $config_factory->from_settings($paylater_settings),
                'settingsUrl' => admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway'),
                'placementEnabled' => self::is_placement_enabled($c->get('wcgateway.settings.status'), 'checkout'),
                'payLaterSettingsUrl' => admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway'),
                // Module loaded, not page ownership: the editor has no page
                // to own.
                'isSdkV6Active' => $c->has('sdk-v6.owns-current-page'),
            ) + self::sdk_v6_preview_data($c, 'checkout'));
        }, 20);
        // Auto-insert the messaging blocks into block-theme (FSE) cart and checkout
        // templates via the Block Hooks API. No-op on classic themes; on block themes
        // it covers what the classic `woocommerce_*` hooks and the imperative editor
        // inserter cannot reach (template parts and the Site Editor canvas).
        $hooked_blocks_registrar = $c->get('paylater-wc-blocks.hooked-blocks-registrar');
        assert($hooked_blocks_registrar instanceof \WooCommerce\PayPalCommerce\PayLaterWCBlocks\HookedBlocksRegistrar);
        $hooked_blocks_registrar->register();
        /**
         * Registers slugs as block categories with WordPress.
         */
        add_action('block_categories_all', function (array $categories): array {
            return array_merge($categories, array(array('slug' => 'woocommerce-paypal-payments', 'title' => __('PayPal Blocks', 'woocommerce-paypal-payments'))));
        }, 10, 2);
        add_action('init', function () use ($c): void {
            if (!function_exists('register_block_type')) {
                return;
            }
            $path_to_module_js_folder = $c->get('ppcp.path-to-plugin-folder') . 'modules/ppcp-paylater-wc-blocks/resources/js/';
            register_block_type($path_to_module_js_folder . 'CartPayLaterMessagesBlock', array('render_callback' => function (array $attributes) use ($c) {
                return \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksUtils::render_paylater_block($attributes['blockId'] ?? 'woocommerce-paypal-payments/cart-paylater-messages', $attributes['ppcpId'] ?? 'ppcp-cart-paylater-messages', 'cart', $c);
            }));
            register_block_type($path_to_module_js_folder . 'CheckoutPayLaterMessagesBlock', array('render_callback' => function (array $attributes) use ($c) {
                return \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksUtils::render_paylater_block($attributes['blockId'] ?? 'woocommerce-paypal-payments/checkout-paylater-messages', $attributes['ppcpId'] ?? 'ppcp-checkout-paylater-messages', 'checkout', $c);
            }));
        });
        // Fallback for cart placements the Block Hooks API does not reach - a classic
        // theme, or a Cart block on an ordinary page rather than an FSE template. The
        // strpos guard below also prevents a double insertion: on an FSE template Block
        // Hooks has already added the block, so its markup is present here and we skip.
        add_filter('render_block_woocommerce/cart-totals-block', function (string $block_content) use ($c) {
            if (\false === strpos($block_content, 'woocommerce-paypal-payments/cart-paylater-messages')) {
                return \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksUtils::render_and_insert_paylater_block($block_content, 'woocommerce-paypal-payments/cart-paylater-messages', 'ppcp-cart-paylater-messages', 'cart', $c, self::is_under_cart_totals_placement_enabled());
            }
            return $block_content;
        }, 10, 1);
        // Fallback for checkout placements the Block Hooks API does not reach, and the
        // same strpos guard prevents a double insertion on FSE checkout templates.
        add_filter('render_block_woocommerce/checkout-totals-block', function (string $block_content) use ($c) {
            if (\false === strpos($block_content, 'woocommerce-paypal-payments/checkout-paylater-messages')) {
                return \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksUtils::render_and_insert_paylater_block($block_content, 'woocommerce-paypal-payments/checkout-paylater-messages', 'ppcp-checkout-paylater-messages', 'checkout', $c);
            }
            return $block_content;
        }, 10, 1);
        // Since there's no regular way we can place the Pay Later messaging block under the cart totals block, we need a custom script.
        if (self::is_under_cart_totals_placement_enabled()) {
            add_action('enqueue_block_editor_assets', function () use ($c): void {
                // In the Site Editor the Block Hooks API inserts the messaging
                // block into the cart/checkout templates, so the imperative
                // inserter would place a second copy. Let Block Hooks own that
                // context; keep the inserter for the post/page editor.
                if (Context::is_site_editor()) {
                    return;
                }
                $handle = 'ppcp-checkout-paylater-block-editor-inserter';
                $asset_getter = $c->get('paylater-wc-blocks.asset_getter');
                assert($asset_getter instanceof AssetGetter);
                $path = $asset_getter->get_asset_url('CartPayLaterMessagesBlock/cart-paylater-block-inserter.js');
                wp_register_script($handle, $path, array('wp-blocks', 'wp-data', 'wp-element'), $c->get('ppcp.asset-version'), \true);
                wp_enqueue_script($handle);
            });
        }
        return \true;
    }
}
