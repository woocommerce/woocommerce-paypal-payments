<?php

/**
 * The services
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcSubscriptions;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcSubscriptions\Endpoint\SubscriptionChangePaymentMethod;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\RealTimeAccountUpdaterHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Service\ChangePaymentMethod;
return array('wc-subscriptions.helper' => static function (ContainerInterface $container): SubscriptionHelper {
    return new SubscriptionHelper();
}, 'wc-subscriptions.helpers.real-time-account-updater' => static function (ContainerInterface $container): RealTimeAccountUpdaterHelper {
    return new RealTimeAccountUpdaterHelper();
}, 'wc-subscriptions.renewal-handler' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\WcSubscriptions\RenewalHandler {
    $logger = $container->get('woocommerce.logger.woocommerce');
    $endpoint = $container->get('api.endpoint.order');
    $purchase_unit_factory = $container->get('api.factory.purchase-unit');
    $payer_factory = $container->get('api.factory.payer');
    $environment = $container->get('settings.environment');
    $settings_provider = $container->get('settings.settings-provider');
    $authorized_payments_processor = $container->get('wcgateway.processor.authorized-payments');
    $funding_source_renderer = $container->get('wcgateway.funding-source.renderer');
    return new \WooCommerce\PayPalCommerce\WcSubscriptions\RenewalHandler($logger, $endpoint, $purchase_unit_factory, $container->get('api.factory.shipping-preference'), $payer_factory, $environment, $settings_provider, $authorized_payments_processor, $funding_source_renderer, $container->get('wc-subscriptions.helpers.real-time-account-updater'), $container->get('wc-subscriptions.helper'), $container->get('wc-payment-tokens.wc-payment-tokens'), $container->get('wcgateway.builder.experience-context'));
}, 'wc-subscriptions.endpoint.subscription-change-payment-method' => static function (ContainerInterface $container): SubscriptionChangePaymentMethod {
    return new SubscriptionChangePaymentMethod($container->get('button.request-data'));
}, 'wc-subscriptions.change-payment-method' => static function (ContainerInterface $container): ChangePaymentMethod {
    return new ChangePaymentMethod($container->get('button.helper.context'));
}, 'wc-subscriptions.free-trial-subscription-helper' => static function (ContainerInterface $container): FreeTrialSubscriptionHelper {
    return new FreeTrialSubscriptionHelper();
});
