<?php

/**
 * The uninstall module services.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Uninstall;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\Recaptcha;
use WooCommerce\PayPalCommerce\Settings\Ajax\SwitchSettingsUiEndpoint;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\PaymentSettingsMigration;
use WooCommerce\PayPalCommerce\Uninstall\Assets\ClearDatabaseAssets;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;
return array('uninstall.ppcp-all-option-names' => function (ContainerInterface $container): array {
    return array($container->get('webhook.last-webhook-storage.key'), 'woocommerce_ppcp-is_pay_later_settings_migrated', 'woocommerce_' . PayPalGateway::ID . '_settings', 'woocommerce_' . CreditCardGateway::ID . '_settings', 'woocommerce_' . PayUponInvoiceGateway::ID . '_settings', 'woocommerce_' . CardButtonGateway::ID . '_settings', Settings::KEY, 'woocommerce-ppcp-version', WebhookSimulation::OPTION_ID, WebhookRegistrar::KEY, 'ppcp_payment_tokens_migration_initialized', SwitchSettingsUiEndpoint::OPTION_NAME_SHOULD_USE_OLD_UI, SwitchSettingsUiEndpoint::OPTION_NAME_MIGRATION_IS_DONE, PaymentSettingsMigration::OPTION_NAME_BCDC_MIGRATION_OVERRIDE, Recaptcha::REJECTION_COUNTER_OPTION);
}, 'uninstall.ppcp-all-scheduled-action-names' => function (ContainerInterface $container): array {
    return array('woocommerce_paypal_payments_check_pui_payment_captured', 'woocommerce_paypal_payments_payment_tokens_migration');
}, 'uninstall.ppcp-all-action-names' => function (ContainerInterface $container): array {
    return array('woocommerce_paypal_payments_uninstall');
}, 'uninstall.clear-db-endpoint' => function (ContainerInterface $container): string {
    return 'ppcp-clear-db';
}, 'uninstall.clear-database-script-data' => function (ContainerInterface $container): array {
    return array('clearDb' => array('endpoint' => \WC_AJAX::get_endpoint($container->get('uninstall.clear-db-endpoint')), 'nonce' => wp_create_nonce($container->get('uninstall.clear-db-endpoint')), 'button' => '.ppcp-clear_db_now', 'messageSelector' => '.clear-db-info-message', 'confirmationMessage' => __('Are you sure? the operation will remove all plugin data.', 'woocommerce-paypal-payments'), 'successMessage' => sprintf('<div class="updated clear-db-info-message"><p><strong>%1$s</strong></p></div>', esc_html__('The plugin data is successfully cleared.', 'woocommerce-paypal-payments')), 'failureMessage' => sprintf('<div class="error clear-db-info-message"><p><strong>%1$s</strong></p></div>', esc_html__('Operation failed. Check WooCommerce logs for more details.', 'woocommerce-paypal-payments')), 'redirectUrl' => admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway')));
}, 'uninstall.asset_getter' => static function (ContainerInterface $container): AssetGetter {
    $factory = $container->get('assets.asset_getter_factory');
    assert($factory instanceof AssetGetterFactory);
    return $factory->for_module('ppcp-uninstall');
}, 'uninstall.clear-db-assets' => function (ContainerInterface $container): ClearDatabaseAssets {
    return new ClearDatabaseAssets($container->get('uninstall.asset_getter'), $container->get('ppcp.asset-version'), 'ppcp-clear-db', $container->get('uninstall.clear-database-script-data'));
}, 'uninstall.clear-db' => function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Uninstall\ClearDatabaseInterface {
    return new \WooCommerce\PayPalCommerce\Uninstall\ClearDatabase();
});
