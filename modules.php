<?php

/**
 * The list of modules.
 *
 * @package WooCommerce\PayPalCommerce
 */
namespace WooCommerce\PayPalCommerce;

use WooCommerce\PayPalCommerce\PayLaterBlock\PayLaterBlockModule;
use WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksModule;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\PayLaterConfiguratorModule;
return function (string $root_dir): iterable {
    $modules_dir = "{$root_dir}/modules";
    $modules = array(new \WooCommerce\PayPalCommerce\PluginModule(), (require "{$modules_dir}/woocommerce-logging/module.php")(), (require "{$modules_dir}/ppcp-admin-notices/module.php")(), (require "{$modules_dir}/ppcp-api-client/module.php")(), (require "{$modules_dir}/ppcp-compat/module.php")(), (require "{$modules_dir}/ppcp-button/module.php")(), (require "{$modules_dir}/ppcp-onboarding/module.php")(), (require "{$modules_dir}/ppcp-session/module.php")(), (require "{$modules_dir}/ppcp-status-report/module.php")(), (require "{$modules_dir}/ppcp-wc-subscriptions/module.php")(), (require "{$modules_dir}/ppcp-wc-gateway/module.php")(), (require "{$modules_dir}/ppcp-webhooks/module.php")(), (require "{$modules_dir}/ppcp-vaulting/module.php")(), (require "{$modules_dir}/ppcp-order-tracking/module.php")(), (require "{$modules_dir}/ppcp-uninstall/module.php")(), (require "{$modules_dir}/ppcp-blocks/module.php")(), (require "{$modules_dir}/ppcp-paypal-subscriptions/module.php")(), (require "{$modules_dir}/ppcp-local-alternative-payment-methods/module.php")(), (require "{$modules_dir}/ppcp-settings/module.php")(), (require "{$modules_dir}/ppcp-fraud-protection/module.php")());
    // phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
    if (apply_filters('woocommerce.feature-flags.woocommerce_paypal_payments.applepay_enabled', getenv('PCP_APPLEPAY_ENABLED') !== '0')) {
        $modules[] = (require "{$modules_dir}/ppcp-applepay/module.php")();
    }
    if (apply_filters('woocommerce.feature-flags.woocommerce_paypal_payments.googlepay_enabled', getenv('PCP_GOOGLEPAY_ENABLED') !== '0')) {
        $modules[] = (require "{$modules_dir}/ppcp-googlepay/module.php")();
    }
    if (apply_filters('woocommerce.feature-flags.woocommerce_paypal_payments.card_fields_enabled', getenv('PCP_CARD_FIELDS_ENABLED') !== '0')) {
        $modules[] = (require "{$modules_dir}/ppcp-card-fields/module.php")();
    }
    if (apply_filters('woocommerce.feature-flags.woocommerce_paypal_payments.save_payment_methods_enabled', getenv('PCP_SAVE_PAYMENT_METHODS') !== '0')) {
        $modules[] = (require "{$modules_dir}/ppcp-save-payment-methods/module.php")();
    }
    if (PayLaterBlockModule::is_module_loading_required()) {
        $modules[] = (require "{$modules_dir}/ppcp-paylater-block/module.php")();
    }
    if (PayLaterConfiguratorModule::is_enabled()) {
        $modules[] = (require "{$modules_dir}/ppcp-paylater-configurator/module.php")();
        if (PayLaterWCBlocksModule::is_module_loading_required()) {
            $modules[] = (require "{$modules_dir}/ppcp-paylater-wc-blocks/module.php")();
        }
    }
    if (apply_filters('woocommerce.feature-flags.woocommerce_paypal_payments.axo_enabled', getenv('PCP_AXO_ENABLED') !== '0')) {
        $modules[] = (require "{$modules_dir}/ppcp-axo/module.php")();
        $modules[] = (require "{$modules_dir}/ppcp-axo-block/module.php")();
    }
    return $modules;
};
