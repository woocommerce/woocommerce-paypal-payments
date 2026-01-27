<?php

/**
 * The status report module.
 *
 * @package WooCommerce\PayPalCommerce\StatusReport
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StatusReport;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ReferenceTransactionStatus;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Compat\PPEC\PPECHelper;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\SettingsModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Webhooks\WebhookEventStorage;
/**
 * Class StatusReportModule
 */
class StatusReportModule implements ServiceModule, ExtendingModule, ExecutableModule
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
     * @param ContainerInterface $c A services container instance.
     */
    public function run(ContainerInterface $c): bool
    {
        add_action('woocommerce_system_status_report', function () use ($c) {
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof ContainerInterface);
            $subscriptions_mode_settings = $c->get('wcgateway.settings.fields.subscriptions_mode') ?: array();
            /* @var bool $is_connected Whether onboarding is complete. */
            $is_connected = $c->get('settings.flag.is-connected');
            /* @var Bearer $bearer The bearer. */
            $bearer = $c->get('api.bearer');
            /* @var DccApplies $dcc_applies The ddc applies. */
            $dcc_applies = $c->get('api.helpers.dccapplies');
            /* @var MessagesApply $messages_apply The messages apply. */
            $messages_apply = $c->get('button.helper.messages-apply');
            /* @var SubscriptionHelper $subscription_helper The subscription helper class. */
            $subscription_helper = $c->get('wc-subscriptions.helper');
            $last_webhook_storage = $c->get('webhook.last-webhook-storage');
            assert($last_webhook_storage instanceof WebhookEventStorage);
            $reference_transaction_status = $c->get('api.reference-transaction-status');
            assert($reference_transaction_status instanceof ReferenceTransactionStatus);
            /* @var Renderer $renderer The renderer. */
            $renderer = $c->get('status-report.renderer');
            $had_ppec_plugin = PPECHelper::is_plugin_configured();
            $subscription_mode_options = $c->get('wcgateway.settings.fields.subscriptions_mode_options');
            /* @var GeneralSettings $general_settings General plugin settings. */
            $general_settings = $c->get('settings.data.general');
            // Feature flag convention.
            // phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
            $items = array(array('label' => esc_html__('Onboarded', 'woocommerce-paypal-payments'), 'exported_label' => 'Onboarded', 'description' => esc_html__('Whether PayPal account is correctly configured or not.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($this->onboarded($bearer, $is_connected))), array('label' => esc_html__('Branded only', 'woocommerce-paypal-payments'), 'exported_label' => 'Branded only', 'description' => esc_html__('Whether the plugin is in Branded only mode or not.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($general_settings->own_brand_only())), array('label' => esc_html__('New UI active', 'woocommerce-paypal-payments'), 'exported_label' => 'New UI active', 'description' => esc_html__('Indicates whether the new Settings UI is enabled.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html(!SettingsModule::should_use_the_old_ui())), array('label' => esc_html__('Shop country code', 'woocommerce-paypal-payments'), 'exported_label' => 'Shop country code', 'description' => esc_html__('Country / State value on Settings / General / Store Address.', 'woocommerce-paypal-payments'), 'value' => $c->get('api.shop.country')), array('label' => esc_html__('WooCommerce currency supported', 'woocommerce-paypal-payments'), 'exported_label' => 'WooCommerce currency supported', 'description' => esc_html__('Whether PayPal supports the default store currency or not.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($c->get('api.shop.is-currency-supported'))), array('label' => esc_html__('Advanced Card Processing available in country', 'woocommerce-paypal-payments'), 'exported_label' => 'Advanced Card Processing available in country', 'description' => esc_html__('Whether Advanced Card Processing is available in country or not.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($dcc_applies->for_country_currency())), array('label' => esc_html__('Pay Later messaging available in country', 'woocommerce-paypal-payments'), 'exported_label' => 'Pay Later messaging available in country', 'description' => esc_html__('Whether Pay Later is available in country or not.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($messages_apply->for_country())), array('label' => esc_html__('Webhook status', 'woocommerce-paypal-payments'), 'exported_label' => 'Webhook status', 'description' => esc_html__('Whether we received webhooks successfully.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html(!$last_webhook_storage->is_empty())), array('label' => esc_html__('PayPal Vault enabled', 'woocommerce-paypal-payments'), 'exported_label' => 'PayPal Vault enabled', 'description' => esc_html__('Whether vaulting option is enabled on Standard Payments settings or not.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($settings->has('vault_enabled') && $settings->get('vault_enabled'))), array('label' => esc_html__('ACDC Vault enabled', 'woocommerce-paypal-payments'), 'exported_label' => 'ACDC Vault enabled', 'description' => esc_html__('Whether vaulting option is enabled on Advanced Card Processing settings or not.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($settings->has('vault_enabled_dcc') && $settings->get('vault_enabled_dcc'))), array('label' => esc_html__('Logging enabled', 'woocommerce-paypal-payments'), 'exported_label' => 'Logging enabled', 'description' => esc_html__('Whether logging of plugin events and errors is enabled.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($settings->has('logging_enabled') && $settings->get('logging_enabled'))), array('label' => esc_html__('Reference Transactions', 'woocommerce-paypal-payments'), 'exported_label' => 'Reference Transactions', 'description' => esc_html__('Whether Reference Transactions are enabled for the connected account', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($reference_transaction_status->reference_transaction_enabled())), array('label' => esc_html__('Used PayPal Checkout plugin', 'woocommerce-paypal-payments'), 'exported_label' => 'Used PayPal Checkout plugin', 'description' => esc_html__('Whether the PayPal Checkout Gateway plugin was configured previously or not', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($had_ppec_plugin)), array('label' => esc_html__('Subscriptions Mode', 'woocommerce-paypal-payments'), 'exported_label' => 'Subscriptions Mode', 'description' => esc_html__('Whether subscriptions are active and their mode.', 'woocommerce-paypal-payments'), 'value' => $this->subscriptions_mode_text($subscription_helper->plugin_is_active(), $settings->has('subscriptions_mode') ? (string) $subscription_mode_options[$settings->get('subscriptions_mode')] : '', $subscriptions_mode_settings)), array('label' => esc_html__('PayPal Shipping Callback', 'woocommerce-paypal-payments'), 'exported_label' => 'PayPal Shipping Callback', 'description' => esc_html__('Whether the "Require final confirmation on checkout" setting is disabled.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($settings->has('blocks_final_review_enabled') && !$settings->get('blocks_final_review_enabled'))), array('label' => esc_html__('Apple Pay', 'woocommerce-paypal-payments'), 'exported_label' => 'Apple Pay', 'description' => esc_html__('Whether Apple Pay is enabled.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($settings->has('applepay_button_enabled') && $settings->get('applepay_button_enabled'))), array('label' => esc_html__('Google Pay', 'woocommerce-paypal-payments'), 'exported_label' => 'Google Pay', 'description' => esc_html__('Whether Google Pay is enabled.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($settings->has('googlepay_button_enabled') && $settings->get('googlepay_button_enabled'))), array('label' => esc_html__('Fastlane', 'woocommerce-paypal-payments'), 'exported_label' => 'Fastlane', 'description' => esc_html__('Whether Fastlane is enabled.', 'woocommerce-paypal-payments'), 'value' => $this->bool_to_html($settings->has('axo_enabled') && $settings->get('axo_enabled'))));
            echo wp_kses_post($renderer->render(esc_html__('WooCommerce PayPal Payments', 'woocommerce-paypal-payments'), $items));
        });
        return \true;
    }
    /**
     * It returns the current onboarding status.
     *
     * @param Bearer $bearer       The bearer.
     * @param bool   $is_connected Whether onboarding is complete.
     * @return bool
     */
    private function onboarded(Bearer $bearer, bool $is_connected): bool
    {
        try {
            $token = $bearer->bearer();
        } catch (RuntimeException $exception) {
            return \false;
        }
        return $is_connected && $token->is_valid();
    }
    /**
     * Returns the text associated with the subscriptions mode status.
     *
     * @param bool   $is_plugin_active     Indicates if the WooCommerce Subscriptions plugin is active.
     * @param string $subscriptions_mode   The subscriptions mode stored in settings.
     * @param array  $field_settings       The subscriptions mode field settings.
     * @return string
     */
    private function subscriptions_mode_text(bool $is_plugin_active, string $subscriptions_mode, array $field_settings): string
    {
        if (!$is_plugin_active || !$field_settings || $subscriptions_mode === 'disable_paypal_subscriptions') {
            return 'Disabled';
        }
        if (!$subscriptions_mode) {
            $subscriptions_mode = $field_settings['default'] ?? '';
        }
        // Return the options value or if it's missing from options the settings value.
        return $field_settings['options'][$subscriptions_mode] ?? $subscriptions_mode;
    }
    /**
     * Converts the bool value to "yes" icon or dash.
     *
     * @param bool $value The value.
     * @return string
     */
    private function bool_to_html(bool $value): string
    {
        return $value ? '<mark class="yes"><span class="dashicons dashicons-yes"></span></mark>' : '<mark class="no">&ndash;</mark>';
    }
}
