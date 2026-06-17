<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\VaultComponent;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\ClientCredentials;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ReferenceTransactionStatus;
use WooCommerce\PayPalCommerce\VaultComponent\Authentication\VaultClientToken;
use WooCommerce\PayPalCommerce\VaultComponent\Endpoint\CreateVaultOrderEndpoint;
use WooCommerce\PayPalCommerce\VaultComponent\Helper\VaultComponentApplies;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('vault-component.eligibility.check' => static function (ContainerInterface $container): callable {
    $vault_component_applies = $container->get('vault-component.helpers.vault-component-applies');
    assert($vault_component_applies instanceof VaultComponentApplies);
    $settings_provider = $container->get('settings.settings-provider');
    assert($settings_provider instanceof SettingsProvider);
    $free_trial_helper = $container->get('wc-subscriptions.free-trial-subscription-helper');
    assert($free_trial_helper instanceof FreeTrialSubscriptionHelper);
    return static function () use ($vault_component_applies, $settings_provider, $free_trial_helper): bool {
        // A zero-total subscription cart (free trial or 100% coupon) uses the
        // save-without-purchase flow. The order-based Vault Component would
        // create a $0 order (rejected by PayPal with CANNOT_BE_ZERO_OR_NEGATIVE)
        // and only renders an empty paysheet, so disable it entirely here.
        return $settings_provider->save_paypal_and_venmo() && !$free_trial_helper->is_free_trial_cart() && $vault_component_applies->for_country() && $vault_component_applies->for_merchant();
    };
}, 'vault-component.helpers.vault-component-applies' => static function (ContainerInterface $container): VaultComponentApplies {
    $reference_transaction_status = $container->get('api.reference-transaction-status');
    assert($reference_transaction_status instanceof ReferenceTransactionStatus);
    return new VaultComponentApplies($container->get('api.merchant.country'), $reference_transaction_status);
}, 'vault-component.auth.client-token-cache' => static function (ContainerInterface $container): Cache {
    return new Cache('ppcp-vault-client-token-cache');
}, 'vault-component.auth.client-token' => static function (ContainerInterface $container): VaultClientToken {
    $logger = $container->get('woocommerce.logger.woocommerce');
    assert($logger instanceof LoggerInterface);
    $client_credentials = $container->get('api.client-credentials');
    assert($client_credentials instanceof ClientCredentials);
    $cache = $container->get('vault-component.auth.client-token-cache');
    assert($cache instanceof Cache);
    return new VaultClientToken($container->get('api.host'), $logger, $client_credentials, $cache);
}, 'vault-component.data' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\VaultComponent\VaultComponentData {
    $client_token = $container->get('vault-component.auth.client-token');
    assert($client_token instanceof VaultClientToken);
    $logger = $container->get('woocommerce.logger.woocommerce');
    assert($logger instanceof LoggerInterface);
    return new \WooCommerce\PayPalCommerce\VaultComponent\VaultComponentData($client_token, $logger);
}, 'vault-component.endpoint.create-order' => static function (ContainerInterface $container): CreateVaultOrderEndpoint {
    return new CreateVaultOrderEndpoint($container->get('button.request-data'), $container->get('api.endpoint.order'), $container->get('api.factory.purchase-unit'), $container->get('api.factory.shipping-preference'), $container->get('woocommerce.logger.woocommerce'));
});
