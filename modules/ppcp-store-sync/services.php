<?php

/**
 * The agentic commerce services.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Logger;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WooCommerce\WooCommerce\Logging\Logger\WooCommerceLogger;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\StoreSync\Config\AgenticWebhookConfiguration;
use WooCommerce\PayPalCommerce\StoreSync\Config\IngestionConfiguration;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\StoreSync\Auth\PayPalJwkProvider;
use WooCommerce\PayPalCommerce\StoreSync\Endpoint\CreateCartEndpoint;
use WooCommerce\PayPalCommerce\StoreSync\Endpoint\GetCartEndpoint;
use WooCommerce\PayPalCommerce\StoreSync\Endpoint\ReplaceCartEndpoint;
use WooCommerce\PayPalCommerce\StoreSync\Endpoint\CheckoutEndpoint;
use WooCommerce\PayPalCommerce\StoreSync\Ingestion\IngestionBatchProvider;
use WooCommerce\PayPalCommerce\StoreSync\Ingestion\IngestionManager;
use WooCommerce\PayPalCommerce\StoreSync\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\StoreSync\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\StoreSync\Setting\AgenticSettingsEndpoint;
use WooCommerce\PayPalCommerce\StoreSync\Setting\AgenticSettingsDataModel;
use WooCommerce\PayPalCommerce\StoreSync\Setting\AgenticSettingsModule;
use WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadataProvider;
use WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationService;
use WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationEligibility;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCheckoutProcessor;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ShippingOptionsBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCartBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\ProductValidator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\PriceValidator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\InventoryValidator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CurrencyValidator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\ShippingValidator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponValidator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponContextBuilder;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\DiscountCalculator;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\CouponResolutionBuilder;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\AppliedCouponsBuilder;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CartValidationProcessor;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticSessionManager;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreData;
/**
 * Separate source keeps high-volume ingestion entries out of the agentic (cart API) log stream:
 * Ingestion is a cron-driven background process with a constant, predictable output cadence.
 * The cart API is event-driven and session-contextual. Mixing them into one stream makes both
 * harder to read.
 *
 * When using log-files, this creates a separate file for agentic log entries
 * When using DB logging, the source makes it easy to filter for agentic entries
 */
