<?php

/**
 * The Settings module.
 *
 * @package WooCommerce\PayPalCommerce\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings;

use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PartnerAttribution;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Data\TodosModel;
use WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Enum\InstallationPathEnum;
use WooCommerce\PayPalCommerce\Settings\Handler\ConnectionListener;
use WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience\PathRepository;
use WooCommerce\PayPalCommerce\Settings\Service\GatewayRedirectService;
use WooCommerce\PayPalCommerce\Settings\Service\LoadingScreenService;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\MigrationManager;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\PaymentSettingsMigration;
use WooCommerce\PayPalCommerce\Settings\Service\PaymentMethodsEligibilityService;
use WooCommerce\PayPalCommerce\Settings\Service\ScriptDataHandler;
use WooCommerce\PayPalCommerce\Settings\Service\SellerTypeResolver;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\Settings\Service\SettingsDataManager;
use WooCommerce\PayPalCommerce\Settings\DTO\ConfigurationFlagsDTO;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\Settings\Enum\ProductChoicesEnum;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Axo\Helper\CompatibilityChecker;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use Throwable;
/**
 * Class SettingsModule
 */
class SettingsModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * {@inheritDoc}
     */
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
    /**
     * {@inheritDoc}
     */
    public function run(ContainerInterface $container): bool
    {
        // Suppress WooCommerce Settings UI elements via CSS to improve the loading experience.
        $loading_screen_service = $container->get('settings.services.loading-screen-service');
        assert($loading_screen_service instanceof LoadingScreenService);
        $loading_screen_service->register();
        add_action('init', fn() => $this->apply_branded_only_limitations($container), 1);
        add_action(
            'woocommerce_paypal_payments_gateway_migrate_on_update',
            /**
             * Auto-trigger settings migration to new UI on plugin update.
             *
             * This hook executes during plugin updates to automatically migrate existing merchants
             * from the legacy settings interface to the new settings UI. The migration runs once
             * per installation.
             *
             * Migration process includes:
             * - Cleaning up legacy UI toggle options (old/new UI preference flags)
             * - Marking onboarding as completed for existing merchants
             * - Migrating general settings, styling settings, and payment method configurations
             * - Syncing gateway states to reflect current settings
             *
             * The migration is skipped if:
             * - OPTION_NAME_MIGRATION_IS_DONE flag is already set (migration completed previously)
             */
            static function () use ($container): void {
                if (get_option(MigrationManager::OPTION_NAME_MIGRATION_IS_DONE) === '1') {
                    return;
                }
                self::pre_populate_credentials($container);
                $run_migration = static function () use ($container): void {
                    $migration_manager = $container->get('settings.service.data-migration');
                    assert($migration_manager instanceof MigrationManager);
                    $migration_manager->migrate();
                };
                // Timing is important - migration saves gateway options, which triggers WooCommerce
                // hooks that require WC to be fully initialized.
                if (did_action('woocommerce_init')) {
                    $run_migration();
                } else {
                    add_action('woocommerce_init', $run_migration);
                }
            }
        );
        add_action('admin_init', function () use ($container): void {
            if (get_option(MigrationManager::OPTION_NAME_MIGRATION_IS_DONE) !== '1') {
                $legacy_settings = (array) get_option('woocommerce-ppcp-settings', array());
                if (!empty($legacy_settings['client_id'])) {
                    self::pre_populate_credentials($container);
                    $migration_manager = $container->get('settings.service.data-migration');
                    assert($migration_manager instanceof MigrationManager);
                    $migration_manager->migrate();
                }
            }
        });
        // Resolve unknown seller type on all pages (not just admin), so frontend
        // page loads after migration also fix the seller_type saved as 'unknown'.
        add_action('init', static function () use ($container): void {
            $seller_type_resolver = $container->get('settings.service.seller-type-resolver');
            assert($seller_type_resolver instanceof SellerTypeResolver);
            $seller_type_resolver->resolve_unknown_seller_type($container->get('api.helper.failure-registry'), $container->get('settings.data.general'), $container->get('api.endpoint.partners'), $container->get('woocommerce.logger.woocommerce'));
        });
        /**
         * Override ACDC status with BCDC for eligible merchants.
         *
         * When the BCDC migration override is active, forces BCDC (Standard Card buttons)
         * classification instead of ACDC (Advanced Card processing), and suppresses ACDC
         * eligibility so the payment methods panel shows BCDC instead of ACDC.
         *
         * @param bool|null $use_bcdc Whether to use BCDC instead of ACDC.
         *
         * @return bool|null True to force BCDC classification, false/null otherwise.
         */
        add_filter('woocommerce_paypal_payments_override_acdc_status_with_bcdc', static function (?bool $use_bcdc) use ($container) {
            $check_override = $container->get('settings.migration.bcdc-override-check');
            assert(is_callable($check_override));
            if ($check_override()) {
                $use_bcdc = \true;
                add_filter('woocommerce_paypal_payments_is_acdc_active', '__return_false');
                add_filter('woocommerce_paypal_payments_is_eligible_for_card_fields', '__return_false');
            }
            return $use_bcdc;
        });
        add_action(
            'woocommerce_paypal_payments_gateway_migrate',
            /**
             * Set the BCDC override flag during plugin update, if the merchant has enabled BCDC
             * in the legacy settings.
             *
             * Corrects the BCDC flag for already-migrated merchants, as the previous migration logic
             * did not create this flag.  This ensures merchants who migrated before the override flag
             * implementation don't lose their Standard Card button functionality.
             *
             * @param false|string $previous_version The previously installed plugin version,
             *                                       or false on first installation.
             */
            static function ($previous_version) use ($container): void {
                // Only run this migration logic when updating from version 3.1.1 or older.
                // Skip on fresh installs (no previous version) since there's nothing to migrate.
                if (!$previous_version || version_compare($previous_version, '3.1.1', 'gt')) {
                    return;
                }
                try {
                    $payment_settings_migration = $container->get('settings.service.data-migration.payment-settings');
                    assert($payment_settings_migration instanceof PaymentSettingsMigration);
                    $is_bcdc_merchant = $payment_settings_migration->is_bcdc_enabled_for_acdc_merchant();
                    // Fallback: when API-based check fails (no cached DCC product status after major
                    // version upgrade), detect BCDC usage from legacy settings directly.
                    if (!$is_bcdc_merchant) {
                        $dcc_applies = $container->get('api.helpers.dccapplies');
                        if ($dcc_applies->for_country_currency()) {
                            $legacy_settings = (array) get_option('woocommerce-ppcp-settings', array());
                            $disable_funding = (array) ($legacy_settings['disable_funding'] ?? array());
                            $card_was_active = !in_array('card', $disable_funding, \true);
                            $dcc_not_enabled = empty($legacy_settings['dcc_enabled']);
                            $is_bcdc_merchant = $card_was_active && $dcc_not_enabled;
                        }
                    }
                    if (!$is_bcdc_merchant) {
                        return;
                    }
                    $payment_settings = $container->get('settings.data.payment');
                    assert($payment_settings instanceof PaymentSettings);
                    // One-time fix: Set override flag for already-migrated merchants with BCDC evidence.
                    update_option(PaymentSettingsMigration::OPTION_NAME_BCDC_MIGRATION_OVERRIDE, \true);
                    $payment_settings->toggle_method_state(CardButtonGateway::ID, \true);
                } catch (Throwable $error) {
                    // Something failed - ignore the error and assume there is no migration data.
                    return;
                }
            }
        );
        /**
         * Clean up migration-related options on settings reset.
         *
         * Removes migration state flags when merchant disconnects via "Start Over"
         * to ensure a clean state for subsequent merchant connections.
         *
         * Removed options:
         * - BCDC migration override flag (OPTION_NAME_BCDC_MIGRATION_OVERRIDE)
         */
        add_action('woocommerce_paypal_payments_reset_settings', static function (): void {
            delete_option(PaymentSettingsMigration::OPTION_NAME_BCDC_MIGRATION_OVERRIDE);
        });
        add_action(
            'admin_enqueue_scripts',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($hook_suffix) use ($container): void {
                if (!is_string($hook_suffix)) {
                    return;
                }
                $script_data_handler = $container->get('settings.service.script-data-handler');
                assert($script_data_handler instanceof ScriptDataHandler);
                $script_data_handler->localize_scripts($hook_suffix);
            }
        );
        add_action('woocommerce_paypal_payments_gateway_admin_options_wrapper', function () use ($container): void {
            global $hide_save_button;
            $hide_save_button = \true;
            $this->initialize_branded_only($container);
            $this->render_header();
            $this->render_content();
        });
        add_action('rest_api_init', static function () use ($container): void {
            $endpoints = array('onboarding' => $container->get('settings.rest.onboarding'), 'common' => $container->get('settings.rest.common'), 'connect_manual' => $container->get('settings.rest.authentication'), 'login_link' => $container->get('settings.rest.login_link'), 'webhooks' => $container->get('settings.rest.webhooks'), 'refresh_feature_status' => $container->get('settings.rest.refresh_feature_status'), 'payment' => $container->get('settings.rest.payment'), 'settings' => $container->get('settings.rest.settings'), 'styling' => $container->get('settings.rest.styling'), 'todos' => $container->get('settings.rest.todos'), 'pay_later_messaging' => $container->get('settings.rest.pay_later_messaging'), 'features' => $container->get('settings.rest.features'), 'migrate_to_acdc' => $container->get('settings.rest.migrate_to_acdc'));
            foreach ($endpoints as $endpoint) {
                assert($endpoint instanceof RestEndpoint);
                $endpoint->register_routes();
            }
        });
        add_action('admin_init', static function () use ($container): void {
            $connection_handler = $container->get('settings.handler.connection-listener');
            assert($connection_handler instanceof ConnectionListener);
            // @phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no nonce; sanitation done by the handler
            $connection_handler->process(get_current_user_id(), $_GET);
        });
        add_action('woocommerce_paypal_payments_merchant_disconnected', static function () use ($container): void {
            $logger = $container->get('woocommerce.logger.woocommerce');
            assert($logger instanceof LoggerInterface);
            $logger->info('Merchant disconnected, reset onboarding');
            // Reset onboarding profile.
            $onboarding_profile = $container->get('settings.data.onboarding');
            assert($onboarding_profile instanceof OnboardingProfile);
            $onboarding_profile->set_completed(\false);
            $onboarding_profile->set_step(0);
            $onboarding_profile->set_gateways_synced(\false);
            $onboarding_profile->set_gateways_refreshed(\false);
            $onboarding_profile->save();
            // Reset dismissed and completed on click todos.
            $todos = $container->get('settings.data.todos');
            assert($todos instanceof TodosModel);
            $todos->reset_dismissed_todos();
            $todos->reset_completed_onclick_todos();
        });
        add_action('woocommerce_paypal_payments_authenticated_merchant', static function () use ($container): void {
            $logger = $container->get('woocommerce.logger.woocommerce');
            assert($logger instanceof LoggerInterface);
            $logger->info('Merchant connected, complete onboarding and set defaults.');
            $onboarding_profile = $container->get('settings.data.onboarding');
            assert($onboarding_profile instanceof OnboardingProfile);
            $onboarding_profile->set_completed(\true);
            $onboarding_profile->save();
            // Try to apply a default configuration for the current store.
            $data_manager = $container->get('settings.service.data-manager');
            assert($data_manager instanceof SettingsDataManager);
            $general_settings = $container->get('settings.data.general');
            assert($general_settings instanceof GeneralSettings);
            $flags = new ConfigurationFlagsDTO();
            $flags->country_code = $general_settings->get_merchant_country();
            $flags->is_business_seller = $general_settings->is_business_seller();
            $flags->use_card_payments = $onboarding_profile->get_accept_card_payments();
            $flags->use_subscriptions = in_array(ProductChoicesEnum::SUBSCRIPTIONS, $onboarding_profile->get_products(), \true);
            $data_manager->set_defaults_for_new_merchant($flags);
        });
        add_filter('woocommerce_paypal_payments_payment_methods', function (array $payment_methods) use ($container): array {
            $payment_methods_eligibility_service = $container->get('settings.service.payment_methods_eligibilities');
            assert($payment_methods_eligibility_service instanceof PaymentMethodsEligibilityService);
            foreach ($payment_methods_eligibility_service->get_eligibility_checks() as $payment_method_id => $payment_method_check) {
                if (isset($payment_methods[$payment_method_id]) && call_user_func($payment_method_check) === \false) {
                    unset($payment_methods[$payment_method_id]);
                }
            }
            return $payment_methods;
        });
        add_filter(
            'woocommerce_payment_gateways',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($methods) use ($container): array {
                $is_onboarded = $container->get('api.merchant_id') !== '';
                if (!is_array($methods) || !$is_onboarded) {
                    return $methods;
                }
                $card_button_gateway = $container->get('wcgateway.card-button-gateway');
                assert($card_button_gateway instanceof CardButtonGateway);
                $googlepay_gateway = $container->get('googlepay.wc-gateway');
                assert($googlepay_gateway instanceof WC_Payment_Gateway);
                $applepay_gateway = $container->get('applepay.wc-gateway');
                assert($applepay_gateway instanceof WC_Payment_Gateway);
                $methods[] = $card_button_gateway;
                $methods[] = $googlepay_gateway;
                $methods[] = $applepay_gateway;
                if ($container->has('axo.eligible') && $container->get('axo.eligible')) {
                    $axo_gateway = $container->get('axo.gateway');
                    assert($axo_gateway instanceof WC_Payment_Gateway);
                    $methods[] = $axo_gateway;
                }
                // Remove gateways where the merchant is not eligible.
                $eligibility_service = $container->get('settings.service.payment_methods_eligibilities');
                $eligibility_checks = $eligibility_service->get_eligibility_checks();
                $methods = array_filter($methods, static function ($gateway) use ($eligibility_checks) {
                    $id = $gateway instanceof WC_Payment_Gateway ? $gateway->id : '';
                    return !isset($eligibility_checks[$id]) || $eligibility_checks[$id]();
                });
                return $methods;
            },
            99
        );
        /**
         * Filters the available payment gateways in the WooCommerce admin settings.
         *
         * Ensures that only enabled PayPal payment gateways are displayed.
         *
         * @hook     woocommerce_admin_field_payment_gateways
         * @priority 5 Allows modifying the registered gateways before they are displayed.
         */
        add_action('woocommerce_admin_field_payment_gateways', function () use ($container): void {
            $all_gateway_ids = $container->get('settings.config.all-gateway-ids');
            $payment_gateways = WC()->payment_gateways->payment_gateways;
            foreach ($payment_gateways as $index => $payment_gateway) {
                $payment_gateway_id = $payment_gateway->id;
                if (!in_array($payment_gateway_id, $all_gateway_ids, \true) || $payment_gateway_id === PayPalGateway::ID || $this->is_gateway_enabled($payment_gateway_id)) {
                    continue;
                }
                unset(WC()->payment_gateways->payment_gateways[$index]);
            }
            $card_config = $container->get('wcgateway.configuration.card-configuration');
            $store_country = $container->get('api.merchant.country');
            if ($card_config->use_acdc() && $store_country !== 'MX') {
                foreach (WC()->payment_gateways->payment_gateways as $index => $gateway) {
                    if ($gateway->id === CardButtonGateway::ID) {
                        unset(WC()->payment_gateways->payment_gateways[$index]);
                    }
                }
            }
        }, 5);
        // Remove the Fastlane gateway if the customer is logged in, ensuring that we don't interfere with the Fastlane gateway status in the settings UI.
        add_filter(
            'woocommerce_available_payment_gateways',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            static function ($methods) {
                if (!is_array($methods)) {
                    return $methods;
                }
                if (is_user_logged_in() && !is_admin()) {
                    foreach ($methods as $key => $method) {
                        if ($method instanceof WC_Payment_Gateway && $method->id === 'ppcp-axo-gateway') {
                            unset($methods[$key]);
                            break;
                        }
                    }
                }
                return $methods;
            }
        );
        add_filter('woocommerce_paypal_payments_gateway_title', function (string $title, WC_Payment_Gateway $gateway) {
            return $gateway->get_option('title', $title);
        }, 10, 2);
        add_filter('woocommerce_paypal_payments_gateway_description', function (string $description, WC_Payment_Gateway $gateway) {
            return $gateway->get_option('description', $description);
        }, 10, 2);
        add_filter('woocommerce_paypal_payments_paypal_gateway_icon', function (string $icon_url) use ($container) {
            $payment_settings = $container->get('settings.data.payment');
            assert($payment_settings instanceof PaymentSettings);
            // If "Show logo" is disabled, return an empty string to hide the icon.
            return $payment_settings->get_paypal_show_logo() ? $icon_url : '';
        });
        add_filter('woocommerce_paypal_payments_card_button_gateway_should_register_gateway', '__return_true');
        add_filter('woocommerce_paypal_payments_credit_card_gateway_form_fields', function (array $form_fields) {
            $form_fields['enabled'] = array('title' => __('Enable/Disable', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'desc_tip' => \true, 'description' => __('Once enabled, the Credit Card option will show up in the checkout.', 'woocommerce-paypal-payments'), 'label' => __('Enable Advanced Card Processing', 'woocommerce-paypal-payments'), 'default' => 'no');
            return $form_fields;
        });
        add_filter('woocommerce_paypal_payments_credit_card_gateway_should_update_enabled', '__return_false');
        add_filter('woocommerce_paypal_payments_credit_card_gateway_title', function (string $title, WC_Payment_Gateway $gateway) {
            return $gateway->get_option('title', $title);
        }, 10, 2);
        add_filter('woocommerce_paypal_payments_credit_card_gateway_description', function (string $description, WC_Payment_Gateway $gateway) {
            return $gateway->get_option('description', $description);
        }, 10, 2);
        if (is_admin()) {
            add_filter('woocommerce_paypal_payments_axo_gateway_should_update_enabled', '__return_false');
            add_filter('woocommerce_paypal_payments_axo_gateway_title', function (string $title, WC_Payment_Gateway $gateway) {
                return $gateway->get_option('title', $title);
            }, 10, 2);
            add_filter('woocommerce_paypal_payments_axo_gateway_description', function (string $description, WC_Payment_Gateway $gateway) {
                return $gateway->get_option('description', $description);
            }, 10, 2);
        }
        // Enable Fastlane after onboarding if the store is compatible.
        add_action('woocommerce_paypal_payments_toggle_payment_gateways', function (PaymentSettings $payment_methods, ConfigurationFlagsDTO $flags) use ($container) {
            if ($flags->is_business_seller && $flags->use_card_payments) {
                $compatibility_checker = $container->get('axo.helpers.compatibility-checker');
                assert($compatibility_checker instanceof CompatibilityChecker);
                if ($compatibility_checker->is_fastlane_compatible()) {
                    $payment_methods->toggle_method_state(AxoGateway::ID, \true);
                }
            }
            $general_settings = $container->get('settings.data.general');
            assert($general_settings instanceof GeneralSettings);
            $merchant_data = $general_settings->get_merchant_data();
            $merchant_country = $merchant_data->merchant_country;
        }, 10, 2);
        // Enable APMs after onboarding if the country is compatible.
        add_action('woocommerce_paypal_payments_toggle_payment_gateways_apms', function (PaymentSettings $payment_methods, array $methods_apm, ConfigurationFlagsDTO $flags) use ($container) {
            $general_settings = $container->get('settings.data.general');
            assert($general_settings instanceof GeneralSettings);
            $merchant_data = $general_settings->get_merchant_data();
            $merchant_country = $merchant_data->merchant_country;
            // Enable all APM methods.
            foreach ($methods_apm as $method) {
                if ($flags->use_card_payments === \false) {
                    $payment_methods->toggle_method_state($method['id'], $flags->use_card_payments);
                    continue;
                }
                // Skip PayUponInvoice if merchant is not in Germany.
                if (PayUponInvoiceGateway::ID === $method['id'] && 'DE' !== $merchant_country) {
                    continue;
                }
                // For OXXO: enable ONLY if merchant is in Mexico.
                if (OXXO::ID === $method['id']) {
                    if ('MX' === $merchant_country) {
                        $payment_methods->toggle_method_state($method['id'], \true);
                    }
                    continue;
                }
                $payment_methods->toggle_method_state($method['id'], \true);
            }
        }, 10, 3);
        // Toggle payment gateways after onboarding based on flags.
        add_action('woocommerce_paypal_payments_sync_gateways', static function () use ($container) {
            $settings_data_manager = $container->get('settings.service.data-manager');
            assert($settings_data_manager instanceof SettingsDataManager);
            $settings_data_manager->sync_gateway_settings();
        });
        // Redirect payment method links in the WC Payment Gateway to the new UI Payment Methods tab.
        $gateway_redirect_service = $container->get('settings.service.gateway-redirect');
        assert($gateway_redirect_service instanceof GatewayRedirectService);
        $gateway_redirect_service->register();
        // Do not render Pay Later messaging if the "Save PayPal and Venmo" setting is enabled.
        add_filter('woocommerce_paypal_payments_should_render_pay_later_messaging', static function () use ($container): bool {
            $settings_model = $container->get('settings.data.settings');
            assert($settings_model instanceof SettingsModel);
            return !$settings_model->get_save_paypal_and_venmo();
        });
        // Migration code to update BN code of merchants that are on whitelabel mode (own_brand_only false) to use the whitelabel BN code (direct).
        add_action('woocommerce_paypal_payments_gateway_migrate_on_update', static function () use ($container) {
            $general_settings = $container->get('settings.data.general');
            assert($general_settings instanceof GeneralSettings);
            $partner_attribution = $container->get('api.helper.partner-attribution');
            assert($partner_attribution instanceof PartnerAttribution);
            $own_brand_only = $general_settings->own_brand_only();
            $installation_path = $general_settings->get_installation_path();
            if (!$own_brand_only && $installation_path !== InstallationPathEnum::DIRECT) {
                $partner_attribution->initialize_bn_code(InstallationPathEnum::DIRECT, \true);
            }
        });
        /**
         * Implement the mutually exclusive BCDC or ACDC rule:
         * If the current merchant is _not BCDC eligible_, we disable the "card" funding source.
         * This effectively hides the black Standard Card button from the express payment block
         * and the PayPal smart button stack in classic checkout.
         */
        add_filter('woocommerce_paypal_payments_sdk_disabled_funding_hook', static function (array $disable_funding) use ($container) {
            // Already disabled, no correction needed.
            if (in_array('card', $disable_funding, \true)) {
                return $disable_funding;
            }
            $dcc_configuration = $container->get('wcgateway.configuration.card-configuration');
            assert($dcc_configuration instanceof CardPaymentsConfiguration);
            if (!$dcc_configuration->is_bcdc_enabled()) {
                $disable_funding[] = 'card';
            }
            return $disable_funding;
        });
        add_action(
            'woocommerce_paypal_payments_gateway_migrate',
            /**
             * Retroactive fix for CardButtonGateway not enabled after migration.
             *
             * In versions up to 3.4.1, the migration only enabled CardButtonGateway for
             * ACDC-eligible merchants using BCDC. Non-ACDC merchants who had the card
             * funding source active (the default) were missed, causing the card button
             * to disappear after upgrade.
             *
             * @param false|string $previous_version The previously installed plugin version,
             *                                       or false on first installation.
             */
            static function ($previous_version) use ($container): void {
                if ($previous_version && version_compare($previous_version, '3.4.1', 'gt')) {
                    return;
                }
                if (get_option(MigrationManager::OPTION_NAME_MIGRATION_IS_DONE) !== '1') {
                    return;
                }
                $payment_settings = $container->get('settings.data.payment');
                assert($payment_settings instanceof PaymentSettings);
                if ($payment_settings->is_method_enabled(CardButtonGateway::ID)) {
                    return;
                }
                $legacy_settings = (array) get_option('woocommerce-ppcp-settings', array());
                $disable_funding = (array) ($legacy_settings['disable_funding'] ?? array());
                if (!in_array('card', $disable_funding, \true)) {
                    $payment_settings->toggle_method_state(CardButtonGateway::ID, \true);
                    $payment_settings->save();
                }
            }
        );
        add_action(
            'woocommerce_paypal_payments_gateway_migrate',
            /**
             * Retroactive fix for local APMs not enabled after migration when
             * allow_local_apm_gateways was false.
             *
             * In versions up to 3.4.1, the migration only enabled local APMs when
             * allow_local_apm_gateways was truthy. When it was false, APMs were shown
             * inside the PayPal button, not as separate gateways. The new UI always
             * treats APMs as separate gateways, so skipping them left them invisible.
             *
             * @param false|string $previous_version The previously installed plugin version,
             *                                       or false on first installation.
             */
            static function ($previous_version) use ($container): void {
                if ($previous_version && version_compare($previous_version, '3.4.1', 'gt')) {
                    return;
                }
                if (get_option(MigrationManager::OPTION_NAME_MIGRATION_IS_DONE) !== '1') {
                    return;
                }
                $legacy_settings = (array) get_option('woocommerce-ppcp-settings', array());
                // Only fix merchants who had allow_local_apm_gateways falsy.
                // Truthy merchants were migrated correctly.
                if (!empty($legacy_settings['allow_local_apm_gateways'])) {
                    return;
                }
                $payment_settings = $container->get('settings.data.payment');
                assert($payment_settings instanceof PaymentSettings);
                $local_apms = $container->get('ppcp-local-apms.payment-methods');
                $disable_funding = (array) ($legacy_settings['disable_funding'] ?? array());
                $changed = \false;
                foreach ($local_apms as $apm) {
                    if (!in_array($apm['id'], $disable_funding, \true) && !$payment_settings->is_method_enabled($apm['id'])) {
                        $payment_settings->toggle_method_state($apm['id'], \true);
                        $changed = \true;
                    }
                }
                if ($changed) {
                    $payment_settings->save();
                }
            }
        );
        add_action(
            'woocommerce_paypal_payments_gateway_migrate',
            /**
             * Migrates payment level processing setting during plugin update.
             *
             * For merchants updating from version 3.3.2 or older, disables Level 2/3
             * processing if they previously opted out of automatic updates (stay_updated=false).
             * Merchants who opted into updates inherit the default enabled state.
             *
             * @param false|string $previous_version The previously installed plugin version,
             *                                       or false on first installation.
             */
            static function ($previous_version) use ($container): void {
                // Only run this migration logic when updating from version 3.3.2 or older.
                if ($previous_version && version_compare($previous_version, '3.3.2', 'gt')) {
                    return;
                }
                try {
                    $settings_model = $container->get('settings.data.settings');
                    assert($settings_model instanceof SettingsModel);
                    if (!$settings_model->get_stay_updated()) {
                        $settings_model->set_payment_level_processing(\false);
                        $settings_model->save();
                    }
                } catch (Throwable $error) {
                    // Something failed - ignore the error and assume there is no migration data.
                    return;
                }
            }
        );
        /**
         * Disable ACDC gateway for merchants not eligible for ACDC
         * after onboarding is completed.
         */
        add_action('woocommerce_paypal_payments_toggle_payment_gateways', function (PaymentSettings $payment_methods, ConfigurationFlagsDTO $flags) use ($container) {
            $dcc_configuration = $container->get('wcgateway.configuration.card-configuration');
            assert($dcc_configuration instanceof CardPaymentsConfiguration);
            if ($flags->is_business_seller && $flags->use_card_payments && !$dcc_configuration->use_acdc()) {
                $payment_methods->toggle_method_state(CreditCardGateway::ID, \false);
            }
        }, 10, 2);
        /**
         * Disable Apple Pay/Google Pay gateways for merchants not eligible
         * after onboarding is completed.
         */
        add_action('woocommerce_paypal_payments_toggle_payment_gateways', function (PaymentSettings $payment_methods, ConfigurationFlagsDTO $flags) use ($container) {
            if (!$flags->is_business_seller || !$flags->use_digital_wallets) {
                return;
            }
            $applepay_product_status = $container->get('applepay.apple-product-status');
            $applepay_eligibility = $container->get('applepay.eligibility.check');
            $apple_pay_available = $applepay_product_status->is_active() && $applepay_eligibility();
            if (!$apple_pay_available) {
                $payment_methods->toggle_method_state(ApplePayGateway::ID, \false);
            }
            $googlepay_product_status = $container->get('googlepay.helpers.apm-product-status');
            $googlepay_eligibility = $container->get('googlepay.eligibility.check');
            $google_pay_available = $googlepay_product_status->is_active() && $googlepay_eligibility();
            if (!$google_pay_available) {
                $payment_methods->toggle_method_state(GooglePayGateway::ID, \false);
            }
        }, 10, 2);
        return \true;
    }
    /**
     * Pre-populates GeneralSettings with legacy credentials before migration
     * DI services are resolved.
     *
     * DI services like api.key, api.secret, api.merchant_id are resolved from
     * SettingsProvider → GeneralSettings → woocommerce-ppcp-data-common.
     * This option does not exist before migration, so these DI services resolve
     * to empty strings. Pre-populating ensures PartnersEndpoint has working
     * credentials during migration so seller_status() API call succeeds.
     *
     * @param ContainerInterface $container The DI container.
     */
    private static function pre_populate_credentials(ContainerInterface $container): void
    {
        $general = $container->get('settings.data.general');
        assert($general instanceof GeneralSettings);
        if ($general->is_merchant_connected()) {
            return;
        }
        $legacy = (array) get_option('woocommerce-ppcp-settings', array());
        if (empty($legacy['client_id']) || empty($legacy['merchant_id'])) {
            return;
        }
        $general->set_merchant_data(new MerchantConnectionDTO(!empty($legacy['sandbox_on']), $legacy['client_id'], $legacy['client_secret'] ?? '', $legacy['merchant_id'], $legacy['merchant_email'] ?? '', '', SellerTypeEnum::UNKNOWN));
    }
    /**
     * Checks the branded-only state and applies relevant site-wide feature limitations, if needed.
     *
     * @param ContainerInterface $container The DI container provider.
     *
     * @return void
     */
    protected function apply_branded_only_limitations(ContainerInterface $container): void
    {
        $settings = $container->get('settings.data.general');
        assert($settings instanceof GeneralSettings);
        if (!$settings->own_brand_only()) {
            return;
        }
        /**
         * Ensure BCDC remains functional in branded-only mode.
         *
         * In branded-only mode, white-label payment methods (ACDC, Apple Pay, Google Pay)
         * are disabled, but the PayPal-branded card button (BCDC) should remain functional.
         *
         * BCDC requires the 'card' funding source to be enabled. This filter prevents 'card'
         * from being added to the disabled funding sources list on checkout pages, ensuring
         * the BCDC button remains clickable and functional for merchants using branded-only mode.
         */
        add_filter('woocommerce_paypal_payments_sdk_disabled_funding_hook', static function (array $disable_funding, array $flags) use ($container) {
            $allowed_context = array('checkout-block', 'checkout');
            if (!in_array($flags['context'], $allowed_context, \true)) {
                return $disable_funding;
            }
            $payment_settings = $container->get('settings.data.payment');
            assert($payment_settings instanceof PaymentSettings);
            if (!$payment_settings->is_method_enabled(CardButtonGateway::ID)) {
                return $disable_funding;
            }
            return array_filter($disable_funding, static fn(string $funding_source) => $funding_source !== 'card');
        }, 10, 2);
        /**
         * Prevent white-label payment methods from being enabled during onboarding.
         *
         * During the onboarding flow, toggle_payment_gateways() enables ACDC, Apple Pay,
         * and Google Pay for business sellers. In branded-only mode, these white-label
         * methods should never be enabled.
         *
         * This hook runs during the 'woocommerce_paypal_payments_toggle_payment_gateways_apms'
         * action, immediately disabling these methods before payment settings are saved.
         * This prevents them from being enabled even temporarily during onboarding.
         *
         * Without this hook, white-label methods would be enabled during onboarding and
         * then disabled afterward, creating an inconsistent state during the upgrade process.
         */
        add_action('woocommerce_paypal_payments_toggle_payment_gateways_apms', static function (PaymentSettings $payment_settings): void {
            $payment_settings->toggle_method_state(CreditCardGateway::ID, \false);
            $payment_settings->toggle_method_state(ApplePayGateway::ID, \false);
            $payment_settings->toggle_method_state(GooglePayGateway::ID, \false);
        });
        $payment_settings = $container->get('settings.data.payment');
        assert($payment_settings instanceof PaymentSettings);
        $gateway_name = CardButtonGateway::ID;
        $gateway_settings = get_option("woocommerce_{$gateway_name}_settings", array());
        $gateway_enabled = $gateway_settings['enabled'] ?? \false;
        if ($payment_settings->is_method_enabled(CreditCardGateway::ID)) {
            $payment_settings->toggle_method_state(CreditCardGateway::ID, \false);
            if ($gateway_enabled === 'yes') {
                $payment_settings->toggle_method_state(CardButtonGateway::ID, \true);
            }
            $payment_settings->save();
        }
        if ($payment_settings->is_method_enabled(ApplePayGateway::ID)) {
            $payment_settings->toggle_method_state(ApplePayGateway::ID, \false);
            $payment_settings->save();
        }
        if ($payment_settings->is_method_enabled(GooglePayGateway::ID)) {
            $payment_settings->toggle_method_state(GooglePayGateway::ID, \false);
            $payment_settings->save();
        }
        /**
         * In branded-only mode, we completely disable all white label features.
         */
        add_filter('woocommerce_paypal_payments_is_eligible_for_applepay', '__return_false');
        add_filter('woocommerce_paypal_payments_is_eligible_for_googlepay', '__return_false');
        add_filter('woocommerce_paypal_payments_is_eligible_for_axo', '__return_false');
        add_filter('woocommerce_paypal_payments_is_eligible_for_save_payment_methods', '__return_false');
        add_filter('woocommerce_paypal_payments_is_eligible_for_card_fields', '__return_false');
        add_filter('woocommerce_paypal_payments_is_acdc_active', '__return_false');
    }
    /**
     * Initializes the branded-only flags if they are not set.
     *
     * This method can be called multiple times:
     * The flags are only initialized once but does not change afterward.
     *
     * Also, this check has no impact on performance for two reasons:
     * 1. The GeneralSettings class is already initialized and will short-circuit
     *    the check if the settings are already initialized.
     * 2. The settings UI is a React app, this method only runs when the React app
     *    is injected to the DOM, and not while the UI is used.
     *
     * @param ContainerInterface $container The DI container provider.
     *
     * @return void
     */
    protected function initialize_branded_only(ContainerInterface $container): void
    {
        $path_repository = $container->get('settings.service.branded-experience.path-repository');
        assert($path_repository instanceof PathRepository);
        $partner_attribution = $container->get('api.helper.partner-attribution');
        assert($partner_attribution instanceof PartnerAttribution);
        $general_settings = $container->get('settings.data.general');
        assert($general_settings instanceof GeneralSettings);
        $path_repository->persist();
        $partner_attribution->initialize_bn_code($general_settings->get_installation_path());
    }
    /**
     * Outputs the settings page header (title and back-link).
     *
     * @return void
     */
    protected function render_header(): void
    {
        echo '<h2>' . esc_html__('PayPal', 'woocommerce-paypal-payments');
        wc_back_link(__('Return to payments', 'woocommerce-paypal-payments'), admin_url('admin.php?page=wc-settings&tab=checkout'));
        echo '</h2>';
    }
    /**
     * Renders the container for the React app.
     *
     * @return void
     */
    protected function render_content(): void
    {
        echo '<div id="ppcp-settings-container"></div>';
    }
    /**
     * Checks if the payment gateway with the given name is enabled.
     *
     * @param string $gateway_name The gateway name.
     *
     * @return bool True if the payment gateway with the given name is enabled, otherwise false.
     */
    protected function is_gateway_enabled(string $gateway_name): bool
    {
        $gateway_settings = get_option("woocommerce_{$gateway_name}_settings", array());
        $gateway_enabled = $gateway_settings['enabled'] ?? \false;
        return $gateway_enabled === 'yes';
    }
}
