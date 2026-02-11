<?php

/**
 * The Axo module services.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Axo;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Axo\Assets\AxoManager;
use WooCommerce\PayPalCommerce\Axo\Endpoint\AxoScriptAttributes;
use WooCommerce\PayPalCommerce\Axo\Endpoint\FrontendLogger;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Axo\Service\AxoApplies;
use WooCommerce\PayPalCommerce\Axo\Helper\CompatibilityChecker;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter;
return array(
    // @deprecated - use `axo.eligibility.check` instead.
    'axo.eligible' => static function (ContainerInterface $container): bool {
        $eligibility_check = $container->get('axo.eligibility.check');
        return $eligibility_check();
    },
    'axo.eligibility.check' => static function (ContainerInterface $container): callable {
        $axo_applies = $container->get('axo.service.axo-applies');
        assert($axo_applies instanceof AxoApplies);
        return static function () use ($axo_applies): bool {
            return $axo_applies->for_country_currency() && $axo_applies->for_merchant();
        };
    },
    'axo.service.axo-applies' => static function (ContainerInterface $container): AxoApplies {
        return new AxoApplies($container->get('axo.supported-country-currency-matrix'), $container->get('api.shop.currency.getter'), $container->get('api.merchant.country'), $container->get('wcgateway.configuration.card-configuration'), $container->get('wc-subscriptions.helper'));
    },
    'axo.helpers.compatibility-checker' => static function (ContainerInterface $container): CompatibilityChecker {
        return new CompatibilityChecker($container->get('axo.fastlane-incompatible-plugin-names'), $container->get('wcgateway.configuration.card-configuration'));
    },
    // If AXO is configured and onboarded.
    'axo.available' => static function (ContainerInterface $container): bool {
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        return $settings->has('axo_enabled') && $settings->get('axo_enabled');
    },
    'axo.asset_getter' => static function (ContainerInterface $container): AssetGetter {
        $factory = $container->get('assets.asset_getter_factory');
        assert($factory instanceof AssetGetterFactory);
        return $factory->for_module('ppcp-axo');
    },
    'axo.manager' => static function (ContainerInterface $container): AxoManager {
        return new AxoManager($container->get('axo.asset_getter'), $container->get('ppcp.asset-version'), $container->get('session.handler'), $container->get('wcgateway.settings'), $container->get('settings.environment'), $container->get('axo.insights'), $container->get('wcgateway.settings.status'), $container->get('api.shop.currency.getter'), $container->get('woocommerce.logger.woocommerce'), $container->get('wcgateway.asset_getter'), $container->get('axo.supported-country-card-type-matrix'));
    },
    'axo.gateway' => static function (ContainerInterface $container): AxoGateway {
        return new AxoGateway($container->get('wcgateway.settings.render'), $container->get('wcgateway.settings'), $container->get('wcgateway.configuration.card-configuration'), $container->get('session.handler'), $container->get('wcgateway.order-processor'), $container->get('wcgateway.credit-card-icons'), $container->get('api.endpoint.order'), $container->get('api.factory.purchase-unit'), $container->get('api.factory.shipping-preference'), $container->get('wcgateway.transaction-url-provider'), $container->get('settings.environment'), $container->get('woocommerce.logger.woocommerce'), $container->get('wcgateway.builder.experience-context'), $container->get('settings.data.settings'));
    },
    // Data needed for the PayPal Insights.
    'axo.insights' => static function (ContainerInterface $container): array {
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        $currency = $container->get('api.shop.currency.getter');
        assert($currency instanceof CurrencyGetter);
        $session_id = '';
        if (isset(WC()->session) && method_exists(WC()->session, 'get_customer_unique_id')) {
            $session_id = substr(md5(WC()->session->get_customer_unique_id()), 0, 16);
        }
        return array('enabled' => defined('WP_DEBUG') && WP_DEBUG, 'client_id' => $settings->has('client_id') ? $settings->get('client_id') : null, 'session_id' => $session_id, 'amount' => array('currency_code' => $currency->get()), 'payment_method_selected_map' => $container->get('axo.payment_method_selected_map'), 'wp_debug' => defined('WP_DEBUG') && WP_DEBUG);
    },
    // The mapping of payment methods to the PayPal Insights 'payment_method_selected' types.
    'axo.payment_method_selected_map' => static function (ContainerInterface $container): array {
        return array('ppcp-axo-gateway' => 'card', 'ppcp-credit-card-gateway' => 'card', 'ppcp-gateway' => 'paypal', 'ppcp-googlepay' => 'google_pay', 'ppcp-applepay' => 'apple_pay', 'ppcp-multibanco' => 'other', 'ppcp-trustly' => 'other', 'ppcp-p24' => 'other', 'ppcp-mybank' => 'other', 'ppcp-ideal' => 'other', 'ppcp-eps' => 'other', 'ppcp-blik' => 'other', 'ppcp-bancontact' => 'other', 'ppcp-card-button-gateway' => 'card');
    },
    /**
     * The matrix which countries and currency combinations can be used for AXO.
     */
    'axo.supported-country-currency-matrix' => static function (ContainerInterface $container): array {
        $dcc_allowed_country_currency_matrix = $container->get('api.dcc-supported-country-currency-matrix');
        $matrix = array('US' => $dcc_allowed_country_currency_matrix['US']);
        if ($container->get('axo.uk.enabled')) {
            $matrix['GB'] = $dcc_allowed_country_currency_matrix['GB'];
        }
        if ($container->get('axo.au.enabled')) {
            $matrix['AU'] = $dcc_allowed_country_currency_matrix['AU'];
        }
        /**
         * Returns which countries and currency combinations can be used for AXO.
         */
        return apply_filters('woocommerce_paypal_payments_axo_supported_country_currency_matrix', $matrix);
    },
    /**
     * The matrix which countries and card type combinations can be used for AXO.
     */
    'axo.supported-country-card-type-matrix' => static function (ContainerInterface $container): array {
        $matrix = array('US' => array('VISA', 'MASTERCARD', 'AMEX', 'DISCOVER'), 'CA' => array('VISA', 'MASTERCARD', 'AMEX', 'DISCOVER'));
        if ($container->get('axo.uk.enabled')) {
            $matrix['GB'] = array('VISA', 'MASTERCARD', 'AMEX', 'DISCOVER');
        }
        if ($container->get('axo.au.enabled')) {
            $matrix['AU'] = array('VISA', 'MASTERCARD', 'AMEX');
        }
        /**
         * Returns which countries and card type combinations can be used for AXO.
         */
        return apply_filters('woocommerce_paypal_payments_axo_supported_country_card_type_matrix', $matrix);
    },
    'axo.settings-conflict-notice' => static function (ContainerInterface $container): string {
        $compatibility_checker = $container->get('axo.helpers.compatibility-checker');
        assert($compatibility_checker instanceof CompatibilityChecker);
        return $compatibility_checker->generate_settings_conflict_notice();
    },
    'axo.checkout-config-notice' => static function (ContainerInterface $container): string {
        $compatibility_checker = $container->get('axo.helpers.compatibility-checker');
        assert($compatibility_checker instanceof CompatibilityChecker);
        return $compatibility_checker->generate_checkout_notice();
    },
    'axo.checkout-config-notice.raw' => static function (ContainerInterface $container): string {
        $compatibility_checker = $container->get('axo.helpers.compatibility-checker');
        assert($compatibility_checker instanceof CompatibilityChecker);
        return $compatibility_checker->generate_checkout_notice(\true);
    },
    'axo.incompatible-plugins-notice' => static function (ContainerInterface $container): string {
        $settings_notice_generator = $container->get('axo.helpers.compatibility-checker');
        assert($settings_notice_generator instanceof CompatibilityChecker);
        return $settings_notice_generator->generate_incompatible_plugins_notice();
    },
    'axo.incompatible-plugins-notice.raw' => static function (ContainerInterface $container): string {
        $settings_notice_generator = $container->get('axo.helpers.compatibility-checker');
        assert($settings_notice_generator instanceof CompatibilityChecker);
        return $settings_notice_generator->generate_incompatible_plugins_notice(\true);
    },
    'axo.smart-button-location-notice' => static function (ContainerInterface $container): string {
        $dcc_configuration = $container->get('wcgateway.configuration.card-configuration');
        assert($dcc_configuration instanceof CardPaymentsConfiguration);
        if ($dcc_configuration->use_fastlane()) {
            $fastlane_settings_url = admin_url(sprintf('admin.php?page=wc-settings&tab=checkout&section=%1$s&ppcp-tab=%2$s#field-axo_heading', PayPalGateway::ID, CreditCardGateway::ID));
            $notice_content = sprintf(
                /* translators: %1$s: URL to the Checkout edit page. */
                __('<span class="highlight">Important:</span> The <code>Cart</code> & <code>Classic Cart</code> <strong>Smart Button Locations</strong> cannot be disabled while <a href="%1$s">Fastlane</a> is active.', 'woocommerce-paypal-payments'),
                esc_url($fastlane_settings_url)
            );
        } else {
            return '';
        }
        return '<div class="ppcp-notice ppcp-notice-warning"><p>' . $notice_content . '</p></div>';
    },
    'axo.endpoint.frontend-logger' => static function (ContainerInterface $container): FrontendLogger {
        return new FrontendLogger($container->get('button.request-data'), $container->get('woocommerce.logger.woocommerce'));
    },
    'axo.endpoint.script-attributes' => static function (ContainerInterface $container): AxoScriptAttributes {
        return new AxoScriptAttributes($container->get('button.request-data'), $container->get('woocommerce.logger.woocommerce'), $container->get('api.sdk-client-token'), $container->get('axo.eligible'), $container->get('button.helper.context'));
    },
    /**
     * The list of Fastlane incompatible plugins.
     *
     * @returns array<array{name: string, is_active: bool}>
     */
    'axo.fastlane-incompatible-plugins' => static function (): array {
        /**
         * Filters the list of Fastlane incompatible plugins.
         */
        return apply_filters('woocommerce_paypal_payments_fastlane_incompatible_plugins', array(array('name' => 'Elementor', 'is_active' => did_action('elementor/loaded')), array('name' => 'CheckoutWC', 'is_active' => defined('CFW_NAME')), array('name' => 'Direct Checkout for WooCommerce', 'is_active' => defined('QLWCDC_PLUGIN_NAME')), array('name' => 'Multi-Step Checkout for WooCommerce', 'is_active' => class_exists('WPMultiStepCheckout')), array('name' => 'Fluid Checkout for WooCommerce', 'is_active' => class_exists('FluidCheckout')), array('name' => 'MultiStep Checkout for WooCommerce', 'is_active' => class_exists('THWMSCF_Multistep_Checkout')), array('name' => 'WooCommerce Subscriptions', 'is_active' => class_exists('WC_Subscriptions')), array('name' => 'CartFlows', 'is_active' => class_exists('Cartflows_Loader')), array('name' => 'FunnelKit Funnel Builder', 'is_active' => class_exists('WFFN_Core')), array('name' => 'WooCommerce One Page Checkout', 'is_active' => class_exists('PP_One_Page_Checkout')), array('name' => 'All Products for Woo Subscriptions', 'is_active' => class_exists('WCS_ATT'))));
    },
    'axo.fastlane-incompatible-plugin-names' => static function (ContainerInterface $container): array {
        $incompatible_plugins = $container->get('axo.fastlane-incompatible-plugins');
        $active_plugins_list = array_filter($incompatible_plugins, function (array $plugin): bool {
            return (bool) $plugin['is_active'];
        });
        if (empty($active_plugins_list)) {
            return array();
        }
        return array_map(function (array $plugin): string {
            return "<li>{$plugin['name']}</li>";
        }, $active_plugins_list);
    },
    'axo.shipping-wc-enabled-locations' => static function (ContainerInterface $container) {
        $default_zone = new \WC_Shipping_Zone(0);
        if (!empty($default_zone->get_shipping_methods(\true))) {
            return array();
        }
        $shipping_zones = \WC_Shipping_Zones::get_zones();
        $get_zone_locations = fn(\WC_Shipping_Zone $zone): array => !empty($zone->get_shipping_methods(\true)) ? array_map(fn(object $location): string => $location->code, $zone->get_zone_locations()) : array();
        return array_unique(array_merge(...array_map($get_zone_locations, array_map(fn($zone): \WC_Shipping_Zone => $zone instanceof \WC_Shipping_Zone ? $zone : new \WC_Shipping_Zone($zone['id']), $shipping_zones))));
    },
    'axo.uk.enabled' => static function (ContainerInterface $container): bool {
        // phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
        /**
         * Filter to determine if Fastlane UK with 3D Secure should be enabled.
         *
         * @param bool $enabled Whether Fastlane UK is enabled.
         */
        return apply_filters('woocommerce.feature-flags.woocommerce_paypal_payments.axo_uk_enabled', getenv('PCP_AXO_UK_ENABLED') !== '0');
        // phpcs:enable WordPress.NamingConventions.ValidHookName.UseUnderscores
    },
    'axo.au.enabled' => static function (ContainerInterface $container): bool {
        // phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
        /**
         * Filter to determine if Fastlane AU should be enabled.
         *
         * @param bool $enabled Whether Fastlane AU is enabled.
         */
        return apply_filters('woocommerce.feature-flags.woocommerce_paypal_payments.axo_au_enabled', getenv('PCP_AXO_AU_ENABLED') !== '0');
        // phpcs:enable WordPress.NamingConventions.ValidHookName.UseUnderscores
    },
);
