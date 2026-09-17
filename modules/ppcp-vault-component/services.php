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
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('vault-component.eligibility.check' => static function (ContainerInterface $container): callable {
    $vault_component_applies = $container->get('vault-component.helpers.vault-component-applies');
    assert($vault_component_applies instanceof VaultComponentApplies);
    $settings_provider = $container->get('settings.settings-provider');
    assert($settings_provider instanceof SettingsProvider);
    return static function () use ($vault_component_applies, $settings_provider): bool {
        // This is a capability check only (merchant setting + country + reference
        // transactions). It deliberately does NOT exclude zero-total subscription
        // carts (free trial or 100% coupon): under SDK v6 this value gates whether
        // the saved-token payment method is registered at all, so excluding free
        // trials here hid the saved PayPal option entirely for returning buyers.
        // The order-based Vault Component overlay (which would create a $0 order
        // PayPal rejects with CANNOT_BE_ZERO_OR_NEGATIVE) is suppressed for those
        // carts at render time instead - see paypal-saved-token.js and the classic
        // checkout.js `is_free_trial_cart` guards.
        return $settings_provider->save_paypal_and_venmo() && $vault_component_applies->for_country() && $vault_component_applies->for_merchant();
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
