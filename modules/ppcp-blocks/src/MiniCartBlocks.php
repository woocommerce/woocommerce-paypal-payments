<?php

/**
 * Mini-Cart PayPal buttons block.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
/**
 * Registers the Mini-Cart Smart Buttons block and auto-inserts it into the block-theme
 * `woocommerce/mini-cart` template part, where the classic mini-cart action does not fire.
 * The block appears where the merchant's "Mini cart" placement is enabled (off by default).
 */
class MiniCartBlocks
{
    /**
     * The Smart Buttons block type.
     */
    public const BUTTONS_BLOCK = 'woocommerce-paypal-payments/mini-cart-smart-buttons';
    /**
     * The mini-cart footer block the buttons block is inserted into (as its last child).
     */
    public const FOOTER_ANCHOR = 'woocommerce/mini-cart-footer-block';
    /**
     * Whether the "Mini cart" button placement is enabled.
     *
     * @param SettingsStatus $settings_status The settings status helper.
     */
    public static function is_buttons_enabled(SettingsStatus $settings_status): bool
    {
        return $settings_status->is_smart_button_enabled_for_location('mini-cart');
    }
    /**
     * Registers the block and its auto-insertion. Must run on `init`.
     *
     * @param ContainerInterface $c The container.
     */
    public static function register(ContainerInterface $c): void
    {
        self::register_block($c);
        $hooked_blocks_registrar = $c->get('blocks.mini-cart-hooked-blocks-registrar');
        assert($hooked_blocks_registrar instanceof \WooCommerce\PayPalCommerce\Blocks\HookedBlocksRegistrar);
        $hooked_blocks_registrar->register();
    }
    /**
     * The editor's SDK v6 button preview data (public client id + mini-cart button style),
     * or nulls when the v6 module is not loaded. The '35px' height matches the front end.
     *
     * @param ContainerInterface $c The container.
     * @return array{sdkV6: ?array, buttonStyle: ?array}
     */
    private static function sdk_v6_preview_data(ContainerInterface $c): array
    {
        $sdk_v6 = \WooCommerce\PayPalCommerce\Blocks\SdkV6EditorPreview::config($c);
        if (null === $sdk_v6 || !$c->has('sdk-v6.button-style-mapper')) {
            return array('sdkV6' => null, 'buttonStyle' => null);
        }
        $style_mapper = $c->get('sdk-v6.button-style-mapper');
        assert($style_mapper instanceof ButtonStyleMapper);
        return array('sdkV6' => $sdk_v6, 'buttonStyle' => array_merge($style_mapper->styles_for_context('mini-cart'), array('height' => '35px')));
    }
    /**
     * Registers the block: editor script, localized data and the server render callback.
     *
     * @param ContainerInterface $c The container.
     */
    private static function register_block(ContainerInterface $c): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }
        $asset_getter = $c->get('blocks.asset_getter');
        assert($asset_getter instanceof AssetGetter);
        $asset_version = $c->get('ppcp.asset-version');
        $settings_status = $c->get('wcgateway.settings.status');
        assert($settings_status instanceof SettingsStatus);
        $blocks_js_path = $c->get('ppcp.path-to-plugin-folder') . 'modules/ppcp-blocks/resources/js/';
        $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway');
        $buttons_handle = 'ppcp-mini-cart-smart-buttons-block';
        wp_register_script($buttons_handle, $asset_getter->get_asset_url('MiniCartSmartButtonsBlock/mini-cart-smart-buttons-block.js'), array(), $asset_version, \true);
        wp_localize_script($buttons_handle, 'PcpMiniCartSmartButtonsBlock', array_merge(array('placementEnabled' => self::is_buttons_enabled($settings_status), 'settingsUrl' => $settings_url, 'isSdkV6Active' => $c->has('sdk-v6.owns-current-page')), self::sdk_v6_preview_data($c)));
        register_block_type($blocks_js_path . 'MiniCartSmartButtonsBlock', array('render_callback' => static function (array $attributes) use ($c): string {
            $renderer = $c->get('blocks.mini-cart-buttons-renderer');
            assert($renderer instanceof \WooCommerce\PayPalCommerce\Blocks\MiniCartSmartButtonsRenderer);
            return $renderer->render($attributes, $c);
        }));
    }
}
