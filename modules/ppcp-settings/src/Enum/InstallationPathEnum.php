<?php

/**
 * Defines valid "installation path" values that document _how_ the plugin was
 * installed. This path is used for the branded-only experience.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Enum
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Enum;

/**
 * Enum for the "installation path" values.
 */
class InstallationPathEnum
{
    /**
     * The plugin was installed via the WooCommerce onboarding wizard, by
     * selecting PayPal in the "Get a boost with our free features" screen.
     */
    public const CORE_PROFILER = 'core-profiler';
    /**
     * The plugin was installed from the WooCommerce "Payment" settings page:
     * `/wp-admin/admin.php?page=wc-settings&tab=checkout` - only available in
     * the reactified version, i.e. the "new layout".
     */
    public const PAYMENT_SETTINGS = 'payment-settings';
    /**
     * The plugin was installed in a different way, most likely the "Plugins"
     * admin page, or FTP upload, WP CLI or similar.
     *
     * Also applies to merchants that installed the plugin before we added the
     * installation path detection.
     */
    public const DIRECT = 'direct';
    /**
     * Get all valid seller types.
     *
     * @return array List of all valid options.
     */
    public static function get_valid_values(): array
    {
        return array(self::CORE_PROFILER, self::PAYMENT_SETTINGS, self::DIRECT);
    }
    /**
     * Check if a given type is valid.
     *
     * @param string $type The value to validate.
     * @return bool True, if the value is a valid installation path.
     */
    public static function is_valid(string $type): bool
    {
        return in_array($type, self::get_valid_values(), \true);
    }
}
