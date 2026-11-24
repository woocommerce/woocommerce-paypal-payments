<?php

/**
 * SDK v6 config for block editor previews.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\SdkV6\Helper\SdkLocale;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
/**
 * The SDK v6 config the editor previews load the SDK with.
 *
 * The v6 frontend config is never printed in admin, so the editor gets its own:
 * the public client id instead of a client token.
 */
class SdkV6EditorPreview
{
    /**
     * The editor's SDK v6 config, or null when the v6 module is not loaded.
     *
     * @param ContainerInterface $c The container.
     * @return array{sdkUrl: string, clientId: string, currency: string, locale: string}|null
     */
    public static function config(ContainerInterface $c): ?array
    {
        if (!$c->has('sdk-v6.owns-current-page')) {
            return null;
        }
        $environment = $c->get('settings.environment');
        assert($environment instanceof Environment);
        // Same script URL as SdkV6Manager::script_data().
        $base_url = $environment->is_sandbox() ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
        return array('sdkUrl' => $base_url . '/web-sdk/v6/core', 'clientId' => (string) $c->get('button.client_id'), 'currency' => get_woocommerce_currency(), 'locale' => SdkLocale::from_wp_locale(get_locale()));
    }
}
