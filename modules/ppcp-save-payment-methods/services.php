<?php

/**
 * The save payment methods module services.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SavePaymentMethods;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreatePaymentToken;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreateSetupToken;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreatePaymentTokenForGuest;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Helper\SavePaymentMethodsApplies;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Service\PaymentMethodTokensChecker;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array(
    // @deprecated - use `save-payment-methods.eligibility.check` instead.
    'save-payment-methods.eligible' => static function (ContainerInterface $container): bool {
        $eligibility_check = $container->get('save-payment-methods.eligibility.check');
        return $eligibility_check();
    },
    'save-payment-methods.eligibility.check' => static function (ContainerInterface $container): callable {
        $save_payment_methods_applies = $container->get('save-payment-methods.helpers.save-payment-methods-applies');
        assert($save_payment_methods_applies instanceof SavePaymentMethodsApplies);
        return static function () use ($save_payment_methods_applies): bool {
            return $save_payment_methods_applies->for_country() && $save_payment_methods_applies->for_merchant();
        };
    },
    'save-payment-methods.helpers.save-payment-methods-applies' => static function (ContainerInterface $container): SavePaymentMethodsApplies {
        return new SavePaymentMethodsApplies($container->get('save-payment-methods.supported-countries'), $container->get('api.merchant.country'));
    },
    'save-payment-methods.supported-countries' => static function (ContainerInterface $container): array {
        if (has_filter('woocommerce_paypal_payments_save_payment_methods_supported_country_currency_matrix')) {
            _deprecated_hook('woocommerce_paypal_payments_save_payment_methods_supported_country_currency_matrix', '3.0.0', 'woocommerce_paypal_payments_save_payment_methods_supported_countries', esc_attr__('Please use the new Hook to filter countries for saved payments in PayPal Payments.', 'woocommerce-paypal-payments'));
        }
        return apply_filters('woocommerce_paypal_payments_save_payment_methods_supported_countries', array('AU', 'AT', 'BE', 'BG', 'CA', 'CN', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'HK', 'HU', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'NO', 'NL', 'PL', 'PT', 'RO', 'SG', 'SK', 'SI', 'ES', 'SE', 'GB', 'US', 'YT', 'RE', 'GP', 'GF', 'MQ'));
    },
    'save-payment-methods.asset_getter' => static function (ContainerInterface $container): AssetGetter {
        $factory = $container->get('assets.asset_getter_factory');
        assert($factory instanceof AssetGetterFactory);
        return $factory->for_module('ppcp-save-payment-methods');
    },
    'save-payment-methods.endpoint.create-setup-token' => static function (ContainerInterface $container): CreateSetupToken {
        return new CreateSetupToken($container->get('button.request-data'), $container->get('api.endpoint.payment-method-tokens'));
    },
    'save-payment-methods.endpoint.create-payment-token' => static function (ContainerInterface $container): CreatePaymentToken {
        return new CreatePaymentToken($container->get('button.request-data'), $container->get('api.endpoint.payment-method-tokens'), $container->get('vaulting.wc-payment-tokens'));
    },
    'save-payment-methods.endpoint.create-payment-token-for-guest' => static function (ContainerInterface $container): CreatePaymentTokenForGuest {
        return new CreatePaymentTokenForGuest($container->get('button.request-data'), $container->get('api.endpoint.payment-method-tokens'));
    },
    'save-payment-methods.service.payment-method-tokens-checker' => static function (ContainerInterface $container): PaymentMethodTokensChecker {
        return new PaymentMethodTokensChecker($container->get('api.endpoint.payment-tokens'));
    },
);
