<?php

/**
 * The Gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use Throwable;
use WC_Order;
use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ReferenceTransactionStatus;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\LocalApmProductStatus;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Admin\FeesRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use WooCommerce\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use WooCommerce\PayPalCommerce\WcGateway\Admin\RenderAuthorizeAction;
use WooCommerce\PayPalCommerce\WcGateway\Assets\FraudNetAssets;
use WooCommerce\PayPalCommerce\WcGateway\Assets\SettingsPageAssets;
use WooCommerce\PayPalCommerce\WcGateway\Assets\VoidButtonAssets;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\CheckoutPayPalAddressPreset;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\RefreshFeatureStatusEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ShippingCallbackEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\VoidOrderEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\GatewayRepository;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXOGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\InstallmentsProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PWCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\GatewayWithoutPayPalAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\SendOnlyCountryNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\UnsupportedCurrencyAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\CreditCardOrderInfoHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Settings\HeaderRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SectionsRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsListener;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteRegistrar;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Registrar\TaskRegistrarInterface;
/**
 * Class WcGatewayModule
 */
class WCGatewayModule implements ServiceModule, ExtendingModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    use CreditCardOrderInfoHandlingTrait;
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
    public function extensions(): array
    {
        return require __DIR__ . '/../extensions.php';
    }
    /**
     * {@inheritDoc}
     */
    public function run(ContainerInterface $c): bool
    {
        $this->register_payment_gateways($c);
        $this->register_order_functionality($c);
        $this->register_contact_handlers();
        $this->register_columns($c);
        $this->register_checkout_paypal_address_preset($c);
        $this->register_wc_tasks($c);
        $this->register_woo_inbox_notes($c);
        $this->register_void_button($c);
        if (!$c->get('wcgateway.settings.admin-settings-enabled')) {
            add_action('woocommerce_sections_checkout', function () use ($c) {
                $header_renderer = $c->get('wcgateway.settings.header-renderer');
                assert($header_renderer instanceof HeaderRenderer);
                $section_renderer = $c->get('wcgateway.settings.sections-renderer');
                assert($section_renderer instanceof SectionsRenderer);
                // phpcs:ignore WordPress.Security.EscapeOutput
                echo $header_renderer->render() . $section_renderer->render();
            }, 20);
        }
        add_action('woocommerce_paypal_payments_order_captured', function (WC_Order $wc_order, Capture $capture) use ($c) {
            $breakdown = $capture->seller_receivable_breakdown();
            if ($breakdown) {
                $wc_order->update_meta_data(PayPalGateway::FEES_META_KEY, $breakdown->to_array());
                $paypal_fee = $breakdown->paypal_fee();
                if ($paypal_fee) {
                    $wc_order->update_meta_data('PayPal Transaction Fee', (string) $paypal_fee->value());
                }
                $wc_order->save_meta_data();
            }
            $order = $c->get('session.handler')->order();
            if (!$order) {
                return;
            }
            $fraud = $capture->fraud_processor_response();
            if ($fraud) {
                $this->handle_fraud($fraud, $order, $wc_order);
            }
            $this->handle_three_d_secure($order, $wc_order);
        }, 10, 2);
        add_action('woocommerce_paypal_payments_order_authorized', function (WC_Order $wc_order, Authorization $authorization) use ($c) {
            $order = $c->get('session.handler')->order();
            if (!$order) {
                return;
            }
            $fraud = $authorization->fraud_processor_response();
            if ($fraud) {
                $this->handle_fraud($fraud, $order, $wc_order);
            }
            $this->handle_three_d_secure($order, $wc_order);
        }, 10, 2);
        $fees_renderer = $c->get('wcgateway.admin.fees-renderer');
        assert($fees_renderer instanceof FeesRenderer);
        add_action('woocommerce_admin_order_totals_after_total', function (int $order_id) use ($fees_renderer) {
            $wc_order = wc_get_order($order_id);
            if (!$wc_order instanceof WC_Order) {
                return;
            }
            /**
             * The filter can be used to remove the rows with PayPal fees in WC orders.
             */
            if (!apply_filters('woocommerce_paypal_payments_show_fees_on_order_admin_page', \true, $wc_order)) {
                return;
            }
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $fees_renderer->render($wc_order);
        });
        add_action('admin_enqueue_scripts', function () use ($c) {
            if (!is_admin() || wp_doing_ajax()) {
                return;
            }
            if (!$c->has('wcgateway.asset_getter')) {
                return;
            }
            $settings_status = $c->get('wcgateway.settings.status');
            assert($settings_status instanceof SettingsStatus);
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof Settings);
            $dcc_configuration = $c->get('wcgateway.configuration.card-configuration');
            assert($dcc_configuration instanceof CardPaymentsConfiguration);
            $assets = new SettingsPageAssets($c->get('wcgateway.asset_getter'), $c->get('ppcp.asset-version'), $c->get('wc-subscriptions.helper'), $c->get('button.client_id_for_admin'), $c->get('api.shop.currency.getter'), $c->get('api.shop.country'), $c->get('settings.environment'), $settings_status->is_pay_later_button_enabled(), $settings->has('disable_funding') ? $settings->get('disable_funding') : array(), $c->get('wcgateway.settings.funding-sources'), $c->get('wcgateway.is-ppcp-settings-page'), $dcc_configuration->is_enabled(), $c->get('api.reference-transaction-status'), $c->get('wcgateway.is-ppcp-settings-payment-methods-page'));
            $assets->register_assets();
        });
        add_filter(Repository::NOTICES_FILTER, static function ($notices) use ($c): array {
            $notice = $c->get('wcgateway.notice.connect');
            assert($notice instanceof ConnectAdminNotice);
            $connect_message = $notice->connect_message();
            if ($connect_message) {
                $notices[] = $connect_message;
            }
            $notice = $c->get('wcgateway.notice.currency-unsupported');
            assert($notice instanceof UnsupportedCurrencyAdminNotice);
            $unsupported_currency_message = $notice->unsupported_currency_message();
            if ($unsupported_currency_message) {
                $notices[] = $unsupported_currency_message;
            }
            foreach (array($c->get('wcgateway.notice.dcc-without-paypal'), $c->get('wcgateway.notice.card-button-without-paypal')) as $gateway_without_paypal_notice) {
                assert($gateway_without_paypal_notice instanceof GatewayWithoutPayPalAdminNotice);
                $message = $gateway_without_paypal_notice->message();
                if ($message) {
                    $notices[] = $message;
                }
            }
            $send_only_country_notice = $c->get('wcgateway.notice.send-only-country');
            assert($send_only_country_notice instanceof SendOnlyCountryNotice);
            $message = $send_only_country_notice->message();
            if ($message) {
                $notices[] = $message;
            }
            $authorize_order_action = $c->get('wcgateway.notice.authorize-order-action');
            $authorized_message = $authorize_order_action->message();
            if ($authorized_message) {
                $notices[] = $authorized_message;
            }
            $settings_renderer = $c->get('wcgateway.settings.render');
            assert($settings_renderer instanceof SettingsRenderer);
            $messages = $settings_renderer->messages();
            $notices = array_merge($notices, $messages);
            return $notices;
        });
        add_action('woocommerce_paypal_commerce_gateway_deactivate', static function () use ($c) {
            delete_option(Settings::KEY);
            delete_option('woocommerce_' . PayPalGateway::ID . '_settings');
            delete_option('woocommerce_' . CreditCardGateway::ID . '_settings');
        });
        add_action('wc_ajax_' . ReturnUrlEndpoint::ENDPOINT, static function () use ($c) {
            $endpoint = $c->get('wcgateway.endpoint.return-url');
            /**
             * The Endpoint.
             *
             * @var ReturnUrlEndpoint $endpoint
             */
            $endpoint->handle_request();
        });
        add_action('wc_ajax_' . RefreshFeatureStatusEndpoint::ENDPOINT, static function () use ($c) {
            $endpoint = $c->get('wcgateway.endpoint.refresh-feature-status');
            assert($endpoint instanceof RefreshFeatureStatusEndpoint);
            $endpoint->handle_request();
        });
        add_action('woocommerce_paypal_payments_gateway_migrate', static function () use ($c) {
            delete_option('ppcp-request-ids');
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof Settings);
            try {
                if ($settings->has('3d_secure_contingency') && $settings->get('3d_secure_contingency') === '3D_SECURE') {
                    $settings->set('3d_secure_contingency', 'SCA_ALWAYS');
                    $settings->persist();
                }
            } catch (NotFoundException $exception) {
                return;
            }
        });
        add_action('woocommerce_paypal_payments_gateway_migrate_on_update', static function () use ($c) {
            $dcc_status_cache = $c->get('dcc.status-cache');
            assert($dcc_status_cache instanceof Cache);
            $pui_status_cache = $c->get('pui.status-cache');
            assert($pui_status_cache instanceof Cache);
            $dcc_status_cache->delete(DCCProductStatus::DCC_STATUS_CACHE_KEY);
            $pui_status_cache->delete(PayUponInvoiceProductStatus::PUI_STATUS_CACHE_KEY);
            $settings = $c->get('wcgateway.settings');
            $settings->set('products_dcc_enabled', \false);
            $settings->set('products_pui_enabled', \false);
            $settings->persist();
            do_action('woocommerce_paypal_payments_clear_apm_product_status', $settings);
            // Update caches.
            $dcc_status = $c->get('wcgateway.helper.dcc-product-status');
            assert($dcc_status instanceof DCCProductStatus);
            $dcc_status->is_active();
            $pui_status = $c->get('wcgateway.pay-upon-invoice-product-status');
            assert($pui_status instanceof PayUponInvoiceProductStatus);
            $pui_status->is_active();
        }, 20);
        add_action('wp_loaded', function () use ($c) {
            if ('DE' === $c->get('api.shop.country')) {
                $c->get('wcgateway.pay-upon-invoice')->init();
            }
            $c->get('wcgateway.oxxo')->init();
            $fraudnet_assets = $c->get('wcgateway.fraudnet-assets');
            assert($fraudnet_assets instanceof FraudNetAssets);
            $fraudnet_assets->register_assets();
        });
        add_action('woocommerce_paypal_payments_check_pui_payment_captured', function (int $wc_order_id, string $order_id) use ($c) {
            $order_endpoint = $c->get('api.endpoint.order');
            $logger = $c->get('woocommerce.logger.woocommerce');
            $order = $order_endpoint->order($order_id);
            $order_status = $order->status();
            $logger->info("Checking payment captured webhook for WC order #{$wc_order_id}, PayPal order status: " . $order_status->name());
            $wc_order = wc_get_order($wc_order_id);
            if (!is_a($wc_order, WC_Order::class) || $wc_order->get_status() !== 'on-hold') {
                return;
            }
            if ($order_status->name() !== OrderStatus::COMPLETED) {
                $message = __('Could not process WC order because PAYMENT.CAPTURE.COMPLETED webhook not received.', 'woocommerce-paypal-payments');
                $logger->error($message);
                $wc_order->update_status('failed', $message);
            }
        }, 10, 2);
        add_action('woocommerce_order_status_changed', static function (int $order_id, string $from, string $to) use ($c) {
            $wc_order = wc_get_order($order_id);
            if (!$wc_order instanceof WC_Order) {
                return;
            }
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof ContainerInterface);
            if (!$settings->has('capture_on_status_change') || !$settings->get('capture_on_status_change')) {
                return;
            }
            $gateway_repository = $c->get('wcgateway.gateway-repository');
            assert($gateway_repository instanceof GatewayRepository);
            // Only allow to proceed if the payment method is one of our Gateways.
            if (!$gateway_repository->exists($wc_order->get_payment_method())) {
                return;
            }
            $intent = strtoupper((string) $wc_order->get_meta(PayPalGateway::INTENT_META_KEY));
            $captured = wc_string_to_bool($wc_order->get_meta(AuthorizedPaymentsProcessor::CAPTURED_META_KEY));
            if ($intent !== 'AUTHORIZE' || $captured) {
                return;
            }
            /**
             * The filter returning the WC order statuses which trigger capturing of payment authorization.
             */
            $capture_statuses = apply_filters('woocommerce_paypal_payments_auto_capture_statuses', array('processing', 'completed'), $wc_order);
            if (!in_array($to, $capture_statuses, \true)) {
                return;
            }
            $authorized_payment_processor = $c->get('wcgateway.processor.authorized-payments');
            assert($authorized_payment_processor instanceof AuthorizedPaymentsProcessor);
            try {
                if ($authorized_payment_processor->capture_authorized_payment($wc_order)) {
                    return;
                }
            } catch (Throwable $error) {
                $logger = $c->get('woocommerce.logger.woocommerce');
                assert($logger instanceof LoggerInterface);
                $logger->error("Capture failed. {$error->getMessage()} {$error->getFile()}:{$error->getLine()}");
            }
            $wc_order->update_status('failed', __('Could not capture the payment.', 'woocommerce-paypal-payments'));
        }, 10, 3);
        add_action('woocommerce_paypal_payments_uninstall', static function () use ($c) {
            $listener = $c->get('wcgateway.settings.listener');
            assert($listener instanceof SettingsListener);
            $listener->listen_for_uninstall();
        });
        if (defined('WP_CLI') && WP_CLI) {
            add_action('init', function () use ($c) {
                \WP_CLI::add_command('pcp settings', $c->get('wcgateway.cli.settings.command'));
            });
        }
        // Clears product status when appropriate.
        add_action('woocommerce_paypal_payments_clear_apm_product_status', function (?Settings $settings = null) use ($c): void {
            // Clear DCC Product status.
            $dcc_product_status = $c->get('wcgateway.helper.dcc-product-status');
            if ($dcc_product_status instanceof DCCProductStatus) {
                $dcc_product_status->clear($settings);
            }
            // Clear Pay Upon Invoice status.
            $pui_product_status = $c->get('wcgateway.pay-upon-invoice-product-status');
            if ($pui_product_status instanceof PayUponInvoiceProductStatus) {
                $pui_product_status->clear($settings);
            }
            // Clear PWC status.
            $pwc_product_status = $c->get('wcgateway.pwc-product-status');
            if ($pwc_product_status instanceof PWCProductStatus) {
                $pwc_product_status->clear($settings);
            }
            $reference_transaction_status_cache = $c->get('api.reference-transaction-status-cache');
            assert($reference_transaction_status_cache instanceof Cache);
            // Clear Reference Transaction status.
            if ($reference_transaction_status_cache->has(ReferenceTransactionStatus::CACHE_KEY)) {
                $reference_transaction_status_cache->delete(ReferenceTransactionStatus::CACHE_KEY);
            }
        });
        /**
         * Param types removed to avoid third-party issues.
         *
         * @psalm-suppress MissingClosureParamType
         */
        add_filter('woocommerce_admin_billing_fields', fn($fields) => $this->insert_custom_fields_into_order_details($fields));
        /**
         * Param types removed to avoid third-party issues.
         *
         * @psalm-suppress MissingClosureParamType
         */
        add_action('woocommerce_admin_order_data_after_shipping_address', fn($order) => $this->display_original_contact_in_order_details($order));
        add_action('woocommerce_paypal_payments_gateway_migrate', function (string $installed_plugin_version) use ($c) {
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof Settings);
            if (!$installed_plugin_version) {
                $settings->set('allow_local_apm_gateways', \true);
                $settings->persist();
            }
        });
        add_filter('woocommerce_paypal_payments_rest_common_merchant_features', static function (array $features) use ($c): array {
            $is_connected = $c->get('settings.flag.is-connected');
            if (!$is_connected) {
                return $features;
            }
            $reference_transaction_status = $c->get('api.reference-transaction-status');
            assert($reference_transaction_status instanceof ReferenceTransactionStatus);
            $dcc_product_status = $c->get('wcgateway.helper.dcc-product-status');
            assert($dcc_product_status instanceof DCCProductStatus);
            $dcc_applies = $c->get('api.helpers.dccapplies');
            $apms_product_status = $c->get('ppcp-local-apms.product-status');
            assert($apms_product_status instanceof LocalApmProductStatus);
            $installments_product_status = $c->get('wcgateway.installments-product-status');
            assert($installments_product_status instanceof InstallmentsProductStatus);
            $pwc_product_status = $c->get('wcgateway.pwc-product-status');
            assert($pwc_product_status instanceof PWCProductStatus);
            $contact_module_check = $c->get('wcgateway.contact-module.eligibility.check');
            assert(is_callable($contact_module_check));
            $save_payment_methods_check = $c->get('save-payment-methods.eligibility.check');
            assert(is_callable($save_payment_methods_check));
            $features[FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO] = array('enabled' => $reference_transaction_status->reference_transaction_enabled() && $save_payment_methods_check());
            $features[FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS] = array('enabled' => $dcc_product_status->is_active() && $dcc_applies->for_country_currency());
            $features[FeaturesDefinition::FEATURE_ALTERNATIVE_PAYMENT_METHODS] = array('enabled' => $apms_product_status->is_active());
            // When local APMs are available, then PayLater messaging is also available.
            $features[FeaturesDefinition::FEATURE_PAY_LATER_MESSAGING] = array('enabled' => $features[FeaturesDefinition::FEATURE_ALTERNATIVE_PAYMENT_METHODS]['enabled']);
            $features[FeaturesDefinition::FEATURE_INSTALLMENTS] = array('enabled' => $installments_product_status->is_active());
            $features[FeaturesDefinition::FEATURE_PAY_WITH_CRYPTO] = array('enabled' => $pwc_product_status->is_active());
            $features[FeaturesDefinition::FEATURE_CONTACT_MODULE] = array('enabled' => $contact_module_check());
            return $features;
        });
        add_action('rest_api_init', static function () use ($c) {
            $endpoint = $c->get('wcgateway.shipping.callback.endpoint');
            assert($endpoint instanceof ShippingCallbackEndpoint);
            $endpoint->register();
        });
        // Add processing instruction request data for OXXO payment.
        add_filter('ppcp_create_order_request_body_data', static function (array $data, string $payment_method, array $request) use ($c): array {
            if ($payment_method !== OXXOGateway::ID) {
                return $data;
            }
            $processing_instruction = $request['processing_instruction'] ?? '';
            if ($processing_instruction) {
                $data['processing_instruction'] = $processing_instruction;
            }
            return $data;
        }, 10, 3);
        return \true;
    }
    /**
     * Registers the payment gateways.
     *
     * @param ContainerInterface $container The container.
     */
    private function register_payment_gateways(ContainerInterface $container)
    {
        add_filter('woocommerce_payment_gateways', static function ($methods) use ($container): array {
            $paypal_gateway = $container->get('wcgateway.paypal-gateway');
            assert($paypal_gateway instanceof \WC_Payment_Gateway);
            $paypal_gateway_enabled = wc_string_to_bool($paypal_gateway->get_option('enabled'));
            $methods[] = $paypal_gateway;
            $settings = $container->get('wcgateway.settings');
            assert($settings instanceof ContainerInterface);
            $is_our_page = $container->get('wcgateway.is-ppcp-settings-page');
            $is_gateways_list_page = $container->get('wcgateway.is-wc-gateways-list-page');
            $is_connected = $container->get('settings.flag.is-connected');
            if (!$is_connected) {
                return $methods;
            }
            $dcc_configuration = $container->get('wcgateway.configuration.card-configuration');
            assert($dcc_configuration instanceof CardPaymentsConfiguration);
            $standard_card_button = get_option('woocommerce_ppcp-card-button-gateway_settings');
            if ($dcc_configuration->is_acdc_enabled() && isset($standard_card_button['enabled'])) {
                $standard_card_button['enabled'] = 'no';
                update_option('woocommerce_ppcp-card-button-gateway_settings', $standard_card_button);
            }
            $dcc_applies = $container->get('api.helpers.dccapplies');
            assert($dcc_applies instanceof DccApplies);
            $dcc_product_status = $container->get('wcgateway.helper.dcc-product-status');
            assert($dcc_product_status instanceof DCCProductStatus);
            if ($dcc_applies->for_country_currency() && ($is_our_page || $is_gateways_list_page && $dcc_product_status->is_active() || $settings->has('products_dcc_enabled') && $settings->get('products_dcc_enabled'))) {
                $methods[] = $container->get('wcgateway.credit-card-gateway');
            }
            if ($paypal_gateway_enabled && apply_filters('woocommerce_paypal_payments_card_button_gateway_should_register_gateway', $container->get('wcgateway.settings.allow_card_button_gateway'))) {
                $methods[] = $container->get('wcgateway.card-button-gateway');
            }
            $pui_product_status = $container->get('wcgateway.pay-upon-invoice-product-status');
            assert($pui_product_status instanceof PayUponInvoiceProductStatus);
            $shop_country = $container->get('api.shop.country');
            if ('DE' === $shop_country && ($is_our_page || $is_gateways_list_page && $pui_product_status->is_active() || $settings->has('products_pui_enabled') && $settings->get('products_pui_enabled'))) {
                $methods[] = $container->get('wcgateway.pay-upon-invoice-gateway');
            }
            if ('MX' === $shop_country) {
                $methods[] = $container->get('wcgateway.oxxo-gateway');
            }
            return (array) $methods;
        });
        add_action('woocommerce_settings_save_checkout', static function () use ($container) {
            $listener = $container->get('wcgateway.settings.listener');
            /**
             * The settings listener.
             *
             * @var SettingsListener $listener
             */
            $listener->listen();
        });
        add_action('admin_init', static function () use ($container) {
            $listener = $container->get('wcgateway.settings.listener');
            assert($listener instanceof SettingsListener);
            $use_new_ui = $container->get('wcgateway.settings.admin-settings-enabled');
            if (!$use_new_ui) {
                $listener->listen_for_merchant_id();
            }
            try {
                $listener->listen_for_vaulting_enabled();
            } catch (RuntimeException $exception) {
                add_action('admin_notices', function () use ($exception) {
                    printf('<div class="notice notice-error"><p>%1$s</p><p>%2$s</p></div>', esc_html__('Authentication with PayPal failed: ', 'woocommerce-paypal-payments') . esc_attr($exception->getMessage()), wp_kses_post(__('Please verify your API Credentials and try again to connect your PayPal business account. Visit the <a href="https://docs.woocommerce.com/document/woocommerce-paypal-payments/" target="_blank">plugin documentation</a> for more information about the setup.', 'woocommerce-paypal-payments')));
                });
            }
        });
        add_filter('woocommerce_form_field', static function ($field, $key, $args, $value) use ($container) {
            $renderer = $container->get('wcgateway.settings.render');
            /**
             * The Settings Renderer object.
             *
             * @var SettingsRenderer $renderer
             */
            $field = $renderer->render_multiselect($field, $key, $args, $value);
            $field = $renderer->render_password($field, $key, $args, $value);
            $field = $renderer->render_heading($field, $key, $args, $value);
            $field = $renderer->render_table($field, $key, $args, $value);
            $field = $renderer->render_html($field, $key, $args, $value);
            return $field;
        }, 10, 4);
        add_filter('woocommerce_available_payment_gateways', static function ($methods) use ($container): array {
            $disabler = $container->get('wcgateway.disabler');
            /**
             * The Gateay disabler.
             *
             * @var DisableGateways $disabler
             */
            return $disabler->handler((array) $methods);
        });
    }
    /**
     * Registers the authorize order functionality.
     *
     * @param ContainerInterface $container The container.
     */
    private function register_order_functionality(ContainerInterface $container)
    {
        add_filter('woocommerce_order_actions', static function ($order_actions) use ($container): array {
            global $theorder;
            if (!is_a($theorder, WC_Order::class)) {
                return $order_actions;
            }
            $render_reauthorize = $container->get('wcgateway.admin.render-reauthorize-action');
            $render_authorize = $container->get('wcgateway.admin.render-authorize-action');
            /**
             * Renders the authorize action in the select field.
             *
             * @var RenderAuthorizeAction $render
             */
            return $render_reauthorize->render($render_authorize->render($order_actions, $theorder), $theorder);
        });
        add_action('woocommerce_order_action_ppcp_authorize_order', static function (WC_Order $wc_order) use ($container) {
            /**
             * The authorized payments processor.
             *
             * @var AuthorizedPaymentsProcessor $authorized_payments_processor
             */
            $authorized_payments_processor = $container->get('wcgateway.processor.authorized-payments');
            $authorized_payments_processor->capture_authorized_payment($wc_order);
        });
        add_action('woocommerce_order_action_ppcp_reauthorize_order', static function (WC_Order $wc_order) use ($container) {
            $admin_notices = $container->get('admin-notices.repository');
            assert($admin_notices instanceof Repository);
            /**
             * The authorized payments processor.
             *
             * @var AuthorizedPaymentsProcessor $authorized_payments_processor
             */
            $authorized_payments_processor = $container->get('wcgateway.processor.authorized-payments');
            if ($authorized_payments_processor->reauthorize_payment($wc_order) !== AuthorizedPaymentsProcessor::SUCCESSFUL) {
                $message = sprintf('%1$s %2$s', esc_html__('Reauthorization with PayPal failed: ', 'woocommerce-paypal-payments'), $authorized_payments_processor->reauthorization_failure_reason() ?: '');
                $admin_notices->persist(new Message($message, 'error'));
            } else {
                $admin_notices->persist(new Message('Payment reauthorized.', 'info'));
                $wc_order->add_order_note(__('Payment reauthorized.', 'woocommerce-paypal-payments'));
            }
        });
    }
    /**
     * Registers the additional columns on the order list page.
     *
     * @param ContainerInterface $container The container.
     */
    private function register_columns(ContainerInterface $container)
    {
        add_action('woocommerce_order_actions_start', static function ($wc_order_id) use ($container) {
            /**
             * The Payment Status Order Detail.
             *
             * @var PaymentStatusOrderDetail $class
             */
            $class = $container->get('wcgateway.admin.order-payment-status');
            $class->render(intval($wc_order_id));
        });
        add_filter('manage_edit-shop_order_columns', static function ($columns) use ($container) {
            /**
             * The Order Table Payment Status object.
             *
             * @var OrderTablePaymentStatusColumn $payment_status_column
             */
            $payment_status_column = $container->get('wcgateway.admin.orders-payment-status-column');
            return $payment_status_column->register($columns);
        });
        add_action('manage_shop_order_posts_custom_column', static function ($column, $wc_order_id) use ($container) {
            /**
             * The column object.
             *
             * @var OrderTablePaymentStatusColumn $payment_status_column
             */
            $payment_status_column = $container->get('wcgateway.admin.orders-payment-status-column');
            $payment_status_column->render((string) $column, intval($wc_order_id));
        }, 10, 2);
    }
    /**
     * Registers the PayPal Address preset to overwrite Shipping in checkout.
     *
     * @param ContainerInterface $container The container.
     */
    private function register_checkout_paypal_address_preset(ContainerInterface $container)
    {
        add_filter('woocommerce_checkout_get_value', static function (...$args) use ($container) {
            /**
             * Its important to not instantiate the service too early as it
             * depends on SessionHandler and WooCommerce Session.
             */
            /**
             * The CheckoutPayPalAddressPreset object.
             *
             * @var CheckoutPayPalAddressPreset $service
             */
            $service = $container->get('wcgateway.checkout.address-preset');
            return $service->filter_checkout_field(...$args);
        }, 10, 2);
    }
    /**
     * Registers the tasks inside "Things to do next" WC section.
     *
     * @param ContainerInterface $container The container.
     * @return void
     */
    protected function register_wc_tasks(ContainerInterface $container): void
    {
        add_action('init', static function () use ($container): void {
            $logger = $container->get('woocommerce.logger.woocommerce');
            assert($logger instanceof LoggerInterface);
            try {
                $simple_redirect_tasks = $container->get('wcgateway.settings.wc-tasks.simple-redirect-tasks');
                if (empty($simple_redirect_tasks)) {
                    return;
                }
                $task_registrar = $container->get('wcgateway.settings.wc-tasks.task-registrar');
                assert($task_registrar instanceof TaskRegistrarInterface);
                $task_registrar->register('extended', $simple_redirect_tasks);
            } catch (Exception $exception) {
                $logger->error("Failed to create a task in the 'Things to do next' section of WC. " . $exception->getMessage());
            }
        });
    }
    /**
     * Registers inbox notes in the WooCommerce Admin inbox section.
     */
    protected function register_woo_inbox_notes(ContainerInterface $container): void
    {
        add_action('admin_init', static function () use ($container): void {
            $logger = $container->get('woocommerce.logger.woocommerce');
            assert($logger instanceof LoggerInterface);
            $inbox_note_registrar = $container->get('wcgateway.settings.inbox-note-registrar');
            assert($inbox_note_registrar instanceof InboxNoteRegistrar);
            try {
                $inbox_note_registrar->register();
            } catch (Exception $exception) {
                $logger->error('Failed to add note to the WooCommerce inbox section. ' . $exception->getMessage());
            }
        });
    }
    /**
     * Registers the assets and ajax endpoint for the void button.
     *
     * @param ContainerInterface $container The container.
     */
    protected function register_void_button(ContainerInterface $container): void
    {
        add_action('admin_enqueue_scripts', static function () use ($container) {
            $assets = $container->get('wcgateway.void-button.assets');
            assert($assets instanceof VoidButtonAssets);
            if ($assets->should_register()) {
                $assets->register();
            }
        });
        add_action('wc_ajax_' . VoidOrderEndpoint::ENDPOINT, static function () use ($container) {
            $endpoint = $container->get('wcgateway.void-button.endpoint');
            assert($endpoint instanceof VoidOrderEndpoint);
            $endpoint->handle_request();
        });
    }
    /**
     * Checks, if the provided argument is a WC_Order which was paid directly by PayPal.
     *
     * Only considers direct PayPal payments, and returns false for orders that were paid "via"
     * PayPal, like wallets (Google Pay, ...) or local APMs.
     *
     * @param WC_Order|mixed $order The order to verify.
     * @return bool True, if it's a valid order that was paid via PayPal.
     */
    private function is_order_paid_by_paypal($order): bool
    {
        if (!$order instanceof WC_Order) {
            return \false;
        }
        if (!$order->get_meta(PayPalGateway::ORDER_PAYER_EMAIL_META_KEY)) {
            return \false;
        }
        if ('paypal' !== $order->get_meta(PayPalGateway::ORDER_PAYMENT_SOURCE_META_KEY)) {
            return \false;
        }
        return \false === strpos($order->get_payment_method_title(), '(via PayPal)');
    }
    /**
     * Overwrite WC order email/phone.
     *
     * The contact module can provide a custom email and phone number.
     * These two properties are intended to be treated as primary contact details.
     */
    private function register_contact_handlers(): void
    {
        $set_order_contacts = function (WC_Order $wc_order): void {
            $email = $wc_order->get_meta(PayPalGateway::CONTACT_EMAIL_META_KEY);
            $phone = $wc_order->get_meta(PayPalGateway::CONTACT_PHONE_META_KEY);
            if ($email && is_email($email)) {
                $wc_order->set_billing_email($email);
            }
            if ($phone && is_string($phone)) {
                $wc_order->set_billing_phone($phone);
            }
        };
        add_action('woocommerce_paypal_payments_contacts_added', function (WC_Order $wc_order) use ($set_order_contacts): void {
            $set_order_contacts($wc_order);
        });
        // There is a race condition in express block checkout, the contacts set in woocommerce_paypal_payments_contacts_added
        // may get reverted by another ajax WC request. So we set them again after that.
        add_action('woocommerce_store_api_cart_update_order_from_request', function (WC_Order $wc_order) use ($set_order_contacts): void {
            // This hook fires for all methods, but we do not do anything if the meta is not set
            // so probably no need to add additional checks.
            $set_order_contacts($wc_order);
        });
    }
    /**
     * Inserts custom fields into the order-detail view.
     *
     * @param mixed $fields The field-list provided by WooCommerce, should be an array.
     * @return array|mixed The filtered field list.
     *
     * @psalm-suppress MissingClosureParamType
     */
    private function insert_custom_fields_into_order_details($fields)
    {
        global $theorder;
        if (!is_array($fields)) {
            return $fields;
        }
        if (!$this->is_order_paid_by_paypal($theorder)) {
            return $fields;
        }
        /**
         * We use this filter to de-customize the order details - 'billing' and 'shipping' section.
         */
        if (!apply_filters('woocommerce_paypal_payments_order_details_show_paypal_email', \true)) {
            return $fields;
        }
        $email = $theorder->get_meta(PayPalGateway::ORDER_PAYER_EMAIL_META_KEY) ?: '';
        $fields['paypal_email'] = array('label' => __('PayPal email address', 'woocommerce-paypal-payments'), 'value' => $email, 'wrapper_class' => 'form-field-wide', 'custom_attributes' => array('disabled' => 'disabled'));
        return $fields;
    }
    /**
     * Displays a custom section in the order details page with the original contact details entered
     * during checkout.
     *
     * When the Contact module is active, those contact details are replaced with details provided
     * by PayPal; this section shows the (unused) details which the user originally entered.
     *
     * @param WC_Order|mixed $order The order which is rendered.
     * @return void
     */
    private function display_original_contact_in_order_details($order): void
    {
        if (!$this->is_order_paid_by_paypal($order)) {
            return;
        }
        if (!apply_filters('woocommerce_paypal_payments_order_details_show_original_contact', \true)) {
            return;
        }
        assert($order instanceof WC_Order);
        $contact_email = $order->get_meta(PayPalGateway::ORIGINAL_EMAIL_META_KEY);
        $contact_phone = $order->get_meta(PayPalGateway::ORIGINAL_PHONE_META_KEY);
        if (!$contact_email && !$contact_phone) {
            return;
        }
        ?>
		<div class="ppcp-original-contact-data address" style="clear:both">
			<h3>
				<?php 
        esc_html_e('Other', 'woocommerce-paypal-payments');
        ?>
				<span
					class="woocommerce-help-tip alignright" tabindex="0"
					data-tip="<?php 
        esc_attr_e('The customer entered these contact details during checkout, but provided different details in the PayPal popup. These details are kept for reference only.', 'woocommerce-paypal-payments');
        ?>"
				></span>
			</h3>
			<?php 
        if (!empty($contact_email)) {
            ?>
				<p>
					<strong>
						<?php 
            esc_html_e('Email address', 'woocommerce-paypal-payments');
            ?>:
					</strong>
					<a href="<?php 
            echo esc_url('mailto:' . $contact_email);
            ?>"><?php 
            echo esc_html($contact_email);
            ?></a>
				</p>
			<?php 
        }
        ?>
			<?php 
        if (!empty($contact_phone)) {
            ?>
				<p>
					<strong>
						<?php 
            esc_html_e('Phone', 'woocommerce-paypal-payments');
            ?>:
					</strong>
					<?php 
            echo wc_make_phone_clickable($contact_phone);
            ?>
				</p>
			<?php 
        }
        ?>
		</div>
		<?php 
    }
}
