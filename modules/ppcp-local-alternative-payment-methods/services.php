<?php

/**
 * The local alternative payment methods module services.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PayUponInvoiceOrderEndpoint;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice\PaymentSourceFactory;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice\PayUponInvoiceIntegration;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice\PUIPaymentMethod;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('ppcp-local-apms.asset_getter' => static function (ContainerInterface $container): AssetGetter {
    $factory = $container->get('assets.asset_getter_factory');
    assert($factory instanceof AssetGetterFactory);
    return $factory->for_module('ppcp-local-alternative-payment-methods');
}, 'ppcp-local-apms.payment-methods' => static function (ContainerInterface $container): array {
    return array('pwc' => array('id' => \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway::ID, 'countries' => array(), 'currencies' => array('USD')), 'bancontact' => array('id' => \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway::ID, 'countries' => array('BE'), 'currencies' => array('EUR')), 'blik' => array('id' => \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway::ID, 'countries' => array('PL'), 'currencies' => array('PLN')), 'eps' => array('id' => \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway::ID, 'countries' => array('AT'), 'currencies' => array('EUR')), 'ideal' => array('id' => \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway::ID, 'countries' => array('NL'), 'currencies' => array('EUR')), 'mybank' => array('id' => \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway::ID, 'countries' => array('IT'), 'currencies' => array('EUR')), 'p24' => array('id' => \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway::ID, 'countries' => array('PL'), 'currencies' => array('EUR', 'PLN')), 'trustly' => array('id' => \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway::ID, 'countries' => array('AT', 'DE', 'DK', 'EE', 'ES', 'FI', 'GB', 'LT', 'LV', 'NL', 'NO', 'SE'), 'currencies' => array('EUR', 'DKK', 'SEK', 'GBP', 'NOK')), 'multibanco' => array('id' => \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway::ID, 'countries' => array('PT'), 'currencies' => array('EUR')), 'oxxo' => array('id' => \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\OXXOGateway::ID, 'countries' => array('MX'), 'currencies' => array('MXN')), 'pui' => array('id' => PayUponInvoiceGateway::ID, 'countries' => array('DE'), 'currencies' => array('EUR'), 'disable_funding' => \false));
}, 'ppcp-local-apms.product-status' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\LocalApmProductStatus {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\LocalApmProductStatus($container->get('settings.flag.is-connected'), $container->get('api.endpoint.partners'), $container->get('api.helper.failure-registry'), $container->get('api.helper.product-status-result-cache'));
}, 'ppcp-local-apms.pwc.wc-gateway' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway($container->get('wcgateway.asset_getter'), $container->get('api.endpoint.orders'), $container->get('api.factory.purchase-unit'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('wcgateway.builder.experience-context'));
}, 'ppcp-local-apms.bancontact.wc-gateway' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway($container->get('api.endpoint.orders'), $container->get('api.factory.purchase-unit'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('wcgateway.builder.experience-context'));
}, 'ppcp-local-apms.blik.wc-gateway' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway($container->get('api.endpoint.orders'), $container->get('api.factory.purchase-unit'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('wcgateway.builder.experience-context'));
}, 'ppcp-local-apms.eps.wc-gateway' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway($container->get('api.endpoint.orders'), $container->get('api.factory.purchase-unit'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('wcgateway.builder.experience-context'));
}, 'ppcp-local-apms.ideal.wc-gateway' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway($container->get('api.endpoint.orders'), $container->get('api.factory.purchase-unit'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('wcgateway.builder.experience-context'));
}, 'ppcp-local-apms.mybank.wc-gateway' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway($container->get('api.endpoint.orders'), $container->get('api.factory.purchase-unit'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('wcgateway.builder.experience-context'));
}, 'ppcp-local-apms.p24.wc-gateway' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway($container->get('api.endpoint.orders'), $container->get('api.factory.purchase-unit'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('wcgateway.builder.experience-context'));
}, 'ppcp-local-apms.trustly.wc-gateway' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway($container->get('api.endpoint.orders'), $container->get('api.factory.purchase-unit'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('wcgateway.builder.experience-context'));
}, 'ppcp-local-apms.multibanco.wc-gateway' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway($container->get('api.endpoint.orders'), $container->get('api.factory.purchase-unit'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('wcgateway.builder.experience-context'));
}, 'ppcp-local-apms.pwc.payment-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCPaymentMethod {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCPaymentMethod($container->get('ppcp-local-apms.asset_getter'), $container->get('ppcp.asset-version'), $container->get('ppcp-local-apms.pwc.wc-gateway'));
}, 'ppcp-local-apms.bancontact.payment-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactPaymentMethod {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactPaymentMethod($container->get('ppcp-local-apms.asset_getter'), $container->get('ppcp.asset-version'), $container->get('ppcp-local-apms.bancontact.wc-gateway'));
}, 'ppcp-local-apms.blik.payment-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikPaymentMethod {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikPaymentMethod($container->get('ppcp-local-apms.asset_getter'), $container->get('ppcp.asset-version'), $container->get('ppcp-local-apms.blik.wc-gateway'));
}, 'ppcp-local-apms.eps.payment-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSPaymentMethod {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSPaymentMethod($container->get('ppcp-local-apms.asset_getter'), $container->get('ppcp.asset-version'), $container->get('ppcp-local-apms.eps.wc-gateway'));
}, 'ppcp-local-apms.ideal.payment-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealPaymentMethod {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealPaymentMethod($container->get('ppcp-local-apms.asset_getter'), $container->get('ppcp.asset-version'), $container->get('ppcp-local-apms.ideal.wc-gateway'));
}, 'ppcp-local-apms.mybank.payment-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankPaymentMethod {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankPaymentMethod($container->get('ppcp-local-apms.asset_getter'), $container->get('ppcp.asset-version'), $container->get('ppcp-local-apms.mybank.wc-gateway'));
}, 'ppcp-local-apms.p24.payment-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24PaymentMethod {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24PaymentMethod($container->get('ppcp-local-apms.asset_getter'), $container->get('ppcp.asset-version'), $container->get('ppcp-local-apms.p24.wc-gateway'));
}, 'ppcp-local-apms.trustly.payment-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyPaymentMethod {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyPaymentMethod($container->get('ppcp-local-apms.asset_getter'), $container->get('ppcp.asset-version'), $container->get('ppcp-local-apms.trustly.wc-gateway'));
}, 'ppcp-local-apms.multibanco.payment-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoPaymentMethod {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoPaymentMethod($container->get('ppcp-local-apms.asset_getter'), $container->get('ppcp.asset-version'), $container->get('ppcp-local-apms.multibanco.wc-gateway'));
}, 'ppcp-local-apms.oxxo.wc-gateway' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\OXXOGateway {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\OXXOGateway($container->get('api.endpoint.orders'), $container->get('api.factory.purchase-unit'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('wcgateway.builder.experience-context'), $container->get('ppcp-local-apms.asset_getter'));
}, 'ppcp-local-apms.oxxo.payment-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\OXXOPaymentMethod {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\OXXOPaymentMethod($container->get('ppcp-local-apms.asset_getter'), $container->get('ppcp.asset-version'), $container->get('ppcp-local-apms.oxxo.wc-gateway'));
}, 'ppcp-local-apms.oxxo.integration' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\OXXOIntegration {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\OXXOIntegration($container->get('wcgateway.checkout-helper'));
}, 'ppcp-local-apms.pui.order-endpoint' => static function (ContainerInterface $container): PayUponInvoiceOrderEndpoint {
    return new PayUponInvoiceOrderEndpoint($container->get('api.host'), $container->get('api.bearer'), $container->get('api.factory.order'), $container->get('wcgateway.fraudnet'), $container->get('woocommerce.logger.woocommerce'));
}, 'ppcp-local-apms.pui.payment-source-factory' => static function (ContainerInterface $container): PaymentSourceFactory {
    return new PaymentSourceFactory($container->get('settings.data.payment'));
}, 'ppcp-local-apms.pui.wc-gateway' => static function (ContainerInterface $container): PayUponInvoiceGateway {
    return new PayUponInvoiceGateway($container->get('ppcp-local-apms.pui.order-endpoint'), $container->get('api.factory.purchase-unit'), $container->get('ppcp-local-apms.pui.payment-source-factory'), $container->get('settings.environment'), $container->get('wcgateway.transaction-url-provider'), $container->get('woocommerce.logger.woocommerce'), $container->get('wcgateway.checkout-helper'), $container->get('settings.flag.is-connected'), $container->get('wcgateway.processor.refunds'), $container->get('ppcp-local-apms.asset_getter'));
}, 'ppcp-local-apms.pui.payment-method' => static function (ContainerInterface $container): PUIPaymentMethod {
    return new PUIPaymentMethod($container->get('ppcp-local-apms.asset_getter'), $container->get('ppcp.asset-version'), $container->get('ppcp-local-apms.pui.wc-gateway'));
}, 'ppcp-local-apms.pui.integration' => static function (ContainerInterface $container): PayUponInvoiceIntegration {
    return new PayUponInvoiceIntegration($container->get('ppcp-local-apms.pui.order-endpoint'), $container->get('woocommerce.logger.woocommerce'), $container->get('wcgateway.pay-upon-invoice-product-status'), $container->get('wcgateway.pay-upon-invoice-helper'), $container->get('wcgateway.checkout-helper'), $container->get('api.factory.capture'), $container->get('settings.data.payment'), $container->get('settings.flag.is-connected'));
}, 'ppcp-local-apms.eligibility.check' => static function (ContainerInterface $container): bool {
    $general_settings = $container->get('settings.data.general');
    assert($general_settings instanceof GeneralSettings);
    $merchant_data = $general_settings->get_merchant_data();
    $merchant_country = $merchant_data->merchant_country;
    $ineligible_countries = array('RU', 'BR', 'JP');
    return !in_array($merchant_country, $ineligible_countries, \true);
}, 'ppcp-local-apms.pwc.currency.check' => static function (ContainerInterface $container): bool {
    return 'USD' === $container->get('api.shop.currency.getter')->get();
}, 'ppcp-local-apms.pwc.eligibility.check' => static function (ContainerInterface $container): bool {
    return $container->get('ppcp-local-apms.eligibility.check') && $container->get('ppcp-local-apms.pwc.currency.check');
}, 'ppcp-local-apms.pui.country.check' => static function (ContainerInterface $container): bool {
    return 'DE' === $container->get('api.merchant.country');
}, 'ppcp-local-apms.pui.currency.check' => static function (ContainerInterface $container): bool {
    return 'EUR' === $container->get('api.shop.currency.getter')->get();
}, 'ppcp-local-apms.pui.eligibility.check' => static function (ContainerInterface $container): bool {
    return $container->get('ppcp-local-apms.pui.country.check') && $container->get('ppcp-local-apms.pui.currency.check');
});
