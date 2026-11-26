<?php

/**
 * Formats a WordPress locale for the v6 SDK.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

/**
 * The v6 SDK only takes language-region pairs: de_DE_formal becomes de-DE, and a bare
 * language (ja) becomes empty so the SDK uses the browser language.
 */
final class SdkLocale
{
    public static function from_wp_locale(string $wp_locale): string
    {
        $subtags = explode('_', $wp_locale);
        if (count($subtags) < 2) {
            return '';
        }
        return strtolower($subtags[0]) . '-' . strtoupper($subtags[1]);
    }
}
