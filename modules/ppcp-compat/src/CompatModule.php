<?php

/**
 * The compatibility module.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat;

use Exception;
use WC_Order;
use WC_Order_Item_Product;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\SettingsModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Compat\Assets\CompatAssets;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
/**
 * Class CompatModule
 */
class CompatModule implements ServiceModule, ExtendingModule, ExecutableModule
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
     *
     * @throws NotFoundException
     */
    public function run(ContainerInterface $c): bool
    {
        add_action('woocommerce_init', function () use ($c) {
            $this->initialize_ppec_compat_layer($c);
            $this->initialize_tracking_compat_layer($c);
        });
        add_action('init', function () use ($c) {
            $asset_loader = $c->get('compat.assets');
            assert($asset_loader instanceof CompatAssets);
            $asset_loader->register();
            add_action('admin_enqueue_scripts', array($asset_loader, 'enqueue'));
        });
        $this->migrate_pay_later_settings($c);
        $this->migrate_smart_button_settings($c);
        $this->migrate_three_d_secure_setting();
        $this->fix_page_builders();
        $this->exclude_cache_plugins_js_minification($c);
        $is_nyp_active = $c->get('compat.nyp.is_supported_plugin_version_active');
        if ($is_nyp_active) {
            $this->initialize_nyp_compat_layer();
        }
        $is_wc_bookings_active = $c->get('compat.wc_bookings.is_supported_plugin_version_active');
        if ($is_wc_bookings_active) {
            $this->initialize_wc_bookings_compat_layer($c);
        }
        add_action('woocommerce_paypal_payments_gateway_migrate', static fn() => delete_transient('ppcp_has_ppec_subscriptions'));
        $this->legacy_ui_card_payment_mapping($c);
        /**
         * Automatically enable Pay Later messaging for Canadian stores during plugin update.
         *
         * This action runs during plugin updates to automatically enable Pay Later messaging for stores
         * that meet the following criteria:
         * - Store Country is set as Canada
         * - The "Stay updated" checkbox is enabled (checked in either old or new UI)
         *
         * The "Stay updated" setting is retrieved differently based on the UI version:
         * - Legacy UI: Retrieved from wcgateway.settings
         * - New UI: Retrieved from settings.data.settings model
         *
         * When all conditions are met, this will:
         * - Enable Pay Later messaging
         * - Enable Pay Later Payment Method
         * - Add default messaging locations (product, cart, checkout) to existing selections
         *
         * @hook woocommerce_paypal_payments_gateway_migrate
         */
        add_action('woocommerce_paypal_payments_gateway_migrate', static function () use ($c) {
            // Check if the "Stay updated" checkbox is enabled (checked in either old or new UI).
            $settings_model = $c->get('settings.data.settings');
            assert($settings_model instanceof SettingsModel);
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof Settings);
            $stay_updated = SettingsModule::should_use_the_old_ui() ? $settings->has('stay_updated') && $settings->get('stay_updated') : $settings_model->get_stay_updated();
            // Store Country is set as Canada.
            if ($c->get('api.shop.country') !== 'CA' || !$stay_updated) {
                return;
            }
            // Enable Pay Later messaging.
            $selected_locations = $settings->has('pay_later_messaging_locations') ? $settings->get('pay_later_messaging_locations') : array();
            $settings->set('pay_later_messaging_enabled', \true);
            $settings->set('pay_later_messaging_locations', array_unique(array_merge($selected_locations, array('product', 'cart', 'checkout'))));
            $settings->persist();
            // Enable Pay Later Payment Method.
            $payment_settings = $c->get('settings.data.payment');
            assert($payment_settings instanceof PaymentSettings);
            $payment_settings->set_paylater_enabled(\true);
            $payment_settings->save();
        });
        return \true;
    }
    /**
     * Sets up the PayPal Express Checkout compatibility layer.
     *
     * @param ContainerInterface $container The Container.
     * @return void
     */
    private function initialize_ppec_compat_layer(ContainerInterface $container): void
    {
        // Process PPEC subscription renewals through PayPal Payments.
        $handler = $container->get('compat.ppec.subscriptions-handler');
        $handler->maybe_hook();
        // Settings.
        $ppec_import = $container->get('compat.ppec.settings_importer');
        $ppec_import->maybe_hook();
        // Inbox note inviting merchant to disable PayPal Express Checkout.
        add_action('woocommerce_init', function () {
            if (is_admin() && is_callable(array(WC(), 'is_wc_admin_active')) && WC()->is_wc_admin_active() && class_exists('Automattic\WooCommerce\Admin\Notes\Notes')) {
                \WooCommerce\PayPalCommerce\Compat\PPEC\DeactivateNote::init();
            }
        });
    }
    /**
     * Sets up the 3rd party plugins compatibility layer for PayPal tracking.
     *
     * @param ContainerInterface $c The Container.
     * @return void
     */
    protected function initialize_tracking_compat_layer(ContainerInterface $c): void
    {
        $order_tracking_integrations = $c->get('order-tracking.integrations');
        foreach ($order_tracking_integrations as $integration) {
            assert($integration instanceof \WooCommerce\PayPalCommerce\Compat\Integration);
            $integration->integrate();
        }
    }
    /**
     * Migrates the old Pay Later button and messaging settings for new Pay Later Tab.
     *
     * The migration will be done on plugin upgrade if it hasn't already done.
     *
     * @param ContainerInterface $c The Container.
     * @throws NotFoundException When setting was not found.
     */
    protected function migrate_pay_later_settings(ContainerInterface $c): void
    {
        $is_pay_later_settings_migrated_option_name = 'woocommerce_ppcp-is_pay_later_settings_migrated';
        $is_pay_later_settings_migrated = get_option($is_pay_later_settings_migrated_option_name);
        if ($is_pay_later_settings_migrated) {
            return;
        }
        add_action('woocommerce_paypal_payments_gateway_migrate_on_update', function () use ($c, $is_pay_later_settings_migrated_option_name) {
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof Settings);
            $disable_funding = $settings->has('disable_funding') ? $settings->get('disable_funding') : array();
            $available_messaging_locations = array_keys($c->get('wcgateway.settings.pay-later.messaging-locations'));
            $available_button_locations = array_keys($c->get('wcgateway.button.locations'));
            if (in_array('credit', $disable_funding, \true)) {
                $settings->set('pay_later_button_enabled', \false);
            } else {
                $settings->set('pay_later_button_enabled', \true);
                $selected_button_locations = $this->selected_locations($settings, $available_button_locations, 'button');
                if (!empty($selected_button_locations)) {
                    $settings->set('pay_later_button_locations', $selected_button_locations);
                }
            }
            $selected_messaging_locations = $this->selected_locations($settings, $available_messaging_locations, 'message');
            if (!empty($selected_messaging_locations)) {
                $settings->set('pay_later_messaging_enabled', \true);
                $settings->set('pay_later_messaging_locations', $selected_messaging_locations);
                $settings->set('pay_later_enable_styling_per_messaging_location', \true);
                foreach ($selected_messaging_locations as $location) {
                    $this->migrate_message_styling_settings_by_location($settings, $location);
                }
            } else {
                $settings->set('pay_later_messaging_enabled', \false);
            }
            $settings->persist();
            update_option($is_pay_later_settings_migrated_option_name, \true);
        });
    }
    /**
     * Migrates the messages styling setting by given location.
     *
     * @param Settings $settings The settings.
     * @param string   $location The location.
     * @throws NotFoundException When setting was not found.
     */
    protected function migrate_message_styling_settings_by_location(Settings $settings, string $location): void
    {
        $old_location = $location === 'checkout' ? '' : "_{$location}";
        $layout = $settings->has("message{$old_location}_layout") ? $settings->get("message{$old_location}_layout") : 'text';
        $logo_type = $settings->has("message{$old_location}_logo") ? $settings->get("message{$old_location}_logo") : 'primary';
        $logo_position = $settings->has("message{$old_location}_position") ? $settings->get("message{$old_location}_position") : 'left';
        $text_color = $settings->has("message{$old_location}_color") ? $settings->get("message{$old_location}_color") : 'black';
        $style_color = $settings->has("message{$old_location}_flex_color") ? $settings->get("message{$old_location}_flex_color") : 'blue';
        $ratio = $settings->has("message{$old_location}_flex_ratio") ? $settings->get("message{$old_location}_flex_ratio") : '1x1';
        $settings->set("pay_later_{$location}_message_layout", $layout);
        $settings->set("pay_later_{$location}_message_logo", $logo_type);
        $settings->set("pay_later_{$location}_message_position", $logo_position);
        $settings->set("pay_later_{$location}_message_color", $text_color);
        $settings->set("pay_later_{$location}_message_flex_color", $style_color);
        $settings->set("pay_later_{$location}_message_flex_ratio", $ratio);
    }
    /**
     * Finds from old settings the selected locations for given type.
     *
     * @param Settings $settings The settings.
     * @param string[] $all_locations The list of all available locations.
     * @param string   $type The setting type: 'button' or 'message'.
     * @return string[] The list of locations, which should be selected.
     */
    protected function selected_locations(Settings $settings, array $all_locations, string $type): array
    {
        $button_locations = array();
        foreach ($all_locations as $location) {
            $location_setting_name_part = $location === 'checkout' ? '' : "_{$location}";
            $setting_name = "{$type}{$location_setting_name_part}_enabled";
            if ($settings->has($setting_name) && $settings->get($setting_name)) {
                $button_locations[] = $location;
            }
        }
        return $button_locations;
    }
    /**
     * Migrates the old smart button settings.
     *
     * The migration will be done on plugin upgrade if it hasn't already done.
     *
     * @param ContainerInterface $c The Container.
     */
    protected function migrate_smart_button_settings(ContainerInterface $c): void
    {
        $is_smart_button_settings_migrated_option_name = 'woocommerce_ppcp-is_smart_button_settings_migrated';
        $is_smart_button_settings_migrated = get_option($is_smart_button_settings_migrated_option_name);
        if ($is_smart_button_settings_migrated) {
            return;
        }
        add_action('woocommerce_paypal_payments_gateway_migrate_on_update', function () use ($c, $is_smart_button_settings_migrated_option_name) {
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof Settings);
            $available_button_locations = array_keys($c->get('wcgateway.button.locations'));
            $selected_button_locations = $this->selected_locations($settings, $available_button_locations, 'button');
            if (!empty($selected_button_locations)) {
                $settings->set('smart_button_locations', $selected_button_locations);
                $settings->persist();
            }
            update_option($is_smart_button_settings_migrated_option_name, \true);
        });
    }
    /**
     * Migrates the old Three D Secure setting located in PaymentSettings to the new location in SettingsModel.
     *
     * The migration will be done on plugin update if it hasn't already done.
     */
    protected function migrate_three_d_secure_setting(): void
    {
        add_action('woocommerce_paypal_payments_gateway_migrate_on_update', function () {
            $payment_settings = get_option('woocommerce-ppcp-data-payment') ?: array();
            $data_settings = get_option('woocommerce-ppcp-data-settings') ?: array();
            // Skip if payment settings don't have the setting but data settings do.
            if (!isset($payment_settings['three_d_secure']) || isset($data_settings['three_d_secure'])) {
                return;
            }
            // Move the setting.
            $data_settings['three_d_secure'] = $payment_settings['three_d_secure'];
            unset($payment_settings['three_d_secure']);
            // Save both.
            update_option('woocommerce-ppcp-data-settings', $data_settings);
            update_option('woocommerce-ppcp-data-payment', $payment_settings);
        });
    }
    /**
     * Changes the button rendering place for page builders
     * that do not work well with our default places.
     *
     * @return void
     */
    protected function fix_page_builders(): void
    {
        add_action('init', function () {
            if ($this->is_block_theme_active() || $this->is_elementor_pro_active() || $this->is_divi_theme_active() || $this->is_divi_child_theme_active()) {
                add_filter('woocommerce_paypal_payments_single_product_renderer_hook', function (): string {
                    return 'woocommerce_after_add_to_cart_form';
                }, 5);
            }
        });
    }
    /**
     * Checks whether the current theme is a blocks theme.
     *
     * @return bool
     */
    protected function is_block_theme_active(): bool
    {
        return function_exists('wp_is_block_theme') && wp_is_block_theme();
    }
    /**
     * Checks whether the Elementor Pro plugins (allowing integrations with WC) is active.
     *
     * @return bool
     */
    protected function is_elementor_pro_active(): bool
    {
        return is_plugin_active('elementor-pro/elementor-pro.php');
    }
    /**
     * Checks whether the Divi theme is currently used.
     *
     * @return bool
     */
    protected function is_divi_theme_active(): bool
    {
        $theme = wp_get_theme();
        return $theme->get('Name') === 'Divi';
    }
    /**
     * Checks whether a Divi child theme is currently used.
     *
     * @return bool
     */
    protected function is_divi_child_theme_active(): bool
    {
        $theme = wp_get_theme();
        $parent = $theme->parent();
        return $parent && $parent->get('Name') === 'Divi';
    }
    /**
     * Excludes PayPal scripts from being minified by cache plugins.
     *
     * @param ContainerInterface $c The Container.
     * @return void
     */
    protected function exclude_cache_plugins_js_minification(ContainerInterface $c): void
    {
        $ppcp_script_names = $c->get('compat.plugin-script-names');
        $ppcp_script_file_names = $c->get('compat.plugin-script-file-names');
        // Siteground SG Optimize.
        add_filter('sgo_js_minify_exclude', function (array $scripts) use ($ppcp_script_names) {
            return array_merge($scripts, $ppcp_script_names);
        });
        // LiteSpeed Cache.
        add_filter('litespeed_optimize_js_excludes', function (array $excluded_js) use ($ppcp_script_file_names) {
            return array_merge($excluded_js, $ppcp_script_file_names);
        });
        // W3 Total Cache.
        add_filter(
            'w3tc_minify_js_do_tag_minification',
            /**
             * Filter callback for 'w3tc_minify_js_do_tag_minification'.
             *
             * @param bool $do_tag_minification Whether to do tag minification.
             * @param string $script_tag The script tag.
             * @param string|null $file The file path.
             * @return bool Whether to do tag minification.
             * @psalm-suppress MissingClosureParamType
             */
            function (bool $do_tag_minification, string $script_tag, $file) {
                if ($file && strpos($file, 'ppcp') !== \false) {
                    return \false;
                }
                return $do_tag_minification;
            },
            10,
            3
        );
    }
    /**
     * Sets up the compatibility layer for PayPal Shipping callback & WooCommerce Name Your Price plugin.
     *
     * @return void
     */
    protected function initialize_nyp_compat_layer(): void
    {
        add_filter('woocommerce_paypal_payments_shipping_callback_cart_line_item_total', static function (string $total, array $cart_item) {
            if (!isset($cart_item['nyp'])) {
                return $total;
            }
            return $cart_item['nyp'];
        }, 10, 2);
    }
    /**
     * Sets up the compatibility layer for WooCommerce Bookings plugin.
     *
     * @param ContainerInterface $container The logger.
     * @return void
     */
    protected function initialize_wc_bookings_compat_layer(ContainerInterface $container): void
    {
        add_action('woocommerce_paypal_payments_woocommerce_order_created_from_cart', static function (WC_Order $wc_order, CartData $cart_data) use ($container): void {
            try {
                foreach ($cart_data->items() as $cart_item) {
                    if (empty($cart_item['booking'])) {
                        continue;
                    }
                    foreach ($wc_order->get_items() as $wc_order_item) {
                        if (!is_a($wc_order_item, WC_Order_Item_Product::class)) {
                            continue;
                        }
                        $product_id = $wc_order_item->get_variation_id() ?: $wc_order_item->get_product_id();
                        $product = wc_get_product($product_id);
                        if (!is_wc_booking_product($product)) {
                            continue;
                        }
                        $booking_data = array('cost' => $cart_item['booking']['_cost'] ?? 0, 'start_date' => $cart_item['booking']['_start_date'] ?? 0, 'end_date' => $cart_item['booking']['_end_date'] ?? 0, 'all_day' => $cart_item['booking']['_all_day'] ?? 0, 'local_timezone' => $cart_item['booking']['_local_timezone'] ?? 0, 'order_item_id' => $wc_order_item->get_id());
                        if (isset($cart_item['booking']['_resource_id'])) {
                            $booking_data['resource_id'] = $cart_item['booking']['_resource_id'];
                        }
                        if (isset($cart_item['booking']['_persons'])) {
                            $booking_data['persons'] = $cart_item['booking']['_persons'];
                        }
                        create_wc_booking($cart_item['product_id'], $booking_data, 'unpaid');
                    }
                }
            } catch (Exception $exception) {
                $container->get('woocommerce.logger.woocommerce')->warning('Failed to create booking for WooCommerce Bookings plugin: ' . $exception->getMessage());
            }
        }, 10, 2);
    }
    /**
     * Responsible to keep the credit card payment configuration backwards
     * compatible with the legacy UI.
     *
     * This method can be removed with the #legacy-ui code.
     *
     * @param ContainerInterface $container DI container instance.
     * @return void
     */
    protected function legacy_ui_card_payment_mapping(ContainerInterface $container): void
    {
        $new_ui = $container->get('wcgateway.settings.admin-settings-enabled');
        if ($new_ui) {
            return;
        }
        add_filter('woocommerce_paypal_payments_is_acdc_active', static function (bool $is_acdc) use ($container): bool {
            $settings = $container->get('wcgateway.settings');
            assert($settings instanceof Settings);
            try {
                return (bool) $settings->get('dcc_enabled');
            } catch (NotFoundException $exception) {
                return $is_acdc;
            }
        });
    }
}
