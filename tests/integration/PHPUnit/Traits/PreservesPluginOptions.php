<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Traits;

/**
 * Snapshots the plugin's options before a test class runs and puts them back
 * after it, for tests that delete or rewrite them.
 *
 * Integration tests run against the developer's working site, so a test that
 * deletes woocommerce-ppcp-data-common (the merchant credentials) and its
 * siblings would otherwise disconnect the merchant and reset every setting.
 */
trait PreservesPluginOptions
{
    /**
     * The options as they were before the class ran, by name.
     *
     * @var array<string, mixed>
     */
    private static array $preservedPluginOptions = [];

    protected static function preservePluginOptions(): void
    {
        self::$preservedPluginOptions = self::currentPluginOptions();
    }

    /**
     * Restores every snapshotted option and deletes the ones the tests created.
     */
    protected static function restorePluginOptions(): void
    {
        foreach (array_keys(self::currentPluginOptions()) as $name) {
            if (! array_key_exists($name, self::$preservedPluginOptions)) {
                delete_option($name);
            }
        }

        foreach (self::$preservedPluginOptions as $name => $value) {
            update_option($name, $value);
        }

        self::$preservedPluginOptions = [];
    }

    /**
     * The plugin's options: its data stores (woocommerce-ppcp-*), its gateway
     * settings and migration flags (woocommerce_ppcp-*), and the
     * woocommerce_paypal_payments_* flags.
     *
     * @return array<string, mixed>
     */
    private static function currentPluginOptions(): array
    {
        global $wpdb;

        $prefixes = ['woocommerce-ppcp-', 'woocommerce_ppcp-', 'woocommerce_paypal_payments_'];
        $where    = implode(' OR ', array_fill(0, count($prefixes), 'option_name LIKE %s'));
        $patterns = array_map(
            static function (string $prefix) use ($wpdb): string {
                return $wpdb->esc_like($prefix) . '%';
            },
            $prefixes
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $names = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE {$where}", $patterns));

        $options = [];
        foreach ($names as $name) {
            $options[$name] = get_option($name);
        }

        return $options;
    }
}
