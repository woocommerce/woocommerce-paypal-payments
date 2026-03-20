<?php

/**
 * The uninstallation module services.
 *
 * @package WooCommerce\PayPalCommerce\Uninstall
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Uninstall;

use WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\Recaptcha;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\MigrationManager;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\PaymentSettingsMigration;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;
return array('uninstall.ppcp-all-option-names' => static function (ContainerInterface $container): array {
    $own_option_keys = array($container->get('webhook.last-webhook-storage.key'), 'woocommerce_ppcp-is_pay_later_settings_migrated', 'woocommerce_' . PayPalGateway::ID . '_settings', 'woocommerce_' . CreditCardGateway::ID . '_settings', 'woocommerce_' . PayUponInvoiceGateway::ID . '_settings', 'woocommerce_' . CardButtonGateway::ID . '_settings', 'woocommerce-ppcp-version', WebhookSimulation::OPTION_ID, WebhookRegistrar::KEY, 'ppcp_payment_tokens_migration_initialized', MigrationManager::OPTION_NAME_MIGRATION_IS_DONE, PaymentSettingsMigration::OPTION_NAME_BCDC_MIGRATION_OVERRIDE, Recaptcha::REJECTION_COUNTER_OPTION, 'ppcp_bn_code');
    /**
     * Remove legacy settings data:
     * This item stores data of the legacy settings UI, which is only available by downgrading
     * the plugin to an earlier version. Since this service is not called on plain
     * uninstallation, but only when manually overriding a filter value, we can assume that
     * it's safe and intended to also remove this item from the database.
     */
    $own_option_keys[] = 'woocommerce-ppcp-settings';
    /*
     * This flag is set by WooCommerce when the plugin is installed via their
     * Settings page. We remove it here, as uninstalling the plugin should
     * open up the possibility of installing it from a different source in
     * "white label" mode.
     */
    $own_option_keys[] = 'woocommerce_paypal_branded';
    return $own_option_keys;
}, 'uninstall.ppcp-all-scheduled-action-names' => static function (): array {
    return array('woocommerce_paypal_payments_check_pui_payment_captured', 'woocommerce_paypal_payments_payment_tokens_migration');
}, 'uninstall.clear-db' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Uninstall\ClearDatabase {
    return new \WooCommerce\PayPalCommerce\Uninstall\ClearDatabase($container->get('uninstall.ppcp-all-option-names'), $container->get('uninstall.ppcp-all-scheduled-action-names'));
});
