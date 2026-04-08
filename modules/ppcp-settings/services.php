<?php

/**
 * The Settings module services.
 *
 * @package WooCommerce\PayPalCommerce\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings;

use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Applepay\Assets\AppleProductStatus;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Googlepay\Helper\GoogleProductStatus;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDependenciesDefinition;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\Data\StylingSettings;
use WooCommerce\PayPalCommerce\Settings\Data\FastlaneSettings;
use WooCommerce\PayPalCommerce\Settings\Data\PayLaterMessagingSettings;
use WooCommerce\PayPalCommerce\Settings\Data\TodosModel;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\TodosDefinition;
use WooCommerce\PayPalCommerce\Settings\Endpoint\AuthenticationRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\CommonRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\FeaturesRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\LoginLinkRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\MigrateToAcdcRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\OnboardingRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\PayLaterMessagingEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\PaymentRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\RefreshFeatureStatusEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\WebhookSettingsEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\SettingsRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\StylingRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Endpoint\TodosRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Handler\ConnectionListener;
use WooCommerce\PayPalCommerce\Settings\Service\AuthenticationManager;
use WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience\ActivationDetector;
use WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience\PathRepository;
use WooCommerce\PayPalCommerce\Settings\Service\ConnectionUrlGenerator;
use WooCommerce\PayPalCommerce\Settings\Service\FeaturesEligibilityService;
use WooCommerce\PayPalCommerce\Settings\Service\GatewayRedirectService;
use WooCommerce\PayPalCommerce\Settings\Service\LoadingScreenService;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigration;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\MigrationManager;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\PaymentSettingsMigration;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsTabMigration;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\StylingSettingsMigration;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\FastlaneSettingsMigration;
use WooCommerce\PayPalCommerce\Settings\Service\OnboardingUrlManager;
use WooCommerce\PayPalCommerce\Settings\Service\SellerTypeResolver;
use WooCommerce\PayPalCommerce\Settings\Service\PaymentMethodsEligibilityService;
use WooCommerce\PayPalCommerce\Settings\Service\ScriptDataHandler;
use WooCommerce\PayPalCommerce\Settings\Service\TodosEligibilityService;
use WooCommerce\PayPalCommerce\Settings\Service\TodosSortingAndFilteringService;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Settings\Service\DataSanitizer;
use WooCommerce\PayPalCommerce\Settings\Service\SettingsDataManager;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDefinition;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory\ConfigFactory;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\SaveConfig;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
use WooCommerce\PayPalCommerce\Settings\Service\InternalRestService;
use WooCommerce\PayPalCommerce\WcGateway\Helper\MerchantDetails;
return array(
    'settings.asset_getter' => static function (ContainerInterface $container): AssetGetter {
        $factory = $container->get('assets.asset_getter_factory');
        assert($factory instanceof AssetGetterFactory);
        return $factory->for_module('ppcp-settings');
    },
    'settings.settings-provider' => static function (ContainerInterface $container): SettingsProvider {
        return new SettingsProvider($container->get('settings.data.general'), $container->get('settings.data.onboarding'), $container->get('settings.data.payment'), $container->get('settings.data.settings'), $container->get('settings.data.styling'), $container->get('settings.data.fastlane'), $container->get('settings.data.paylater-messaging-settings'));
    },
    'settings.data.onboarding' => static function (ContainerInterface $container): OnboardingProfile {
        $can_use_casual_selling = $container->get('settings.casual-selling.eligible');
        $can_use_vaulting = $container->has('save-payment-methods.eligible') && $container->get('save-payment-methods.eligible');
        $can_use_card_payments = $container->has('card-fields.eligibility.check') && $container->get('card-fields.eligibility.check')();
        $can_use_digital_wallets = $container->has('applepay.eligibility.check') && $container->get('applepay.eligibility.check')() || $container->has('googlepay.eligibility.check') && $container->get('googlepay.eligibility.check')();
        $can_use_subscriptions = $container->has('wc-subscriptions.helper') && $container->get('wc-subscriptions.helper')->plugin_is_active();
        $should_skip_payment_methods = class_exists('\WC_Payments');
        $can_use_fastlane = $container->get('axo.eligibility.check');
        $can_use_pay_later = $container->get('button.helper.messages-apply');
        return new OnboardingProfile($can_use_fastlane, $can_use_casual_selling, $can_use_vaulting, $can_use_card_payments, $can_use_digital_wallets, $can_use_subscriptions, $should_skip_payment_methods, $can_use_pay_later->for_country());
    },
    'settings.data.general' => static function (ContainerInterface $container): GeneralSettings {
        return new GeneralSettings($container->get('api.shop.country'), $container->get('api.shop.currency.getter')->get(), $container->get('wcgateway.is-send-only-country'));
    },
    'settings.data.styling' => static function (ContainerInterface $container): StylingSettings {
        return new StylingSettings($container->get('settings.service.sanitizer'));
    },
    'settings.data.payment' => static function (ContainerInterface $container): PaymentSettings {
        return new PaymentSettings();
    },
    'settings.data.fastlane' => static function (): FastlaneSettings {
        return new FastlaneSettings();
    },
    'settings.data.paylater-messaging-settings' => static function (ContainerInterface $container): PayLaterMessagingSettings {
        return new PayLaterMessagingSettings($container->get('settings.service.sanitizer'));
    },
    'settings.data.settings' => static function (ContainerInterface $container): SettingsModel {
        $environment = $container->get('settings.environment');
        assert($environment instanceof Environment);
        return new SettingsModel($container->get('settings.service.sanitizer'), $environment->is_sandbox() ? $container->get('wcgateway.settings.invoice-prefix-random') : $container->get('wcgateway.settings.invoice-prefix'));
    },
    'settings.data.paylater-messaging' => static function (ContainerInterface $container): array {
        $config_factory = $container->get('paylater-configurator.factory.config');
        assert($config_factory instanceof ConfigFactory);
        $save_config = $container->get('paylater-configurator.endpoint.save-config');
        assert($save_config instanceof SaveConfig);
        $paylater_settings = $container->get('settings.data.paylater-messaging-settings');
        assert($paylater_settings instanceof PayLaterMessagingSettings);
        $pay_later_config = $config_factory->from_settings($paylater_settings);
        return array('read' => $pay_later_config, 'save' => $save_config);
    },
    'settings.connection-state' => static function (ContainerInterface $container): ConnectionState {
        $data = $container->get('settings.data.general');
        assert($data instanceof GeneralSettings);
        $is_connected = $data->is_merchant_connected();
        $environment = new Environment($data->is_sandbox_merchant());
        return new ConnectionState($is_connected, $environment);
    },
    /**
     * Returns details about the connected environment (production/sandbox).
     *
     * @deprecated Directly use 'settings.connection-state' instead of this.
     */
    'settings.environment' => static function (ContainerInterface $container): Environment {
        $state = $container->get('settings.connection-state');
        assert($state instanceof ConnectionState);
        return $state->get_environment();
    },
    /**
     * Checks if the onboarding process is completed and the merchant API can be used.
     * This service only resolves the connection status once per request.
     *
     * @deprecated Use 'settings.connection-state' instead.
     */
    'settings.flag.is-connected' => static function (ContainerInterface $container): bool {
        $state = $container->get('settings.connection-state');
        assert($state instanceof ConnectionState);
        return $state->is_connected();
    },
    /**
     * Determines whether the merchant is connected to a sandbox account.
     * This service only resolves the sandbox flag once per request.
     *
     * @deprecated Use 'settings.connection-state' instead.
     */
    'settings.flag.is-sandbox' => static function (ContainerInterface $container): bool {
        $state = $container->get('settings.connection-state');
        assert($state instanceof ConnectionState);
        return $state->is_sandbox();
    },
    'settings.rest.onboarding' => static function (ContainerInterface $container): OnboardingRestEndpoint {
        return new OnboardingRestEndpoint($container->get('settings.data.onboarding'));
    },
    'settings.rest.common' => static function (ContainerInterface $container): CommonRestEndpoint {
        return new CommonRestEndpoint($container->get('settings.data.general'), $container->get('api.endpoint.partners'));
    },
    'settings.rest.payment' => static function (ContainerInterface $container): PaymentRestEndpoint {
        return new PaymentRestEndpoint($container->get('settings.data.payment'), $container->get('settings.data.definition.methods'), $container->get('settings.data.definition.method_dependencies'));
    },
    'settings.rest.styling' => static function (ContainerInterface $container): StylingRestEndpoint {
        return new StylingRestEndpoint($container->get('settings.data.styling'), $container->get('settings.service.sanitizer'));
    },
    'settings.rest.refresh_feature_status' => static function (ContainerInterface $container): RefreshFeatureStatusEndpoint {
        return new RefreshFeatureStatusEndpoint(new Cache('ppcp-timeout'), $container->get('woocommerce.logger.woocommerce'));
    },
    'settings.rest.authentication' => static function (ContainerInterface $container): AuthenticationRestEndpoint {
        return new AuthenticationRestEndpoint($container->get('settings.service.authentication_manager'), $container->get('settings.service.data-manager'), $container->get('woocommerce.logger.woocommerce'));
    },
    'settings.rest.login_link' => static function (ContainerInterface $container): LoginLinkRestEndpoint {
        return new LoginLinkRestEndpoint($container->get('settings.service.connection-url-generator'));
    },
    'settings.rest.webhooks' => static function (ContainerInterface $container): WebhookSettingsEndpoint {
        return new WebhookSettingsEndpoint($container->get('api.endpoint.webhook'), $container->get('webhook.registrar'), $container->get('webhook.status.simulation'));
    },
    'settings.rest.pay_later_messaging' => static function (ContainerInterface $container): PayLaterMessagingEndpoint {
        return new PayLaterMessagingEndpoint($container->get('settings.data.paylater-messaging-settings'), $container->get('paylater-configurator.endpoint.save-config'));
    },
    'settings.rest.settings' => static function (ContainerInterface $container): SettingsRestEndpoint {
        return new SettingsRestEndpoint($container->get('settings.data.settings'));
    },
    'settings.rest.migrate_to_acdc' => static function (ContainerInterface $container): MigrateToAcdcRestEndpoint {
        return new MigrateToAcdcRestEndpoint($container->get('settings.data.payment'));
    },
    'settings.casual-selling.supported-countries' => static function (ContainerInterface $container): array {
        return array('AR', 'AU', 'AT', 'BE', 'BR', 'CA', 'CL', 'CN', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'GR', 'HU', 'ID', 'IE', 'IT', 'JP', 'LV', 'LI', 'LU', 'MY', 'MT', 'NL', 'NZ', 'NO', 'PH', 'PL', 'PT', 'RO', 'RU', 'SM', 'SA', 'SG', 'SK', 'SI', 'ZA', 'KR', 'ES', 'SE', 'TW', 'GB', 'US', 'VN');
    },
    'settings.casual-selling.eligible' => static function (ContainerInterface $container): bool {
        $country = $container->get('api.shop.country');
        $eligible_countries = $container->get('settings.casual-selling.supported-countries');
        return in_array($country, $eligible_countries, \true);
    },
    'settings.handler.connection-listener' => static function (ContainerInterface $container): ConnectionListener {
        return new ConnectionListener($container->get('wcgateway.is-plugin-settings-page'), $container->get('settings.service.onboarding-url-manager'), $container->get('settings.service.authentication_manager'), $container->get('http.redirector'), $container->get('woocommerce.logger.woocommerce'));
    },
    'settings.service.signup-link-cache' => static function (ContainerInterface $container): Cache {
        return new Cache('ppcp-paypal-signup-link');
    },
    'settings.service.onboarding-url-manager' => static function (ContainerInterface $container): OnboardingUrlManager {
        return new OnboardingUrlManager($container->get('settings.service.signup-link-cache'), $container->get('woocommerce.logger.woocommerce'));
    },
    'settings.service.connection-url-generator' => static function (ContainerInterface $container): ConnectionUrlGenerator {
        return new ConnectionUrlGenerator($container->get('api.env.endpoint.partner-referrals'), $container->get('api.repository.partner-referrals-data'), $container->get('settings.service.onboarding-url-manager'), $container->get('woocommerce.logger.woocommerce'));
    },
    'settings.service.authentication_manager' => static function (ContainerInterface $container): AuthenticationManager {
        return new AuthenticationManager($container->get('settings.data.general'), $container->get('api.env.paypal-host'), $container->get('api.env.endpoint.login-seller'), $container->get('api.repository.partner-referrals-data'), $container->get('settings.connection-state'), $container->get('settings.service.rest-service'), $container->get('woocommerce.logger.woocommerce'));
    },
    'settings.service.rest-service' => static function (ContainerInterface $container): InternalRestService {
        return new InternalRestService($container->get('woocommerce.logger.woocommerce'));
    },
    'settings.service.sanitizer' => static function (ContainerInterface $container): DataSanitizer {
        return new DataSanitizer();
    },
    'settings.service.data-manager' => static function (ContainerInterface $container): SettingsDataManager {
        return new SettingsDataManager($container->get('settings.data.definition.methods'), $container->get('settings.data.onboarding'), $container->get('settings.data.general'), $container->get('settings.data.settings'), $container->get('settings.data.styling'), $container->get('settings.data.payment'), $container->get('settings.data.paylater-messaging'), $container->get('settings.data.todos'));
    },
    'settings.service.script-data-handler' => static function (ContainerInterface $container): ScriptDataHandler {
        $check_override = $container->get('settings.migration.bcdc-override-check');
        assert(is_callable($check_override));
        return new ScriptDataHandler($container->get('settings.asset_getter'), $container->get('paylater-configurator.is-available'), $container->get('wcgateway.store-country'), $container->get('api.partner_merchant_id'), $container->get('wcgateway.wp-paypal-locales-map'), $container->get('api.helper.partner-attribution'), $container->get('settings.settings-provider'), $container->get('api.helpers.paymentLevelEligibility'), $check_override());
    },
    'settings.service.data-migration' => static fn(ContainerInterface $c): MigrationManager => new MigrationManager($c->get('settings.service.data-migration.general-settings'), $c->get('settings.service.data-migration.settings-tab'), $c->get('settings.service.data-migration.styling'), $c->get('settings.service.data-migration.payment-settings'), $c->get('settings.service.data-migration.fastlane'), $c->get('settings.data.onboarding'), $c->get('woocommerce.logger.woocommerce')),
    'settings.service.data-migration.settings-tab' => static fn(ContainerInterface $c): SettingsTabMigration => new SettingsTabMigration((array) get_option('woocommerce-ppcp-settings', array()), $c->get('settings.data.settings')),
    'settings.service.data-migration.styling' => static fn(ContainerInterface $c): StylingSettingsMigration => new StylingSettingsMigration((array) get_option('woocommerce-ppcp-settings', array()), $c->get('settings.data.styling')),
    'settings.service.data-migration.payment-settings' => static fn(ContainerInterface $c): PaymentSettingsMigration => new PaymentSettingsMigration((array) get_option('woocommerce-ppcp-settings', array()), $c->get('settings.data.payment'), $c->get('api.helpers.dccapplies'), $c->get('wcgateway.helper.dcc-product-status'), $c->get('wcgateway.configuration.card-configuration'), $c->get('ppcp-local-apms.payment-methods')),
    'settings.service.seller-type-resolver' => static fn(): SellerTypeResolver => new SellerTypeResolver(),
    'settings.service.data-migration.general-settings' => static fn(ContainerInterface $c): SettingsMigration => new SettingsMigration((array) get_option('woocommerce-ppcp-settings', array()), $c->get('settings.data.general'), $c->get('api.endpoint.partners'), $c->get('woocommerce.logger.woocommerce'), $c->get('settings.service.seller-type-resolver')),
    'settings.service.data-migration.fastlane' => static fn(ContainerInterface $c): FastlaneSettingsMigration => new FastlaneSettingsMigration((array) get_option('woocommerce-ppcp-settings', array()), $c->get('settings.data.fastlane')),
    'settings.rest.todos' => static function (ContainerInterface $container): TodosRestEndpoint {
        return new TodosRestEndpoint($container->get('settings.data.todos'), $container->get('settings.data.definition.todos'), $container->get('settings.rest.settings'), $container->get('settings.service.todos_sorting'));
    },
    'settings.data.todos' => static function (ContainerInterface $container): TodosModel {
        return new TodosModel();
    },
    'settings.data.definition.todos' => static function (ContainerInterface $container): TodosDefinition {
        return new TodosDefinition($container->get('settings.service.todos_eligibilities'), $container->get('settings.data.general'), $container->get('settings.data.todos'));
    },
    'settings.data.definition.methods' => static function (ContainerInterface $container): PaymentMethodsDefinition {
        return new PaymentMethodsDefinition($container->get('settings.data.payment'), $container->get('settings.data.general'), $container->get('axo.checkout-config-notice.raw'), $container->get('axo.incompatible-plugins-notice.raw'));
    },
    'settings.data.definition.method_dependencies' => static function (ContainerInterface $container): PaymentMethodsDependenciesDefinition {
        return new PaymentMethodsDependenciesDefinition();
    },
    'settings.service.pay_later_status' => static function (ContainerInterface $container): array {
        $pay_later_endpoint = $container->get('settings.rest.pay_later_messaging');
        $pay_later_settings = $pay_later_endpoint->get_details()->get_data();
        $pay_later_statuses = array('cart' => $pay_later_settings['data']['cart']['status'] === 'enabled', 'checkout' => $pay_later_settings['data']['checkout']['status'] === 'enabled', 'product' => $pay_later_settings['data']['product']['status'] === 'enabled', 'shop' => $pay_later_settings['data']['shop']['status'] === 'enabled', 'home' => $pay_later_settings['data']['home']['status'] === 'enabled', 'custom_placement' => !empty($pay_later_settings['data']['custom_placement']) && $pay_later_settings['data']['custom_placement'][0]['status'] === 'enabled');
        $is_pay_later_messaging_enabled_for_any_location = !array_filter($pay_later_statuses);
        return array('statuses' => $pay_later_statuses, 'is_enabled_for_any_location' => $is_pay_later_messaging_enabled_for_any_location);
    },
    'settings.service.button_locations' => static function (ContainerInterface $container): array {
        $styling_endpoint = $container->get('settings.rest.styling');
        $styling_data = $styling_endpoint->get_details()->get_data()['data'];
        return array('cart_enabled' => $styling_data['cart']->enabled ?? \false, 'block_checkout_enabled' => $styling_data['expressCheckout']->enabled ?? \false, 'product_enabled' => $styling_data['product']->enabled ?? \false);
    },
    'settings.service.gateways_status' => static function (ContainerInterface $container): array {
        $payment_endpoint = $container->get('settings.rest.payment');
        $settings = $payment_endpoint->get_details()->get_data();
        return array('apple_pay' => $settings['data']['ppcp-applepay']['enabled'] ?? \false, 'google_pay' => $settings['data']['ppcp-googlepay']['enabled'] ?? \false, 'axo' => $settings['data']['ppcp-axo-gateway']['enabled'] ?? \false, 'card-button' => $settings['data']['ppcp-card-button-gateway']['enabled'] ?? \false, 'pwc' => $settings['data']['ppcp-pwc']['enabled'] ?? \false);
    },
    'settings.service.merchant_capabilities' => static function (ContainerInterface $container): array {
        /**
         * Use the REST API filter to collect eligibility flags.
         *
         * TODO: We should switch to using the new `*.eligibility.check` services, which return a callback instead of a boolean.
         *       Problem with booleans is, that they are evaluated during DI service creation (plugin_loaded), and some relevant filters are not registered at that point.
         *       Overthink the capability system, it's difficult to reuse across the plugin.
         */
        $features = apply_filters('woocommerce_paypal_payments_rest_common_merchant_features', array());
        // TODO: This condition included in the `*.eligibility.check` services; it can be removed when we switch to those services.
        $general_settings = $container->get('settings.data.general');
        assert($general_settings instanceof GeneralSettings);
        return array(FeaturesDefinition::FEATURE_APPLE_PAY => ($features[FeaturesDefinition::FEATURE_APPLE_PAY]['enabled'] ?? \false) && !$general_settings->own_brand_only(), FeaturesDefinition::FEATURE_GOOGLE_PAY => ($features[FeaturesDefinition::FEATURE_GOOGLE_PAY]['enabled'] ?? \false) && !$general_settings->own_brand_only(), FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS => ($features[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS]['enabled'] ?? \false) && !$general_settings->own_brand_only(), FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO => $features[FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO]['enabled'] ?? \false, FeaturesDefinition::FEATURE_ALTERNATIVE_PAYMENT_METHODS => $features[FeaturesDefinition::FEATURE_ALTERNATIVE_PAYMENT_METHODS]['enabled'] ?? \false, FeaturesDefinition::FEATURE_PAY_LATER_MESSAGING => $features[FeaturesDefinition::FEATURE_PAY_LATER_MESSAGING]['enabled'] ?? \false, FeaturesDefinition::FEATURE_INSTALLMENTS => $features[FeaturesDefinition::FEATURE_INSTALLMENTS]['enabled'] ?? \false, FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO => $features[FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO]['enabled'] ?? \false, FeaturesDefinition::FEATURE_PAY_UPON_INVOICE => $features[FeaturesDefinition::FEATURE_PAY_UPON_INVOICE]['enabled'] ?? \false);
    },
    'settings.service.todos_eligibilities' => static function (ContainerInterface $container): TodosEligibilityService {
        $pay_later_service = $container->get('settings.service.pay_later_status');
        $pay_later_statuses = $pay_later_service['statuses'];
        $is_pay_later_messaging_enabled_for_any_location = $pay_later_service['is_enabled_for_any_location'];
        $button_locations = $container->get('settings.service.button_locations');
        $gateways = $container->get('settings.service.gateways_status');
        // TODO: This "merchant_capabilities" service is only used here. Could it be merged to make the code cleaner and less segmented?
        $capabilities = $container->get('settings.service.merchant_capabilities');
        $settings_model = $container->get('settings.data.settings');
        assert($settings_model instanceof SettingsModel);
        $messages_apply = $container->get('button.helper.messages-apply');
        assert($messages_apply instanceof MessagesApply);
        $is_working_capital_feature_flag_enabled = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- feature flags use this convention
            'woocommerce.feature-flags.woocommerce_paypal_payments.working_capital_enabled',
            \true
        );
        $is_working_capital_eligible = $container->get('settings.data.general')->get_merchant_country() === 'US' && $settings_model->get_stay_updated();
        $recaptcha_settings = get_option('woocommerce_ppcp-recaptcha_settings', array());
        $is_recaptcha_enabled = isset($recaptcha_settings['enabled']) && 'yes' === $recaptcha_settings['enabled'];
        /**
         * Initializes TodosEligibilityService with eligibility conditions for various PayPal features.
         * Each parameter determines whether a specific feature should be shown in the Things To Do list.
         *
         * Logic relies on three main factors:
         * 1. $container->get( 'x.eligible' ) - Module based eligibility check, usually whether the WooCommerce store is using a supported country/currency matrix.
         * 2. $capabilities - Whether the merchant is eligible for specific features on their PayPal account.
         * 3. $gateways, $pay_later_statuses, $button_locations - Plugin settings (enabled/disabled status).
         *
         * @param bool $is_fastlane_eligible - Show if merchant is eligible (ACDC) but hasn't enabled Fastlane gateway.
         * @param bool $is_pay_later_messaging_eligible - Show if Pay Later messaging is enabled for at least one location.
         * @param bool $is_pay_later_messaging_product_eligible - Show if Pay Later is not enabled anywhere and specifically not on product page.
         * @param bool $is_pay_later_messaging_cart_eligible - Show if Pay Later is not enabled anywhere and specifically not on cart.
         * @param bool $is_pay_later_messaging_checkout_eligible - Show if Pay Later is not enabled anywhere and specifically not on checkout.
         * @param bool $is_subscription_eligible - Show if WooCommerce Subscriptions plugin is active but merchant is not eligible for PayPal Vaulting.
         * @param bool $is_paypal_buttons_cart_eligible - Show if PayPal buttons are not enabled on cart page.
         * @param bool $is_paypal_buttons_block_checkout_eligible - Show if PayPal buttons are not enabled on blocks checkout.
         * @param bool $is_paypal_buttons_product_eligible - Show if PayPal buttons are not enabled on product page.
         * @param bool $is_apple_pay_domain_eligible - Show if merchant has Apple Pay capability on PayPal account.
         * @param bool $is_digital_wallet_eligible - Show if merchant is eligible (ACDC) but doesn't have both wallet types on PayPal.
         * @param bool $is_apple_pay_eligible - Show if merchant is eligible (ACDC) but doesn't have Apple Pay on PayPal.
         * @param bool $is_google_pay_eligible - Show if merchant is eligible (ACDC) but doesn't have Google Pay on PayPal.
         * @param bool $is_enable_apple_pay_eligible - Show if merchant has Apple Pay capability but hasn't enabled the gateway.
         * @param bool $is_enable_google_pay_eligible - Show if merchant has Google Pay capability but hasn't enabled the gateway.
         * @param bool $is_enable_installments_eligible - Show if merchant has installments capability and merchant country is MX.
         * @param bool $is_working_capital_eligible - Show if feature flag is enabled, merchant country is US and "Stay Updated" is turned On.
         * @param bool $is_pwc_eligible                  - Show if merchant has Pay with Crypto capability and store currency is USD.
         * @param bool $is_recaptcha_protection_eligible - Show if reCAPTCHA is not already enabled.
         */
        return new TodosEligibilityService(
            $container->get('axo.eligible') && $capabilities[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS] && !$gateways['axo'],
            // Enable Fastlane.
            $is_pay_later_messaging_enabled_for_any_location,
            // Enable Pay Later messaging.
            !$is_pay_later_messaging_enabled_for_any_location && !$pay_later_statuses['product'],
            // Add Pay Later messaging (Product page).
            !$is_pay_later_messaging_enabled_for_any_location && !$pay_later_statuses['cart'],
            // Add Pay Later messaging (Cart).
            !$is_pay_later_messaging_enabled_for_any_location && !$pay_later_statuses['checkout'],
            // Add Pay Later messaging (Checkout).
            $container->has('save-payment-methods.eligible') && !$container->get('save-payment-methods.eligible') && $container->has('wc-subscriptions.helper') && $container->get('wc-subscriptions.helper')->plugin_is_active(),
            // Configure a PayPal Subscription.
            !$button_locations['cart_enabled'],
            // Add PayPal buttons to cart.
            !$button_locations['block_checkout_enabled'],
            // Add PayPal buttons to block checkout.
            !$button_locations['product_enabled'],
            // Add PayPal buttons to product.
            $container->get('applepay.eligible') && $capabilities[FeaturesDefinition::FEATURE_APPLE_PAY] && !$container->get('applepay.is_validated'),
            // Register Domain for Apple Pay.
            $capabilities[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS] && !($capabilities[FeaturesDefinition::FEATURE_APPLE_PAY] && $capabilities[FeaturesDefinition::FEATURE_GOOGLE_PAY]),
            // Add digital wallets to your account.
            $container->get('applepay.eligible') && $capabilities[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS] && !$capabilities[FeaturesDefinition::FEATURE_APPLE_PAY],
            // Add Apple Pay to your account.
            $container->get('googlepay.eligible') && $capabilities[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS] && !$capabilities[FeaturesDefinition::FEATURE_GOOGLE_PAY],
            // Add Google Pay to your account.
            $container->get('applepay.eligible') && $capabilities[FeaturesDefinition::FEATURE_APPLE_PAY] && !$gateways[FeaturesDefinition::FEATURE_APPLE_PAY],
            // Enable Apple Pay.
            $container->get('googlepay.eligible') && $capabilities[FeaturesDefinition::FEATURE_GOOGLE_PAY] && !$gateways[FeaturesDefinition::FEATURE_GOOGLE_PAY],
            !$capabilities[FeaturesDefinition::FEATURE_INSTALLMENTS] && 'MX' === $container->get('settings.data.general')->get_merchant_country(),
            // Enable Installments for Mexico.
            $is_working_capital_feature_flag_enabled && $is_working_capital_eligible,
            // Enable Working Capital.
            $capabilities[FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO] && !$gateways[FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO] && $container->get('ppcp-local-apms.pwc.eligibility.check'),
            // Enable Pay with Crypto.
            $capabilities[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS] && !$capabilities[FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO] && $container->get('ppcp-local-apms.pwc.eligibility.check'),
            // Apply for Pay with Crypto.
            !$is_recaptcha_enabled
        );
    },
    'settings.rest.features' => static function (ContainerInterface $container): FeaturesRestEndpoint {
        return new FeaturesRestEndpoint($container->get('settings.data.definition.features'), $container->get('settings.rest.settings'));
    },
    'settings.data.definition.features' => static function (ContainerInterface $container): FeaturesDefinition {
        $features = apply_filters('woocommerce_paypal_payments_rest_common_merchant_features', array());
        $payment_endpoint = $container->get('settings.rest.payment');
        $settings = $payment_endpoint->get_details()->get_data();
        // Settings status.
        $gateways = array('card-button' => $settings['data']['ppcp-card-button-gateway']['enabled'] ?? \false);
        // Merchant capabilities serve to show active or inactive badge and buttons.
        $capabilities = array(FeaturesDefinition::FEATURE_APPLE_PAY => $features[FeaturesDefinition::FEATURE_APPLE_PAY]['enabled'] ?? \false, FeaturesDefinition::FEATURE_GOOGLE_PAY => $features[FeaturesDefinition::FEATURE_GOOGLE_PAY]['enabled'] ?? \false, FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS => $features[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS]['enabled'] ?? \false, FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO => $features[FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO]['enabled'] ?? \false, FeaturesDefinition::FEATURE_ALTERNATIVE_PAYMENT_METHODS => $features[FeaturesDefinition::FEATURE_ALTERNATIVE_PAYMENT_METHODS]['enabled'] ?? \false, FeaturesDefinition::FEATURE_INSTALLMENTS => $features[FeaturesDefinition::FEATURE_INSTALLMENTS]['enabled'] ?? \false, FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO => $features[FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO]['enabled'] ?? \false, FeaturesDefinition::FEATURE_PAY_LATER_MESSAGING => $features[FeaturesDefinition::FEATURE_PAY_LATER_MESSAGING]['enabled'] ?? \false, FeaturesDefinition::FEATURE_PAY_UPON_INVOICE => $features[FeaturesDefinition::FEATURE_PAY_UPON_INVOICE]['enabled'] ?? \false);
        $merchant_capabilities = array(
            FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO => $capabilities[FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO],
            // Save PayPal and Venmo eligibility.
            FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS => $capabilities[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS],
            // Advanced credit and debit cards eligibility.
            FeaturesDefinition::FEATURE_ALTERNATIVE_PAYMENT_METHODS => $capabilities[FeaturesDefinition::FEATURE_ALTERNATIVE_PAYMENT_METHODS],
            // Alternative payment methods eligibility.
            FeaturesDefinition::FEATURE_GOOGLE_PAY => $capabilities[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS] && $capabilities[FeaturesDefinition::FEATURE_GOOGLE_PAY],
            // Google Pay eligibility.
            FeaturesDefinition::FEATURE_APPLE_PAY => $capabilities[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS] && $capabilities[FeaturesDefinition::FEATURE_APPLE_PAY],
            // Apple Pay eligibility.
            FeaturesDefinition::FEATURE_PAY_LATER_MESSAGING => $capabilities[FeaturesDefinition::FEATURE_PAY_LATER_MESSAGING] && $capabilities[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS] && !$gateways['card-button'],
            // Pay Later eligibility.
            FeaturesDefinition::FEATURE_INSTALLMENTS => $capabilities[FeaturesDefinition::FEATURE_INSTALLMENTS],
            // Installments eligibility.
            FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO => $capabilities[FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO],
            // Pay with Crypto eligibility.
            FeaturesDefinition::FEATURE_PAY_UPON_INVOICE => $capabilities[FeaturesDefinition::FEATURE_PAY_UPON_INVOICE],
        );
        return new FeaturesDefinition($container->get('settings.service.features_eligibilities'), $container->get('settings.data.general'), $merchant_capabilities, $container->get('settings.data.settings'), $container->get('woocommerce.logger.woocommerce'));
    },
    'settings.service.features_eligibilities' => static function (ContainerInterface $container): FeaturesEligibilityService {
        $messages_apply = $container->get('button.helper.messages-apply');
        assert($messages_apply instanceof MessagesApply);
        $pay_later_eligible = $messages_apply->for_country();
        $apm_eligible = $container->get('ppcp-local-apms.eligibility.check');
        return new FeaturesEligibilityService(
            $container->get('save-payment-methods.eligible'),
            // Save PayPal and Venmo eligibility.
            $container->get('card-fields.eligibility.check'),
            // Advanced credit and debit cards eligibility.
            $apm_eligible,
            // Alternative payment methods eligibility.
            $container->get('googlepay.eligibility.check'),
            // Google Pay eligibility.
            $container->get('applepay.eligibility.check'),
            // Apple Pay eligibility.
            $pay_later_eligible,
            // Pay Later eligibility.
            'MX' === $container->get('api.merchant.country'),
            // Installments eligibility.
            $container->get('ppcp-local-apms.pwc.eligibility.check'),
            // Pay with Crypto eligibility.
            $container->get('ppcp-local-apms.pui.eligibility.check')
        );
    },
    'settings.service.payment_methods_eligibilities' => static function (ContainerInterface $container): PaymentMethodsEligibilityService {
        $applepay_product_status = $container->get('applepay.apple-product-status');
        assert($applepay_product_status instanceof AppleProductStatus);
        $googlepay_product_status = $container->get('googlepay.helpers.apm-product-status');
        assert($googlepay_product_status instanceof GoogleProductStatus);
        return new PaymentMethodsEligibilityService($container->get('api.merchant.country'), $container->get('ppcp-local-apms.eligibility.check'), $container->get('settings.service.merchant_capabilities'), $container->get('wcgateway.helper.dcc-product-status'), $container->get('axo.eligibility.check'), $container->get('card-fields.eligibility.check'), $applepay_product_status->is_active() && $container->get('applepay.eligible'), $googlepay_product_status->is_active() && $container->get('googlepay.eligible'));
    },
    'settings.service.todos_sorting' => static function (ContainerInterface $container): TodosSortingAndFilteringService {
        return new TodosSortingAndFilteringService($container->get('settings.data.todos'));
    },
    'settings.service.gateway-redirect' => static function (): GatewayRedirectService {
        return new GatewayRedirectService();
    },
    'settings.services.loading-screen-service' => static function (ContainerInterface $container): LoadingScreenService {
        return new LoadingScreenService();
    },
    /**
     * Returns a list of all payment gateway IDs created by this plugin.
     *
     * @returns string[] The list of all gateway IDs.
     */
    'settings.config.all-gateway-ids' => static function (): array {
        return array(PayPalGateway::ID, CardButtonGateway::ID, CreditCardGateway::ID, AxoGateway::ID, ApplePayGateway::ID, GooglePayGateway::ID, PWCGateway::ID, BancontactGateway::ID, BlikGateway::ID, EPSGateway::ID, IDealGateway::ID, MyBankGateway::ID, P24Gateway::ID, TrustlyGateway::ID, MultibancoGateway::ID, PayUponInvoiceGateway::ID, OXXO::ID);
    },
    'settings.service.branded-experience.activation-detector' => static function (): ActivationDetector {
        return new ActivationDetector();
    },
    'settings.service.branded-experience.path-repository' => static function (ContainerInterface $container): PathRepository {
        return new PathRepository($container->get('settings.service.branded-experience.activation-detector'), $container->get('settings.data.general'));
    },
    'settings.merchant-details' => static function (ContainerInterface $container): MerchantDetails {
        $data = $container->get('settings.data.general');
        assert($data instanceof GeneralSettings);
        $merchant_country = $data->get_merchant_country();
        $woo_data = $data->get_woo_settings();
        $eligibility_checks = $container->get('wcgateway.feature-eligibility.list');
        return new MerchantDetails($merchant_country, $woo_data['country'], $eligibility_checks);
    },
    'settings.migration.bcdc-override-check' => static function (): callable {
        return static fn(): bool => (bool) get_option(PaymentSettingsMigration::OPTION_NAME_BCDC_MIGRATION_OVERRIDE);
    },
);
