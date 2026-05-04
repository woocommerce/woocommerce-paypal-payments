<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\VaultComponent;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ReferenceTransactionStatus;
use WooCommerce\PayPalCommerce\VaultComponent\Endpoint\CreateVaultOrderEndpoint;
use WooCommerce\PayPalCommerce\VaultComponent\Helper\VaultComponentApplies;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('vault-component.eligibility.check' => static function (ContainerInterface $container): callable {
    $vault_component_applies = $container->get('vault-component.helpers.vault-component-applies');
    assert($vault_component_applies instanceof VaultComponentApplies);
    return static function () use ($vault_component_applies): bool {
        return $vault_component_applies->for_country() && $vault_component_applies->for_merchant();
    };
}, 'vault-component.helpers.vault-component-applies' => static function (ContainerInterface $container): VaultComponentApplies {
    $reference_transaction_status = $container->get('api.reference-transaction-status');
    assert($reference_transaction_status instanceof ReferenceTransactionStatus);
    return new VaultComponentApplies($container->get('vault-component.supported-countries'), $container->get('api.merchant.country'), $reference_transaction_status);
}, 'vault-component.supported-countries' => static function (ContainerInterface $container): array {
    return apply_filters('woocommerce_paypal_payments_vault_component_supported_countries', array('US'));
}, 'vault-component.endpoint.create-order' => static function (ContainerInterface $container): CreateVaultOrderEndpoint {
    return new CreateVaultOrderEndpoint($container->get('button.request-data'), $container->get('api.endpoint.order'), $container->get('api.factory.purchase-unit'), $container->get('api.factory.shipping-preference'), $container->get('woocommerce.logger.woocommerce'));
});
