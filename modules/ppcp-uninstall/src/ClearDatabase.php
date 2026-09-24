<?php

/**
 * Clears the plugin-related data from DB.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Uninstall;

/**
 * Class ClearDatabase
 */
class ClearDatabase
{
    private array $option_names;
    private array $scheduled_actions;
    public function __construct(array $option_names, array $scheduled_actions)
    {
        $this->option_names = $option_names;
        $this->scheduled_actions = $scheduled_actions;
    }
    public function clean_up(): void
    {
        $this->clear_scheduled_actions();
        $this->delete_options();
        do_action('woocommerce_paypal_payments_uninstall');
    }
    private function delete_options(): void
    {
        foreach ($this->option_names as $option_name) {
            delete_option($option_name);
        }
    }
    private function clear_scheduled_actions(): void
    {
        foreach ($this->scheduled_actions as $action_name) {
            as_unschedule_action($action_name);
        }
    }
}
