<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\FraudProtection;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\Recaptcha;
use WooCommerce\PayPalCommerce\FraudProtection\Recaptcha\RecaptchaIntegration;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
return array('fraud-protection.asset_getter' => static function (ContainerInterface $container): AssetGetter {
    $factory = $container->get('assets.asset_getter_factory');
    assert($factory instanceof AssetGetterFactory);
    return $factory->for_module('ppcp-fraud-protection');
}, 'fraud-protection.recaptcha' => static function (ContainerInterface $container): Recaptcha {
    return new Recaptcha($container->get('fraud-protection.recaptcha.integration'), $container->get('fraud-protection.recaptcha.payment-methods'), $container->get('fraud-protection.asset_getter'), $container->get('ppcp.asset-version'), $container->get('woocommerce.logger.woocommerce'), $container->get('fraud-protection.recaptcha.rejection-counter'));
}, 'fraud-protection.recaptcha.integration' => static function (): RecaptchaIntegration {
    return new RecaptchaIntegration();
}, 'fraud-protection.recaptcha.payment-methods' => static function (): array {
    return apply_filters('woocommerce_paypal_payments_recaptcha_payment_methods', array(PayPalGateway::ID, CreditCardGateway::ID, CardButtonGateway::ID));
}, 'fraud-protection.recaptcha.rejection-counter' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\FraudProtection\PersistentCounter {
    return new \WooCommerce\PayPalCommerce\FraudProtection\PersistentCounter(Recaptcha::REJECTION_COUNTER_OPTION);
}, 'fraud-protection.wc-tasks.recaptcha-task-config' => static function (ContainerInterface $container): array {
    $recaptcha_settings = get_option('woocommerce_ppcp-recaptcha_settings', array());
    if (isset($recaptcha_settings['enabled']) && 'yes' === $recaptcha_settings['enabled']) {
        return array();
    }
    return array(array('id' => 'ppcp-recaptcha-protection-task', 'title' => __('Enable required fraud protection for PayPal Payments', 'woocommerce-paypal-payments'), 'description' => __('Help protect your store and maintain compliance.', 'woocommerce-paypal-payments'), 'redirect_url' => admin_url('admin.php?page=wc-settings&tab=integration&section=ppcp-recaptcha')));
});
