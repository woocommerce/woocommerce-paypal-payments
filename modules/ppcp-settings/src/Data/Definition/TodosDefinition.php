<?php

/**
 * Todos Definitions
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data\Definition
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data\Definition;

use WooCommerce\PayPalCommerce\Settings\Ajax\SwitchSettingsUiEndpoint;
use WooCommerce\PayPalCommerce\Settings\Data\TodosModel;
use WooCommerce\PayPalCommerce\Settings\Service\TodosEligibilityService;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
/**
 * Class TodosDefinition
 *
 * Provides the definitions for all available todos in the system.
 * Each todo has a title, description, eligibility condition, and associated action.
 */
class TodosDefinition
{
    /**
     * The todos eligibility service.
     *
     * @var TodosEligibilityService
     */
    protected TodosEligibilityService $eligibilities;
    /**
     * The general settings service.
     *
     * @var GeneralSettings
     */
    protected GeneralSettings $settings;
    protected TodosModel $todos;
    /**
     * Constructor.
     *
     * @param TodosEligibilityService $eligibilities The todos eligibility service.
     * @param GeneralSettings         $settings The general settings service.
     * @param TodosModel              $todos The todos model instance.
     */
    public function __construct(TodosEligibilityService $eligibilities, GeneralSettings $settings, TodosModel $todos)
    {
        $this->eligibilities = $eligibilities;
        $this->settings = $settings;
        $this->todos = $todos;
    }
    /**
     * Returns the full list of todo definitions with their eligibility conditions.
     *
     * @return array The array of todo definitions.
     */
    public function get(): array
    {
        $eligibility_checks = $this->eligibilities->get_eligibility_checks();
        $todo_items = array('enable_fastlane' => array('title' => __('Enable Fastlane', 'woocommerce-paypal-payments'), 'description' => __('Accelerate your guest checkout with Fastlane by PayPal', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['enable_fastlane'], 'action' => array('type' => 'tab', 'tab' => 'payment_methods', 'section' => 'ppcp-axo-gateway', 'highlight' => 'ppcp-axo-gateway'), 'priority' => 1), 'enable_pay_later_messaging' => array('title' => __('Enable Pay Later messaging', 'woocommerce-paypal-payments'), 'description' => __('Show Pay Later messaging to boost conversion rate and increase cart size', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['enable_pay_later_messaging'], 'action' => array('type' => 'tab', 'tab' => 'pay_later_messaging'), 'priority' => 3), 'add_pay_later_messaging_product_page' => array('title' => __('Add Pay Later messaging to the Product page', 'woocommerce-paypal-payments'), 'description' => __('Present Pay Later messaging on your Product page to boost conversion rate and increase cart size', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['add_pay_later_messaging_product_page'], 'action' => array('type' => 'tab', 'tab' => 'pay_later_messaging'), 'priority' => 4), 'add_pay_later_messaging_cart' => array('title' => __('Add Pay Later messaging to the Cart page', 'woocommerce-paypal-payments'), 'description' => __('Present Pay Later messaging on your Cart page to boost conversion rate and increase cart size', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['add_pay_later_messaging_cart'], 'action' => array('type' => 'tab', 'tab' => 'pay_later_messaging'), 'priority' => 4), 'add_pay_later_messaging_checkout' => array('title' => __('Add Pay Later messaging to the Checkout page', 'woocommerce-paypal-payments'), 'description' => __('Present Pay Later messaging on your Checkout page to boost conversion rate and increase cart size', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['add_pay_later_messaging_checkout'], 'action' => array('type' => 'tab', 'tab' => 'pay_later_messaging'), 'priority' => 4), 'configure_paypal_subscription' => array('title' => __('Configure a PayPal Subscription', 'woocommerce-paypal-payments'), 'description' => __('Connect a subscriptions-type product from WooCommerce with PayPal', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['configure_paypal_subscription'], 'action' => array('type' => 'external', 'url' => 'https://woocommerce.com/document/woocommerce-paypal-payments/#paypal-subscriptions', 'completeOnClick' => \true), 'priority' => 5), 'add_paypal_buttons_cart' => array('title' => __('Add PayPal buttons to the Cart page', 'woocommerce-paypal-payments'), 'description' => __('Allow customers to check out quickly and securely from the Cart page. Customers save time and get through checkout in fewer clicks.', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['add_paypal_buttons_cart'], 'action' => array('type' => 'tab', 'tab' => 'styling'), 'priority' => 6), 'add_paypal_buttons_block_checkout' => array('title' => __('Add PayPal buttons to the Express Checkout page', 'woocommerce-paypal-payments'), 'description' => __('Allow customers to check out quickly and securely from the Express Checkout page. Customers save time and get through checkout in fewer clicks.', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['add_paypal_buttons_block_checkout'], 'action' => array('type' => 'tab', 'tab' => 'styling'), 'priority' => 6), 'add_paypal_buttons_product' => array('title' => __('Add PayPal buttons to the Product page', 'woocommerce-paypal-payments'), 'description' => __('Allow customers to check out quickly and securely from the Product page. Customers save time and get through checkout in fewer clicks.', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['add_paypal_buttons_product'], 'action' => array('type' => 'tab', 'tab' => 'styling'), 'priority' => 6), 'register_domain_apple_pay' => array('title' => __('Register Domain for Apple Pay', 'woocommerce-paypal-payments'), 'description' => __('To enable Apple Pay, you must register your domain with PayPal', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['register_domain_apple_pay'], 'action' => array('type' => 'external', 'url' => $this->settings->is_sandbox_merchant() ? 'https://www.sandbox.paypal.com/uccservicing/apm/applepay' : 'https://www.paypal.com/uccservicing/apm/applepay', 'completeOnClick' => \true), 'priority' => 7), 'add_digital_wallets' => array('title' => __('Add digital wallets to your account', 'woocommerce-paypal-payments'), 'description' => __('Add the ability to accept Apple Pay & Google Pay to your PayPal account', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['add_digital_wallets'], 'action' => array('type' => 'external', 'url' => 'https://www.paypal.com/businessmanage/account/settings'), 'priority' => 8), 'add_apple_pay' => array('title' => __('Add Apple Pay to your account', 'woocommerce-paypal-payments'), 'description' => __('Add the ability to accept Apple Pay to your PayPal account', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['add_apple_pay'], 'action' => array('type' => 'external', 'url' => 'https://www.paypal.com/businessmanage/account/settings'), 'priority' => 9), 'add_google_pay' => array('title' => __('Add Google Pay to your account', 'woocommerce-paypal-payments'), 'description' => __('Add the ability to accept Google Pay to your PayPal account', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['add_google_pay'], 'action' => array('type' => 'external', 'url' => 'https://www.paypal.com/businessmanage/account/settings'), 'priority' => 10), 'enable_apple_pay' => array('title' => __('Enable Apple Pay', 'woocommerce-paypal-payments'), 'description' => __('Allow your buyers to check out via Apple Pay', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['enable_apple_pay'], 'action' => array('type' => 'tab', 'tab' => 'payment_methods', 'section' => 'ppcp-applepay', 'highlight' => 'ppcp-applepay'), 'priority' => 11), 'enable_google_pay' => array('title' => __('Enable Google Pay', 'woocommerce-paypal-payments'), 'description' => __('Allow your buyers to check out via Google Pay', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['enable_google_pay'], 'action' => array('type' => 'tab', 'tab' => 'payment_methods', 'section' => 'ppcp-googlepay', 'highlight' => 'ppcp-googlepay'), 'priority' => 12), 'enable_installments' => array('title' => __('Enable Installments', 'woocommerce-paypal-payments'), 'description' => __('Allow your customers to pay in installments without interest while you receive the full payment in a single transaction', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['enable_installments'], 'action' => array('type' => 'external', 'url' => 'https://www.paypal.com/businessmanage/preferences/installmentplan'), 'priority' => 13), 'apply_for_working_capital' => array('title' => __('Discover how PayPal Working Capital can fuel your business growth', 'woocommerce-paypal-payments'), 'description' => __('Approved loans are quickly deposited, so you can put them to work right away. Check eligibility.', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['apply_for_working_capital'], 'action' => array('type' => 'external', 'url' => 'https://www.paypal.com/us/business/financial-services/working-capital?partner_camp_id=woocommerce_ppwc'), 'priority' => 14), 'enable_pwc' => array('title' => __('Enable Pay with Crypto', 'woocommerce-paypal-payments'), 'description' => __('Enable customers to pay with cryptocurrency, and receive payments in USD in your PayPal balance.', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['enable_pwc'], 'action' => array('type' => 'tab', 'tab' => 'payment_methods', 'section' => 'ppcp-pwc', 'highlight' => 'ppcp-pwc'), 'priority' => 15), 'apply_for_pwc' => array('title' => __('Start your Pay with Crypto application', 'woocommerce-paypal-payments'), 'description' => __('Enable customers to pay with cryptocurrency, and receive payments in USD in your PayPal balance.', 'woocommerce-paypal-payments'), 'isEligible' => $eligibility_checks['apply_for_pwc'], 'action' => array('type' => 'external', 'url' => 'http://example.com/'), 'priority' => 16));
        $todo_items['check_settings_after_migration'] = array('title' => __("You're now using the new PayPal Payments interface!", 'woocommerce-paypal-payments'), 'description' => __('Complete the items below to ensure your payment configuration is optimized for your store.', 'woocommerce-paypal-payments'), 'isEligible' => fn(): bool => $this->is_settings_migration_done() && !$this->are_all_todos_completed($todo_items), 'action' => array('type' => 'tab', 'tab' => 'overview'), 'priority' => 0);
        return apply_filters('woocommerce_paypal_payments_todos_list', $todo_items);
    }
    /**
     * Checks whether the settings migration to the new UI has been completed.
     *
     * @return bool True if the migration is marked as done, false otherwise.
     */
    protected function is_settings_migration_done(): bool
    {
        return '1' === get_option(SwitchSettingsUiEndpoint::OPTION_NAME_MIGRATION_IS_DONE);
    }
    /**
     * Determines whether all todos have been completed or dismissed appropriately.
     *
     * A to-do is considered completed if:
     * - It's eligible (based on the callable `isEligible`), AND
     * - It is either:
     *     - A "completeOnClick" type and is present in the completed list, OR
     *     - Not a "completeOnClick" type and is present in the dismissed list.
     *
     * @param array $todos The array of to-do definitions.
     * @return bool True if all to-dos are completed or dismissed as expected, false otherwise.
     */
    protected function are_all_todos_completed(array $todos): bool
    {
        $dismissed = $this->todos->get_dismissed_todos();
        $completed = $this->todos->get_completed_onclick_todos();
        foreach ($todos as $id => $todo) {
            if (!is_callable($todo['isEligible']) || !call_user_func($todo['isEligible'])) {
                continue;
            }
            $is_click_to_complete = $todo['action']['completeOnClick'] ?? \false;
            if ($is_click_to_complete && !in_array($id, $completed, \true)) {
                return \false;
            }
            if (!$is_click_to_complete && !in_array($id, $dismissed, \true)) {
                return \false;
            }
        }
        return \true;
    }
}