const LOGGER_SOURCE_DEFAULT = 'woocommerce-paypal-agentic';
const LOGGER_SOURCE_INGESTION = 'woocommerce-paypal-ingestion';
return array(
    // Logging.
    'agentic.logger.default' => static function (): LoggerInterface {
        if (!class_exists(WC_Logger::class)) {
            return new NullLogger();
        }
        return new WooCommerceLogger(wc_get_logger(), LOGGER_SOURCE_DEFAULT);
    },
    'agentic.logger.ingestion' => static function (): LoggerInterface {
        if (!class_exists(WC_Logger::class)) {
            return new NullLogger();
        }
        return new WooCommerceLogger(wc_get_logger(), LOGGER_SOURCE_INGESTION);
    },
    // Configuration.
    'agentic.config.webhook_urls' => static function (ContainerInterface $c): AgenticWebhookConfiguration {
        return new AgenticWebhookConfiguration($c->get('settings.connection-state'));
    },
    'agentic.config.ingestion' => static function (): IngestionConfiguration {
        return new IngestionConfiguration();
    },
    'agentic.config.store-currency' => static function (): StoreCurrencyValue {
        return new StoreCurrencyValue();
    },
    // Registration and merchant identification.
    'agentic.merchant.provider' => static function (ContainerInterface $c): MerchantMetadataProvider {
        return new MerchantMetadataProvider($c->get('woocommerce.core'), $c->get('settings.data.general'), $c->get('agentic.config.store-currency'));
    },
    'agentic.registration.eligibility' => static function (ContainerInterface $c): RegistrationEligibility {
        return new RegistrationEligibility($c->get('agentic.merchant.provider'));
    },
    'agentic.registration.handler' => static function (ContainerInterface $c): RegistrationService {
        return new RegistrationService($c->get('agentic.config.webhook_urls'), $c->get('agentic.merchant.provider'), $c->get('agentic.logger.default'));
    },
    // Authentication services.
    'agentic.auth.key_provider' => static function (): PayPalJwkProvider {
        return new PayPalJwkProvider();
    },
    'agentic.auth.provider' => static function (ContainerInterface $c): AuthServiceProvider {
        return new AuthServiceProvider($c->get('settings.connection-state'), $c->get('agentic.auth.key_provider'), $c->get('agentic.merchant.provider'));
    },
    // Session management.
    'agentic.session.handler' => static function (): AgenticSessionHandler {
        return new AgenticSessionHandler();
    },
    // Helper services.
    'agentic.helper.product-manager' => static function (ContainerInterface $c): ProductManager {
        return new ProductManager($c->get('agentic.config.store-currency'));
    },
    'agentic.helper.session-manager' => static function (ContainerInterface $c): AgenticSessionManager {
        return new AgenticSessionManager($c->get('woocommerce.core'));
    },
    'agentic.helper.cart-builder' => static function (ContainerInterface $c): AgenticCartBuilder {
        return new AgenticCartBuilder($c->get('woocommerce.core'), $c->get('agentic.helper.product-manager'), $c->get('button.session.factory.card-data'), $c->get('api.factory.purchase-unit'), $c->get('agentic.logger.default'));
    },
    'agentic.helper.shipping-options-builder' => static function (ContainerInterface $c): ShippingOptionsBuilder {
        return new ShippingOptionsBuilder($c->get('agentic.config.store-currency'));
    },
    'agentic.helper.checkout-processor' => static function (ContainerInterface $c): AgenticCheckoutProcessor {
        return new AgenticCheckoutProcessor($c->get('agentic.helper.paypal-order-manager'), $c->get('button.helper.wc-order-creator'), $c->get('agentic.helper.cart-builder'), $c->get('agentic.response.applied-coupons-builder'), $c->get('api.factory.shipping'), $c->get('agentic.logger.default'));
    },
    'agentic.helper.paypal-order-manager' => static function (ContainerInterface $c): PayPalOrderManager {
        return new PayPalOrderManager($c->get('api.endpoint.order'), $c->get('api.endpoint.orders'), $c->get('agentic.helper.cart-builder'), $c->get('agentic.logger.default'), $c->get('agentic.config.store-currency'), $c->get('api.factory.amount'));
    },
    // Validation services.
    'agentic.validation.processor' => static function (ContainerInterface $c): CartValidationProcessor {
        return new CartValidationProcessor($c->get('agentic.logger.default'));
    },
    'agentic.validator.product' => static function (ContainerInterface $c): ProductValidator {
        return new ProductValidator($c->get('agentic.config.ingestion'));
    },
    'agentic.validator.price' => static function (): PriceValidator {
        return new PriceValidator();
    },
    'agentic.validator.inventory' => static function (ContainerInterface $c): InventoryValidator {
        return new InventoryValidator($c->get('agentic.helper.product-manager'));
    },
    'agentic.validator.shipping' => static function (): ShippingValidator {
        return new ShippingValidator();
    },
    'agentic.validator.currency' => static function (): CurrencyValidator {
        return new CurrencyValidator();
    },
    'agentic.validator.coupon.discount-calculator' => static function (ContainerInterface $c): DiscountCalculator {
        return new DiscountCalculator($c->get('agentic.helper.product-manager'));
    },
    'agentic.validator.coupon.context-builder' => static function (ContainerInterface $c): CouponContextBuilder {
        return new CouponContextBuilder($c->get('agentic.helper.product-manager'), $c->get('agentic.validator.coupon.discount-calculator'));
    },
    'agentic.validator.coupon.resolution-builder' => static function (): CouponResolutionBuilder {
        return new CouponResolutionBuilder();
    },
    'agentic.validator.coupon' => static function (ContainerInterface $c): CouponValidator {
        return new CouponValidator($c->get('agentic.validator.coupon.context-builder'), $c->get('agentic.validator.coupon.discount-calculator'), $c->get('agentic.validator.coupon.resolution-builder'));
    },
    // Response services.
    'agentic.response.applied-coupons-builder' => static function (ContainerInterface $c): AppliedCouponsBuilder {
        return new AppliedCouponsBuilder($c->get('agentic.validator.coupon.discount-calculator'));
    },
    'agentic.response.factory' => static function (ContainerInterface $c): ResponseFactory {
        return new ResponseFactory($c->get('agentic.response.applied-coupons-builder'), $c->get('agentic.helper.shipping-options-builder'));
    },
    // REST endpoints.
    'agentic.rest.create_cart' => static function (ContainerInterface $c): CreateCartEndpoint {
        return new CreateCartEndpoint($c->get('agentic.auth.provider'), $c->get('agentic.session.handler'), $c->get('agentic.helper.session-manager'), $c->get('agentic.response.factory'), $c->get('agentic.validation.processor'), $c->get('agentic.logger.default'), $c->get('agentic.helper.paypal-order-manager'), $c->get('agentic.store.data'));
    },
    'agentic.rest.get_cart' => static function (ContainerInterface $c): GetCartEndpoint {
        return new GetCartEndpoint($c->get('agentic.auth.provider'), $c->get('agentic.session.handler'), $c->get('agentic.helper.session-manager'), $c->get('agentic.response.factory'), $c->get('agentic.validation.processor'), $c->get('agentic.logger.default'), $c->get('agentic.helper.paypal-order-manager'), $c->get('agentic.store.data'));
    },
    'agentic.rest.replace_cart' => static function (ContainerInterface $c): ReplaceCartEndpoint {
        return new ReplaceCartEndpoint($c->get('agentic.auth.provider'), $c->get('agentic.session.handler'), $c->get('agentic.helper.session-manager'), $c->get('agentic.response.factory'), $c->get('agentic.validation.processor'), $c->get('agentic.logger.default'), $c->get('agentic.helper.paypal-order-manager'), $c->get('agentic.store.data'));
    },
    'agentic.rest.checkout' => static function (ContainerInterface $c): CheckoutEndpoint {
        return new CheckoutEndpoint($c->get('agentic.auth.provider'), $c->get('agentic.session.handler'), $c->get('agentic.helper.session-manager'), $c->get('agentic.response.factory'), $c->get('agentic.validation.processor'), $c->get('agentic.logger.default'), $c->get('agentic.helper.paypal-order-manager'), $c->get('agentic.store.data'), $c->get('agentic.helper.checkout-processor'));
    },
    // Store Data Factory.
    'agentic.store.data' => static function (ContainerInterface $c): StoreData {
        return new StoreData($c->get('agentic.helper.product-manager'), $c->get('agentic.config.store-currency'), $c->get('agentic.helper.cart-builder'));
    },
    // Ingestion services.
    'agentic.ingestion-batch-provider' => static function (ContainerInterface $c): IngestionBatchProvider {
        return new IngestionBatchProvider($c->get('agentic.config.ingestion'));
    },
    'agentic.ingestion-manager' => static function (ContainerInterface $c): IngestionManager {
        return new IngestionManager($c->get('agentic.config.ingestion'), $c->get('agentic.ingestion-batch-provider'), $c->get('agentic.config.webhook_urls'), $c->get('agentic.merchant.provider'), $c->get('agentic.logger.ingestion'), $c->get('agentic.helper.product-manager'));
    },
    // Settings.
    'agentic.settings.model' => static function (): AgenticSettingsDataModel {
        return new AgenticSettingsDataModel();
    },
    'agentic.settings.endpoint' => static function (ContainerInterface $c): AgenticSettingsEndpoint {
        return new AgenticSettingsEndpoint($c->get('agentic.settings.model'));
    },
    'agentic.settings.module' => static function (ContainerInterface $c): AgenticSettingsModule {
        return new AgenticSettingsModule($c->get('agentic.settings.endpoint'), $c->get('agentic.registration.eligibility'), $c->get('agentic.asset_getter'));
    },
    'agentic.asset_getter' => static function (ContainerInterface $container): AssetGetter {
        $factory = $container->get('assets.asset_getter_factory');
        assert($factory instanceof AssetGetterFactory);
        return $factory->for_module('ppcp-store-sync');
    },
);
