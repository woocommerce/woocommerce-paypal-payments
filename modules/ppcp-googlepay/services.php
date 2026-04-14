<?php

/**
 * The Googlepay module services.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Googlepay;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodTypeInterface;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;
use WooCommerce\PayPalCommerce\Common\Pattern\SingletonDecorator;
use WooCommerce\PayPalCommerce\Googlepay\Assets\BlocksPaymentMethod;
use WooCommerce\PayPalCommerce\Googlepay\Assets\GooglePayButton;
use WooCommerce\PayPalCommerce\Googlepay\Endpoint\UpdatePaymentDataEndpoint;
use WooCommerce\PayPalCommerce\Googlepay\Helper\ApmApplies;
use WooCommerce\PayPalCommerce\Googlepay\Helper\GoogleProductStatus;
use WooCommerce\PayPalCommerce\Googlepay\Helper\AvailabilityNotice;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array(
    // @deprecated - use `googlepay.eligibility.check` instead.
    'googlepay.eligible' => static function (ContainerInterface $container): bool {
        $eligibility_check = $container->get('googlepay.eligibility.check');
        return $eligibility_check();
    },
    'googlepay.eligibility.check' => static function (ContainerInterface $container): callable {
        $apm_applies = $container->get('googlepay.helpers.apm-applies');
        assert($apm_applies instanceof ApmApplies);
        return static function () use ($apm_applies): bool {
            return $apm_applies->for_country() && $apm_applies->for_currency() && $apm_applies->for_merchant();
        };
    },
    'googlepay.helpers.apm-applies' => static function (ContainerInterface $container): ApmApplies {
        return new ApmApplies($container->get('googlepay.supported-countries'), $container->get('googlepay.supported-currencies'), $container->get('api.shop.currency.getter'), $container->get('api.merchant.country'));
    },
    // If GooglePay is configured and onboarded.
    'googlepay.available' => static function (ContainerInterface $container): bool {
        if (apply_filters('woocommerce_paypal_payments_googlepay_validate_product_status', \true)) {
            $status = $container->get('googlepay.helpers.apm-product-status');
            assert($status instanceof GoogleProductStatus);
            /**
             * If merchant isn't onboarded via /v1/customer/partner-referrals this returns false as the API call fails.
             */
            return apply_filters('woocommerce_paypal_payments_googlepay_product_status', $status->is_active());
        }
        return \true;
    },
    // We assume it's a referral if we can check product status without API request failures.
    'googlepay.is_referral' => static function (ContainerInterface $container): bool {
        $status = $container->get('googlepay.helpers.apm-product-status');
        assert($status instanceof GoogleProductStatus);
        return !$status->has_request_failure();
    },
    'googlepay.availability_notice' => static function (ContainerInterface $container): AvailabilityNotice {
        return new AvailabilityNotice($container->get('googlepay.helpers.apm-product-status'), $container->get('wcgateway.is-wc-gateways-list-page'), $container->get('wcgateway.is-plugin-settings-page'));
    },
    'googlepay.helpers.apm-product-status' => SingletonDecorator::make(static function (ContainerInterface $container): GoogleProductStatus {
        return new GoogleProductStatus($container->get('settings.flag.is-connected'), $container->get('api.endpoint.partners'), $container->get('api.helper.failure-registry'), $container->get('api.helper.product-status-result-cache'));
    }),
    /**
     * The list of which countries can be used for GooglePay.
     */
    'googlepay.supported-countries' => static function (ContainerInterface $container): array {
        /**
         * Returns which countries can be used for GooglePay.
         */
        return apply_filters(
            'woocommerce_paypal_payments_googlepay_supported_countries',
            // phpcs:disable Squiz.Commenting.InlineComment
            array(
                'AU',
                // Australia
                'AT',
                // Austria
                'BE',
                // Belgium
                'BG',
                // Bulgaria
                'CA',
                // Canada
                'CN',
                // China
                'C2',
                // China (PayPal)
                'CY',
                // Cyprus
                'CZ',
                // Czech Republic
                'DK',
                // Denmark
                'EE',
                // Estonia
                'FI',
                // Finland
                'FR',
                // France
                'DE',
                // Germany
                'GR',
                // Greece
                'HK',
                // Hong Kong
                'HU',
                // Hungary
                'IE',
                // Ireland
                'IT',
                // Italy
                'LV',
                // Latvia
                'LI',
                // Liechtenstein
                'LT',
                // Lithuania
                'LU',
                // Luxembourg
                'MT',
                // Malta
                'MX',
                // Mexico
                'NL',
                // Netherlands
                'NO',
                // Norway
                'PL',
                // Poland
                'PT',
                // Portugal
                'RO',
                // Romania
                'SG',
                // Singapore
                'SK',
                // Slovakia
                'SI',
                // Slovenia
                'ES',
                // Spain
                'SE',
                // Sweden
                'US',
                // United States
                'GB',
                // United Kingdom
                'YT',
                // Mayotte
                'RE',
                // Reunion
                'GP',
                // Guadelope
                'GF',
                // French Guiana
                'MQ',
            )
        );
    },
    /**
     * The list of which currencies can be used for GooglePay.
     */
    'googlepay.supported-currencies' => static function (ContainerInterface $container): array {
        /**
         * Returns which currencies can be used for GooglePay.
         */
        return apply_filters(
            'woocommerce_paypal_payments_googlepay_supported_currencies',
            // phpcs:disable Squiz.Commenting.InlineComment
            array(
                'AUD',
                // Australian Dollar
                'BRL',
                // Brazilian Real
                'CAD',
                // Canadian Dollar
                'CHF',
                // Swiss Franc
                'CZK',
                // Czech Koruna
                'DKK',
                // Danish Krone
                'EUR',
                // Euro
                'HKD',
                // Hong Kong Dollar
                'GBP',
                // British Pound Sterling
                'HUF',
                // Hungarian Forint
                'ILS',
                // Israeli New Shekel
                'JPY',
                // Japanese Yen
                'MXN',
                // Mexican Peso
                'NOK',
                // Norwegian Krone
                'NZD',
                // New Zealand Dollar
                'PHP',
                // Philippine Peso
                'PLN',
                // Polish Zloty
                'SGD',
                // Singapur-Dollar
                'SEK',
                // Swedish Krona
                'THB',
                // Thai Baht
                'TWD',
                // New Taiwan Dollar
                'USD',
            )
        );
    },
    'googlepay.button' => static function (ContainerInterface $container): ButtonInterface {
        return new GooglePayButton($container->get('googlepay.asset_getter'), $container->get('googlepay.sdk_url'), $container->get('ppcp.asset-version'), $container->get('wc-subscriptions.helper'), $container->get('settings.settings-provider'), $container->get('settings.environment'), $container->get('button.helper.context'));
    },
    'googlepay.blocks-payment-method' => static function (ContainerInterface $container): PaymentMethodTypeInterface {
        return new BlocksPaymentMethod('ppcp-googlepay', $container->get('googlepay.asset_getter'), $container->get('ppcp.asset-version'), $container->get('googlepay.button'), $container->get('blocks.method'), $container->get('button.helper.context'), $container->get('settings.settings-provider'));
    },
    'googlepay.asset_getter' => static function (ContainerInterface $container): AssetGetter {
        $factory = $container->get('assets.asset_getter_factory');
        assert($factory instanceof AssetGetterFactory);
        return $factory->for_module('ppcp-googlepay');
    },
    'googlepay.sdk_url' => static function (ContainerInterface $container): string {
        return 'https://pay.google.com/gp/p/js/pay.js';
    },
    'googlepay.endpoint.update-payment-data' => static function (ContainerInterface $container): UpdatePaymentDataEndpoint {
        return new UpdatePaymentDataEndpoint($container->get('button.request-data'), $container->get('woocommerce.logger.woocommerce'));
    },
    'googlepay.enable-url-sandbox' => static function (ContainerInterface $container): string {
        return 'https://www.sandbox.paypal.com/bizsignup/add-product?product=payment_methods&capabilities=GOOGLE_PAY';
    },
    'googlepay.enable-url-live' => static function (ContainerInterface $container): string {
        return 'https://www.paypal.com/bizsignup/add-product?product=payment_methods&capabilities=GOOGLE_PAY';
    },
    'googlepay.wc-gateway' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway {
        return new \WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway($container->get('wcgateway.order-processor'), $container->get('api.factory.paypal-checkout-url'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('session.handler'), $container->get('googlepay.asset_getter'), $container->get('woocommerce.logger.woocommerce'));
    },
);
