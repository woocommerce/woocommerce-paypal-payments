<?php

/**
 * The services of the Gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */
// phpcs:disable WordPress.Security.NonceVerification.Recommended
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway;

use Automattic\WooCommerce\Admin\Notes\Note;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PayUponInvoiceOrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ReferenceTransactionStatus;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Axo\Helper\PropertiesDictionary;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesDisclaimers;
use WooCommerce\PayPalCommerce\Common\Pattern\SingletonDecorator;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingOptionsRenderer;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\SettingsModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Admin\FeesRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use WooCommerce\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use WooCommerce\PayPalCommerce\WcGateway\Admin\RenderAuthorizeAction;
use WooCommerce\PayPalCommerce\WcGateway\Admin\RenderReauthorizeAction;
use WooCommerce\PayPalCommerce\WcGateway\Assets\FraudNetAssets;
use WooCommerce\PayPalCommerce\WcGateway\Assets\VoidButtonAssets;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\CheckoutPayPalAddressPreset;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use WooCommerce\PayPalCommerce\WcGateway\Cli\SettingsCommand;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\CaptureCardPayment;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\RefreshFeatureStatusEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ShippingCallbackEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\VoidOrderEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\FraudNet\FraudNet;
use WooCommerce\PayPalCommerce\WcGateway\FraudNet\FraudNetSourceWebsiteId;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\GatewayRepository;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXOGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PaymentSourceFactory;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoice;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CartCheckoutDetector;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CheckoutHelper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DisplayManager;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\FeesUpdater;
use WooCommerce\PayPalCommerce\WcGateway\Helper\InstallmentsProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\MerchantDetails;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceHelper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PWCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\RefundFeesUpdater;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\GatewayWithoutPayPalAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\SendOnlyCountryNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\UnsupportedCurrencyAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\HeaderRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SectionsRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsListener;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteAction;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteFactory;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteRegistrar;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Factory\SimpleRedirectTaskFactory;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Factory\SimpleRedirectTaskFactoryInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Registrar\TaskRegistrar;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Registrar\TaskRegistrarInterface;
use WooCommerce\PayPalCommerce\WcGateway\Settings\WcTasks\Tasks\SimpleRedirectTask;
use WooCommerce\PayPalCommerce\WcGateway\Shipping\ShippingCallbackUrlFactory;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Endpoint\CartEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\CartFactory;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\CartTotalsFactory;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\MoneyFactory;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\ShippingRatesFactory;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Webhooks\WebhookEventStorage;
return array(
    'wcgateway.paypal-gateway' => static function (ContainerInterface $container): PayPalGateway {
        return new PayPalGateway($container->get('wcgateway.settings.render'), $container->get('wcgateway.funding-source.renderer'), $container->get('wcgateway.order-processor'), $container->get('wcgateway.settings'), $container->get('session.handler'), $container->get('wcgateway.processor.refunds'), $container->get('settings.flag.is-connected'), $container->get('wcgateway.transaction-url-provider'), $container->get('wc-subscriptions.helper'), $container->get('wcgateway.current-ppcp-settings-page-id'), $container->get('settings.environment'), $container->get('vaulting.repository.payment-token'), $container->get('woocommerce.logger.woocommerce'), $container->get('api.shop.country'), $container->get('api.endpoint.order'), $container->get('api.factory.paypal-checkout-url'), $container->get('wcgateway.place-order-button-text'), $container->get('api.endpoint.payment-tokens'), $container->get('vaulting.vault-v3-enabled'), $container->get('vaulting.wc-payment-tokens'), $container->get('wcgateway.asset_getter'), $container->get('wcgateway.settings.admin-settings-enabled'));
    },
    'wcgateway.credit-card-gateway' => static function (ContainerInterface $container): CreditCardGateway {
        return new CreditCardGateway($container->get('wcgateway.settings.render'), $container->get('wcgateway.order-processor'), $container->get('wcgateway.settings'), $container->get('wcgateway.configuration.card-configuration'), $container->get('wcgateway.credit-card-icons'), $container->get('session.handler'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.transaction-url-provider'), $container->get('wc-subscriptions.helper'), $container->get('api.endpoint.payments'), $container->get('vaulting.credit-card-handler'), $container->get('settings.environment'), $container->get('api.endpoint.order'), $container->get('wcgateway.endpoint.capture-card-payment'), $container->get('api.prefix'), $container->get('api.endpoint.payment-tokens'), $container->get('vaulting.wc-payment-tokens'), $container->get('woocommerce.logger.woocommerce'));
    },
    'wcgateway.credit-card-labels' => static function (ContainerInterface $container): array {
        return array('visa' => _x('Visa', 'Name of credit card', 'woocommerce-paypal-payments'), 'mastercard' => _x('Mastercard', 'Name of credit card', 'woocommerce-paypal-payments'), 'amex' => _x('American Express', 'Name of credit card', 'woocommerce-paypal-payments'), 'discover' => _x('Discover', 'Name of credit card', 'woocommerce-paypal-payments'), 'jcb' => _x('JCB', 'Name of credit card', 'woocommerce-paypal-payments'), 'elo' => _x('Elo', 'Name of credit card', 'woocommerce-paypal-payments'), 'hiper' => _x('Hiper', 'Name of credit card', 'woocommerce-paypal-payments'));
    },
    'wcgateway.credit-card-icons' => static function (ContainerInterface $container): array {
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        $icons = $settings->has('card_icons') ? (array) $settings->get('card_icons') : array();
        $labels = $container->get('wcgateway.credit-card-labels');
        $asset_getter = $container->get('wcgateway.asset_getter');
        assert($asset_getter instanceof AssetGetter);
        $url_root = $asset_getter->get_static_asset_url('images/');
        $icons_with_label = array();
        foreach ($icons as $icon) {
            $type = str_replace('-dark', '', $icon);
            $icons_with_label[] = array('type' => $type, 'title' => ucwords($labels[$type] ?? $type), 'url' => "{$url_root}/{$icon}.svg");
        }
        return $icons_with_label;
    },
    'wcgateway.card-button-gateway' => static function (ContainerInterface $container): CardButtonGateway {
        return new CardButtonGateway($container->get('wcgateway.settings.render'), $container->get('wcgateway.order-processor'), $container->get('wcgateway.settings'), $container->get('session.handler'), $container->get('wcgateway.processor.refunds'), $container->get('settings.flag.is-connected'), $container->get('wcgateway.transaction-url-provider'), $container->get('wc-subscriptions.helper'), $container->get('wcgateway.settings.allow_card_button_gateway.default'), $container->get('settings.environment'), $container->get('vaulting.repository.payment-token'), $container->get('woocommerce.logger.woocommerce'), $container->get('api.factory.paypal-checkout-url'), $container->get('wcgateway.place-order-button-text'));
    },
    'wcgateway.disabler' => static function (ContainerInterface $container): DisableGateways {
        $settings = $container->get('wcgateway.settings');
        $settings_status = $container->get('wcgateway.settings.status');
        $subscription_helper = $container->get('wc-subscriptions.helper');
        $context = $container->get('button.helper.context');
        return new DisableGateways($settings, $settings_status, $subscription_helper, $context);
    },
    'wcgateway.is-wc-settings-page' => static function (ContainerInterface $container): bool {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        return 'wc-settings' === $page;
    },
    'wcgateway.is-wc-payments-page' => static function (ContainerInterface $container): bool {
        $is_wc_settings_page = $container->get('wcgateway.is-wc-settings-page');
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
        return $is_wc_settings_page && 'checkout' === $tab;
    },
    'wcgateway.is-wc-gateways-list-page' => static function (ContainerInterface $container): bool {
        return $container->get('wcgateway.is-wc-payments-page') && !isset($_GET['section']);
    },
    'wcgateway.is-ppcp-settings-page' => static function (ContainerInterface $container): bool {
        if (!$container->get('wcgateway.is-wc-payments-page')) {
            return \false;
        }
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';
        return in_array($section, array(Settings::CONNECTION_TAB_ID, PayPalGateway::ID, CreditCardGateway::ID, PayUponInvoiceGateway::ID, CardButtonGateway::ID, OXXOGateway::ID, Settings::PAY_LATER_TAB_ID, AxoGateway::ID, GooglePayGateway::ID, ApplePayGateway::ID), \true);
    },
    // Checks, if the current admin page contains settings for this plugin's payment methods.
    'wcgateway.is-ppcp-settings-payment-methods-page' => static function (ContainerInterface $container): bool {
        if (!$container->get('wcgateway.is-ppcp-settings-page')) {
            return \false;
        }
        $active_tab = $container->get('wcgateway.current-ppcp-settings-page-id');
        return in_array($active_tab, array(PayPalGateway::ID, CreditCardGateway::ID, CardButtonGateway::ID, Settings::PAY_LATER_TAB_ID, Settings::CONNECTION_TAB_ID, GooglePayGateway::ID, ApplePayGateway::ID), \true);
    },
    'wcgateway.current-ppcp-settings-page-id' => static function (ContainerInterface $container): string {
        if (!$container->get('wcgateway.is-ppcp-settings-page')) {
            return '';
        }
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';
        $ppcp_tab = isset($_GET[SectionsRenderer::KEY]) ? sanitize_text_field(wp_unslash($_GET[SectionsRenderer::KEY])) : '';
        $is_connected = $container->get('settings.flag.is-connected');
        if (!$ppcp_tab && PayPalGateway::ID === $section && !$is_connected) {
            return Settings::CONNECTION_TAB_ID;
        }
        return $ppcp_tab ? $ppcp_tab : $section;
    },
    'wcgateway.settings' => SingletonDecorator::make(static function (ContainerInterface $container): Settings {
        return new Settings($container->get('wcgateway.button.default-locations'), $container->get('wcgateway.settings.dcc-gateway-title.default'), $container->get('wcgateway.settings.pay-later.default-button-locations'), $container->get('wcgateway.settings.pay-later.default-messaging-locations'), $container->get('compat.settings.settings_map_helper'));
    }),
    'wcgateway.notice.connect' => static function (ContainerInterface $container): ConnectAdminNotice {
        return new ConnectAdminNotice($container->get('settings.flag.is-connected'), $container->get('wcgateway.settings'), $container->get('wcgateway.is-send-only-country'));
    },
    'wcgateway.notice.currency-unsupported' => static function (ContainerInterface $container): UnsupportedCurrencyAdminNotice {
        return new UnsupportedCurrencyAdminNotice($container->get('settings.flag.is-connected'), $container->get('api.shop.currency.getter'), $container->get('api.supported-currencies'), $container->get('wcgateway.is-wc-gateways-list-page'), $container->get('wcgateway.is-ppcp-settings-page'));
    },
    'wcgateway.notice.dcc-without-paypal' => static function (ContainerInterface $container): GatewayWithoutPayPalAdminNotice {
        return new GatewayWithoutPayPalAdminNotice(CreditCardGateway::ID, $container->get('settings.flag.is-connected'), $container->get('wcgateway.settings'), $container->get('wcgateway.is-wc-payments-page'), $container->get('wcgateway.is-ppcp-settings-page'), $container->get('wcgateway.configuration.card-configuration'));
    },
    'wcgateway.notice.card-button-without-paypal' => static function (ContainerInterface $container): GatewayWithoutPayPalAdminNotice {
        return new GatewayWithoutPayPalAdminNotice(CardButtonGateway::ID, $container->get('settings.flag.is-connected'), $container->get('wcgateway.settings'), $container->get('wcgateway.is-wc-payments-page'), $container->get('wcgateway.is-ppcp-settings-page'), $container->get('wcgateway.configuration.card-configuration'), $container->get('wcgateway.settings.status'));
    },
    'wcgateway.store-country' => static function (): string {
        $location = wc_get_base_location();
        return $location['country'];
    },
    'wcgateway.send-only-message' => static function () {
        return __("<strong>Important</strong>: Your current WooCommerce store location is in a \"send-only\" country, according to PayPal's policies. Sellers in these countries are unable to receive payments via PayPal. Since receiving payments is essential for using the PayPal Payments extension, you will not be able to connect your PayPal account while operating from a \"send-only\" country. To activate PayPal, please update your WooCommerce store location to a supported region and connect a PayPal account eligible for receiving payments.", 'woocommerce-paypal-payments');
    },
    'wcgateway.send-only-countries' => static function () {
        return array('AO', 'AI', 'AM', 'AW', 'AZ', 'BY', 'BJ', 'BT', 'BO', 'VG', 'BN', 'BF', 'BI', 'CI', 'KH', 'CM', 'CV', 'TD', 'KM', 'CG', 'CK', 'DJ', 'ER', 'ET', 'FK', 'GA', 'GM', 'GN', 'GW', 'GY', 'KI', 'KG', 'LA', 'MK', 'MG', 'MV', 'ML', 'MH', 'MR', 'FM', 'MN', 'ME', 'MS', 'NA', 'NR', 'NP', 'NE', 'NG', 'NU', 'NF', 'PG', 'PY', 'PN', 'RW', 'ST', 'WS', 'SL', 'SB', 'SO', 'SH', 'PM', 'VC', 'SR', 'SJ', 'TJ', 'TZ', 'TG', 'TO', 'TN', 'TM', 'TV', 'UG', 'UA', 'VA', 'WF', 'YE', 'ZM', 'ZW');
    },
    'wcgateway.is-send-only-country' => static function (ContainerInterface $container) {
        $store_country = $container->get('wcgateway.store-country');
        $send_only_countries = $container->get('wcgateway.send-only-countries');
        return in_array($store_country, $send_only_countries, \true);
    },
    'wcgateway.notice.send-only-country' => static function (ContainerInterface $container) {
        return new SendOnlyCountryNotice($container->get('wcgateway.send-only-message'), $container->get('wcgateway.is-send-only-country'), $container->get('wcgateway.is-ppcp-settings-page'), $container->get('wcgateway.is-wc-gateways-list-page'), $container->get('settings.flag.is-connected'));
    },
    'wcgateway.notice.authorize-order-action' => static function (ContainerInterface $container): AuthorizeOrderActionNotice {
        return new AuthorizeOrderActionNotice();
    },
    'wcgateway.notice.checkout-blocks' => static function (ContainerInterface $container): string {
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        $axo_available = $container->has('axo.available') && $container->get('axo.available');
        $dcc_configuration = $container->get('wcgateway.configuration.card-configuration');
        assert($dcc_configuration instanceof CardPaymentsConfiguration);
        if ($axo_available && $dcc_configuration->use_fastlane()) {
            return '';
        }
        if (CartCheckoutDetector::has_block_checkout()) {
            return '';
        }
        $checkout_page_link = esc_url(get_edit_post_link(wc_get_page_id('checkout')) ?? '');
        $instructions_link = 'https://woocommerce.com/document/cart-checkout-blocks-status/#using-the-cart-and-checkout-blocks';
        $notice_content = sprintf(
            /* translators: %1$s: URL to the Checkout edit page. %2$s: URL to the WooCommerce Checkout instructions. */
            __('<span class="highlight">Info:</span> The <a href="%1$s">Checkout page</a> of your store currently uses a classic checkout layout or a custom checkout widget. Advanced Card Processing supports the new <code>Checkout</code> block which improves conversion rates. See <a href="%2$s">this page</a> for instructions on how to upgrade to the new Checkout layout.', 'woocommerce-paypal-payments'),
            esc_url($checkout_page_link),
            esc_url($instructions_link)
        );
        return '<div class="ppcp-notice ppcp-notice-success"><p>' . $notice_content . '</p></div>';
    },
    'wcgateway.settings.sections-renderer' => static function (ContainerInterface $container): SectionsRenderer {
        return new SectionsRenderer($container->get('wcgateway.current-ppcp-settings-page-id'), $container->get('settings.flag.is-connected'), $container->get('wcgateway.helper.dcc-product-status'), $container->get('api.helpers.dccapplies'), $container->get('button.helper.messages-apply'), $container->get('wcgateway.pay-upon-invoice-product-status'));
    },
    'wcgateway.settings.header-renderer' => static function (ContainerInterface $container): HeaderRenderer {
        return new HeaderRenderer($container->get('wcgateway.current-ppcp-settings-page-id'), $container->get('wcgateway.asset_getter'));
    },
    'wcgateway.settings.status' => static function (ContainerInterface $container): SettingsStatus {
        $settings = $container->get('wcgateway.settings');
        return new SettingsStatus($settings);
    },
    'wcgateway.settings.render' => static function (ContainerInterface $container): SettingsRenderer {
        return new SettingsRenderer(
            $container->get('wcgateway.settings'),
            $container->get('onboarding.state'),
            // Correct.
            $container->get('wcgateway.settings.fields'),
            $container->get('api.helpers.dccapplies'),
            $container->get('button.helper.messages-apply'),
            $container->get('wcgateway.helper.dcc-product-status'),
            $container->get('wcgateway.settings.status'),
            $container->get('wcgateway.current-ppcp-settings-page-id'),
            $container->get('api.shop.country'),
            $container->get('axo.eligibility.check')
        );
    },
    'wcgateway.settings.listener' => static function (ContainerInterface $container): SettingsListener {
        return new SettingsListener(
            $container->get('wcgateway.settings'),
            $container->get('wcgateway.settings.fields'),
            $container->get('webhook.registrar'),
            $container->get('api.paypal-bearer-cache'),
            $container->get('onboarding.state'),
            // Correct.
            $container->get('api.bearer'),
            $container->get('wcgateway.current-ppcp-settings-page-id'),
            $container->get('onboarding.signup-link-cache'),
            $container->get('onboarding.signup-link-ids'),
            $container->get('pui.status-cache'),
            $container->get('dcc.status-cache'),
            $container->get('http.redirector'),
            $container->get('api.partner_merchant_id-production'),
            $container->get('api.partner_merchant_id-sandbox'),
            $container->get('api.reference-transaction-status'),
            $container->get('woocommerce.logger.woocommerce'),
            new Cache('ppcp-client-credentials-cache'),
            $container->get('api.reference-transaction-status-cache')
        );
    },
    'wcgateway.order-processor' => static function (ContainerInterface $container): OrderProcessor {
        $session_handler = $container->get('session.handler');
        $order_endpoint = $container->get('api.endpoint.order');
        $order_factory = $container->get('api.factory.order');
        $threed_secure = $container->get('button.helper.three-d-secure');
        $authorized_payments_processor = $container->get('wcgateway.processor.authorized-payments');
        $settings = $container->get('wcgateway.settings');
        $environment = $container->get('settings.environment');
        $logger = $container->get('woocommerce.logger.woocommerce');
        $subscription_helper = $container->get('wc-subscriptions.helper');
        $order_helper = $container->get('api.order-helper');
        return new OrderProcessor($session_handler, $order_endpoint, $order_factory, $threed_secure, $authorized_payments_processor, $settings, $logger, $environment, $subscription_helper, $order_helper, $container->get('api.factory.purchase-unit'), $container->get('api.factory.payer'), $container->get('api.factory.shipping-preference'), $container->get('wcgateway.builder.experience-context'));
    },
    'wcgateway.processor.refunds' => static function (ContainerInterface $container): RefundProcessor {
        return new RefundProcessor($container->get('api.endpoint.order'), $container->get('api.endpoint.payments'), $container->get('wcgateway.helper.refund-fees-updater'), $container->get('api.prefix'), $container->get('woocommerce.logger.woocommerce'));
    },
    'wcgateway.processor.authorized-payments' => static function (ContainerInterface $container): AuthorizedPaymentsProcessor {
        $order_endpoint = $container->get('api.endpoint.order');
        $payments_endpoint = $container->get('api.endpoint.payments');
        $logger = $container->get('woocommerce.logger.woocommerce');
        $notice = $container->get('wcgateway.notice.authorize-order-action');
        $settings = $container->get('wcgateway.settings');
        $subscription_helper = $container->get('wc-subscriptions.helper');
        $amount_factory = $container->get('api.factory.amount');
        return new AuthorizedPaymentsProcessor($order_endpoint, $payments_endpoint, $logger, $notice, $settings, $subscription_helper, $amount_factory);
    },
    'wcgateway.admin.render-authorize-action' => static function (ContainerInterface $container): RenderAuthorizeAction {
        $column = $container->get('wcgateway.admin.orders-payment-status-column');
        return new RenderAuthorizeAction($column);
    },
    'wcgateway.admin.render-reauthorize-action' => static function (ContainerInterface $container): RenderReauthorizeAction {
        $column = $container->get('wcgateway.admin.orders-payment-status-column');
        return new RenderReauthorizeAction($column);
    },
    'wcgateway.admin.order-payment-status' => static function (ContainerInterface $container): PaymentStatusOrderDetail {
        $column = $container->get('wcgateway.admin.orders-payment-status-column');
        return new PaymentStatusOrderDetail($column);
    },
    'wcgateway.admin.orders-payment-status-column' => static function (ContainerInterface $container): OrderTablePaymentStatusColumn {
        $settings = $container->get('wcgateway.settings');
        return new OrderTablePaymentStatusColumn($settings);
    },
    'wcgateway.admin.fees-renderer' => static function (ContainerInterface $container): FeesRenderer {
        return new FeesRenderer();
    },
    'wcgateway.settings.should-render-settings' => static function (ContainerInterface $container): bool {
        $sections = array(Settings::CONNECTION_TAB_ID => __('Connection', 'woocommerce-paypal-payments'), PayPalGateway::ID => __('Standard Payments', 'woocommerce-paypal-payments'), Settings::PAY_LATER_TAB_ID => __('Pay Later', 'woocommerce-paypal-payments'), CreditCardGateway::ID => __('Advanced Card Processing', 'woocommerce-paypal-payments'), CardButtonGateway::ID => __('Standard Card Button', 'woocommerce-paypal-payments'));
        $current_page_id = $container->get('wcgateway.current-ppcp-settings-page-id');
        return array_key_exists($current_page_id, $sections);
    },
    'wcgateway.settings.fields.subscriptions_mode_options' => static function (ContainerInterface $container): array {
        return array('vaulting_api' => __('PayPal Vaulting', 'woocommerce-paypal-payments'), 'subscriptions_api' => __('PayPal Subscriptions', 'woocommerce-paypal-payments'), 'disable_paypal_subscriptions' => __('Disable PayPal for subscriptions', 'woocommerce-paypal-payments'));
    },
    'wcgateway.settings.fields.subscriptions_mode' => static function (ContainerInterface $container): array {
        $subscription_mode_options = $container->get('wcgateway.settings.fields.subscriptions_mode_options');
        $reference_transaction_status = $container->get('api.reference-transaction-status');
        assert($reference_transaction_status instanceof ReferenceTransactionStatus);
        if (!$reference_transaction_status->reference_transaction_enabled()) {
            unset($subscription_mode_options['vaulting_api']);
        }
        return array('title' => __('Subscriptions Mode', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'desc_tip' => \true, 'description' => __('Utilize PayPal Vaulting for flexible subscription processing with saved payment methods, create “PayPal Subscriptions” to bill customers at regular intervals, or disable PayPal for subscription-type products.', 'woocommerce-paypal-payments'), 'default' => array_key_first($subscription_mode_options), 'options' => $subscription_mode_options, 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal');
    },
    'wcgateway.settings.fields' => static function (ContainerInterface $container): array {
        $should_render_settings = $container->get('wcgateway.settings.should-render-settings');
        if (!$should_render_settings) {
            return array();
        }
        // Legacy settings service, correct use of `State` class.
        $state = $container->get('onboarding.state');
        assert($state instanceof State);
        $dcc_applies = $container->get('api.helpers.dccapplies');
        assert($dcc_applies instanceof DccApplies);
        $onboarding_options_renderer = $container->get('onboarding.render-options');
        assert($onboarding_options_renderer instanceof OnboardingOptionsRenderer);
        $subscription_helper = $container->get('wc-subscriptions.helper');
        assert($subscription_helper instanceof SubscriptionHelper);
        $dcc_configuration = $container->get('wcgateway.configuration.card-configuration');
        assert($dcc_configuration instanceof CardPaymentsConfiguration);
        $fields = array('checkout_settings_heading' => array('heading' => __('Standard Payments Settings', 'woocommerce-paypal-payments'), 'type' => 'ppcp-heading', 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'title' => array('title' => __('Title', 'woocommerce-paypal-payments'), 'type' => 'text', 'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-paypal-payments'), 'default' => __('PayPal', 'woocommerce-paypal-payments'), 'desc_tip' => \true, 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'dcc_block_checkout_notice' => array('heading' => '', 'html' => $container->get('wcgateway.notice.checkout-blocks'), 'type' => 'ppcp-html', 'classes' => array(), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array('dcc'), 'gateway' => 'dcc'), 'dcc_enabled' => array('title' => __('Enable/Disable', 'woocommerce-paypal-payments'), 'desc_tip' => \true, 'description' => __('Once enabled, the Credit Card option will show up in the checkout.', 'woocommerce-paypal-payments'), 'label' => __('Enable Advanced Card Processing', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'default' => \false, 'gateway' => 'dcc', 'requirements' => array('dcc'), 'screens' => array(State::STATE_ONBOARDED)), 'dcc_gateway_title' => array('title' => __('Title', 'woocommerce-paypal-payments'), 'type' => 'text', 'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-paypal-payments'), 'default' => $container->get('wcgateway.settings.dcc-gateway-title.default'), 'desc_tip' => \true, 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array('dcc'), 'gateway' => 'dcc'), 'description' => array('title' => __('Description', 'woocommerce-paypal-payments'), 'type' => 'text', 'desc_tip' => \true, 'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-paypal-payments'), 'default' => __('Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account.', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'intent' => array('title' => __('Intent', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'capture', 'desc_tip' => \true, 'description' => __('The intent to either capture payment immediately or authorize a payment for an order after order creation.', 'woocommerce-paypal-payments'), 'options' => array('capture' => __('Capture', 'woocommerce-paypal-payments'), 'authorize' => __('Authorize', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'capture_on_status_change' => array('title' => __('Capture On Status Change', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'default' => \false, 'desc_tip' => \true, 'description' => __('The transaction will be captured automatically when the order status changes to Processing or Completed.', 'woocommerce-paypal-payments'), 'label' => __('Capture On Status Change', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'capture_for_virtual_only' => array('title' => __('Capture Virtual-Only Orders ', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'default' => \false, 'desc_tip' => \true, 'description' => __('If the order contains exclusively virtual items, enable this to immediately capture, rather than authorize, the transaction.', 'woocommerce-paypal-payments'), 'label' => __('Capture Virtual-Only Orders', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'payee_preferred' => array('title' => __('Instant Payments ', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'default' => \false, 'desc_tip' => \true, 'description' => __('If you enable this setting, PayPal will be instructed not to allow the buyer to use funding sources that take additional time to complete (for example, eChecks). Instead, the buyer will be required to use an instant funding source, such as an instant transfer, a credit/debit card, or Pay Later.', 'woocommerce-paypal-payments'), 'label' => __('Require Instant Payment', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'brand_name' => array('title' => __('Brand Name', 'woocommerce-paypal-payments'), 'type' => 'text', 'default' => get_bloginfo('name'), 'desc_tip' => \true, 'description' => __('Control the name of your shop, customers will see in the PayPal process.', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'landing_page' => array('title' => __('Landing Page', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => ExperienceContext::LANDING_PAGE_LOGIN, 'desc_tip' => \true, 'description' => __('Type of PayPal page to display.', 'woocommerce-paypal-payments'), 'options' => array(ExperienceContext::LANDING_PAGE_LOGIN => __('Login (PayPal account login)', 'woocommerce-paypal-payments'), ExperienceContext::LANDING_PAGE_GUEST_CHECKOUT => __('Billing (Non-PayPal account)', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), FeaturesDefinition::FEATURE_ALTERNATIVE_PAYMENT_METHODS => array('heading' => __('Alternative Payment Methods', 'woocommerce-paypal-payments'), 'description' => sprintf(
            // translators: %1$s, %2$s, %3$s and %4$s are a link tags.
            __('%1$sAlternative Payment Methods%2$s allow you to accept payments from customers around the globe who use their credit cards, bank accounts, wallets, and local payment methods. When a buyer pays in a currency different than yours, PayPal handles currency conversion for you and presents conversion information to the buyer during checkout.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#alternative-payment-methods" target="_blank">',
            '</a>'
        ), 'type' => 'ppcp-heading', 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'disable_funding' => array('title' => __('Disable Alternative Payment Methods', 'woocommerce-paypal-payments'), 'type' => 'ppcp-multiselect', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => array(), 'desc_tip' => \false, 'description' => sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Choose to hide specific %1$sAlternative Payment Methods%2$s such as Credit Cards, Venmo, or others.', 'woocommerce-paypal-payments'),
            '<a
						href="https://developer.paypal.com/docs/checkout/apm/"
						target="_blank"
					>',
            '</a>'
        ), 'options' => $container->get('wcgateway.settings.funding-sources'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'card_billing_data_mode' => array('title' => __('Send checkout billing data to card fields', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'desc_tip' => \true, 'description' => __('Using the WC form data increases convenience for the customers, but can cause issues if card details do not match the billing data in the checkout form.', 'woocommerce-paypal-payments'), 'default' => $container->get('wcgateway.settings.card_billing_data_mode.default'), 'options' => array(\WooCommerce\PayPalCommerce\WcGateway\CardBillingMode::USE_WC => __('Use WC checkout form data (do not show any address fields)', 'woocommerce-paypal-payments'), \WooCommerce\PayPalCommerce\WcGateway\CardBillingMode::MINIMAL_INPUT => __('Request only name and postal code', 'woocommerce-paypal-payments'), \WooCommerce\PayPalCommerce\WcGateway\CardBillingMode::NO_WC => __('Do not use WC checkout form data (request all address fields)', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => array('paypal', CardButtonGateway::ID)), 'allow_card_button_gateway' => array('title' => __('Create gateway for Standard Card Button', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'desc_tip' => \true, 'label' => __('Moves the Standard Card Button from the PayPal gateway into its own dedicated gateway.', 'woocommerce-paypal-payments'), 'description' => __('By default, the Debit or Credit Card button is displayed in the Standard Payments payment gateway. This setting creates a second gateway for the Card button.', 'woocommerce-paypal-payments'), 'default' => $container->get('wcgateway.settings.allow_card_button_gateway.default'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'allow_local_apm_gateways' => array('title' => __('Create gateway for alternative payment methods', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'desc_tip' => \true, 'label' => __('Moves the alternative payment methods from the PayPal gateway into their own dedicated gateways.', 'woocommerce-paypal-payments'), 'description' => __('By default, alternative payment methods are displayed in the Standard Payments payment gateway. This setting creates a gateway for each alternative payment method.', 'woocommerce-paypal-payments'), 'default' => \false, 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'disable_cards' => array('title' => __('Disable specific credit cards', 'woocommerce-paypal-payments'), 'type' => 'ppcp-multiselect', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => array(), 'desc_tip' => \true, 'description' => __('By default all possible credit cards will be accepted. You can disable some cards, if you wish.', 'woocommerce-paypal-payments'), 'options' => array('visa' => _x('Visa', 'Name of credit card', 'woocommerce-paypal-payments'), 'mastercard' => _x('Mastercard', 'Name of credit card', 'woocommerce-paypal-payments'), 'amex' => _x('American Express', 'Name of credit card', 'woocommerce-paypal-payments'), 'discover' => _x('Discover', 'Name of credit card', 'woocommerce-paypal-payments'), 'jcb' => _x('JCB', 'Name of credit card', 'woocommerce-paypal-payments'), 'elo' => _x('Elo', 'Name of credit card', 'woocommerce-paypal-payments'), 'hiper' => _x('Hiper', 'Name of credit card', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array('dcc'), 'gateway' => 'dcc'), 'card_icons' => array('title' => __('Show logo of the following credit cards', 'woocommerce-paypal-payments'), 'type' => 'ppcp-multiselect', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => array(), 'desc_tip' => \true, 'description' => __('Define which cards you want to display in your checkout.', 'woocommerce-paypal-payments'), 'options' => array('visa' => _x('Visa (light)', 'Name of credit card', 'woocommerce-paypal-payments'), 'visa-dark' => _x('Visa (dark)', 'Name of credit card', 'woocommerce-paypal-payments'), 'mastercard' => _x('Mastercard (light)', 'Name of credit card', 'woocommerce-paypal-payments'), 'mastercard-dark' => _x('Mastercard (dark)', 'Name of credit card', 'woocommerce-paypal-payments'), 'amex' => _x('American Express', 'Name of credit card', 'woocommerce-paypal-payments'), 'discover' => _x('Discover', 'Name of credit card', 'woocommerce-paypal-payments'), 'jcb' => _x('JCB', 'Name of credit card', 'woocommerce-paypal-payments'), 'elo' => _x('Elo', 'Name of credit card', 'woocommerce-paypal-payments'), 'hiper' => _x('Hiper', 'Name of credit card', 'woocommerce-paypal-payments')), 'options_axo' => array('visa-light' => _x('Visa (light)', 'Name of credit card', 'woocommerce-paypal-payments'), 'mastercard-light' => _x('Mastercard (light)', 'Name of credit card', 'woocommerce-paypal-payments'), 'amex-light' => _x('Amex (light)', 'Name of credit card', 'woocommerce-paypal-payments'), 'discover-light' => _x('Discover (light)', 'Name of credit card', 'woocommerce-paypal-payments'), 'dinersclub-light' => _x('Diners Club (light)', 'Name of credit card', 'woocommerce-paypal-payments'), 'jcb-light' => _x('JCB (light)', 'Name of credit card', 'woocommerce-paypal-payments'), 'unionpay-light' => _x('UnionPay (light)', 'Name of credit card', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array('dcc'), 'gateway' => 'dcc'), 'dcc_name_on_card' => array('title' => __('Cardholder Name', 'woocommerce-paypal-payments'), 'type' => 'select', 'default' => $dcc_configuration->show_name_on_card(), 'options' => PropertiesDictionary::cardholder_name_options(), 'classes' => array(), 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'desc_tip' => \true, 'description' => __('This setting will control whether or not the cardholder name is displayed in the card field\'s UI.', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => array('dcc', 'axo'), 'requirements' => array('axo')), '3d_secure_heading' => array('heading' => __('3D Secure', 'woocommerce-paypal-payments'), 'type' => 'ppcp-heading', 'description' => wp_kses_post(sprintf(
            // translators: %1$s and %2$s is a link tag.
            __('3D Secure benefits cardholders and merchants by providing
                                  an additional layer of verification using Verified by Visa,
                                  MasterCard SecureCode and American Express SafeKey.
                                  %1$sLearn more about 3D Secure.%2$s', 'woocommerce-paypal-payments'),
            '<a
                            rel="noreferrer noopener"
                            href="https://woocommerce.com/posts/introducing-strong-customer-authentication-sca/"
                            >',
            '</a>'
        )), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array('dcc'), 'gateway' => 'dcc'), '3d_secure_contingency' => array('title' => __('Contingency for 3D Secure', 'woocommerce-paypal-payments'), 'type' => 'select', 'description' => sprintf(
            // translators: %1$s and %2$s opening and closing ul tag, %3$s and %4$s opening and closing li tag.
            __('%1$s%3$sNo 3D Secure will cause transactions to be denied if 3D Secure is required by the bank of the cardholder.%4$s%3$sSCA_WHEN_REQUIRED returns a 3D Secure contingency when it is a mandate in the region where you operate.%4$s%3$sSCA_ALWAYS triggers 3D Secure for every transaction, regardless of SCA requirements.%4$s%2$s', 'woocommerce-paypal-payments'),
            '<ul>',
            '</ul>',
            '<li>',
            '</li>'
        ), 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => $container->get('api.shop.is-psd2-country') ? 'SCA_WHEN_REQUIRED' : 'NO_3D_SECURE', 'desc_tip' => \true, 'options' => array('NO_3D_SECURE' => __('No 3D Secure (transaction will be denied if 3D Secure is required)', 'woocommerce-paypal-payments'), 'SCA_WHEN_REQUIRED' => __('3D Secure when required', 'woocommerce-paypal-payments'), 'SCA_ALWAYS' => __('Always trigger 3D Secure', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array('dcc'), 'gateway' => 'dcc'), 'saved_payments_heading' => array('heading' => __('Saved Payments', 'woocommerce-paypal-payments'), 'type' => 'ppcp-heading', 'description' => wp_kses_post(sprintf(
            // translators: %1$s and %2$s is a link tag.
            __('PayPal can securely store your customers’ payment methods for
							%1$sfuture payments and subscriptions%2$s, simplifying the checkout
							process and enabling recurring transactions on your website.', 'woocommerce-paypal-payments'),
            '<a
                            rel="noreferrer noopener"
                            href="https://woo.com/document/woocommerce-paypal-payments/#vaulting-a-card"
                            >',
            '</a>'
        )), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array('dcc'), 'gateway' => 'dcc'), 'vault_enabled_dcc' => array('title' => __('Vaulting', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'desc_tip' => \true, 'label' => sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Securely store your customers’ credit cards for a seamless checkout experience and subscription features. Payment methods are saved in the secure %1$sPayPal Vault%2$s.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#vaulting-saving-a-payment-method" target="_blank">',
            '</a>'
        ), 'description' => __('Allow registered buyers to save Credit Card payments.', 'woocommerce-paypal-payments'), 'default' => \false, 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'dcc', 'input_class' => $container->get('wcgateway.helper.vaulting-scope') ? array() : array('ppcp-disabled-checkbox')), 'mexico_installments' => array('heading' => __('Installments', 'woocommerce-paypal-payments'), 'type' => 'ppcp-heading', 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal', 'description' => sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag. %3$s and %4$s are the opening and closing of HTML <p> tag.
            __('Allow your customers to pay in installments without interest while you receive the full payment in a single transaction.*
					%3$sTerms and conditions: *You will receive the full payment minus the applicable PayPal fee. See %1$sterms and conditions%2$s.%4$s', 'woocommerce-paypal-payments'),
            '<a href="https://www.paypal.com/mx/webapps/mpp/merchant-fees" target="_blank">',
            '</a>',
            '<p class="description">',
            '</p>'
        )), 'mexico_installments_action_link' => array('title' => __('Activate your Installments', 'woocommerce-paypal-payments'), 'type' => 'ppcp-text', 'text' => '<a href="https://www.paypal.com/businessmanage/preferences/installmentplan" target="_blank" class="button ppcp-refresh-feature-status">' . esc_html__('Enable Installments', 'woocommerce-paypal-payments') . '</a>', 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'paypal_saved_payments' => array('heading' => __('Saved payments', 'woocommerce-paypal-payments'), 'description' => sprintf(
            // translators: %1$s, %2$s, %3$s and %4$s are a link tags.
            __('PayPal can securely store your customers\' payment methods for %1$sfuture payments%2$s and %3$ssubscriptions%4$s, simplifying the checkout process and enabling recurring transactions on your website.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#vaulting-saving-a-payment-method" target="_blank">',
            '</a>',
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#subscriptions-faq" target="_blank">',
            '</a>'
        ), 'type' => 'ppcp-heading', 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'), 'subscriptions_mode' => $container->get('wcgateway.settings.fields.subscriptions_mode'), 'vault_enabled' => array('title' => __('Vaulting', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'desc_tip' => \true, 'label' => sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Securely store your customers’ PayPal accounts for a seamless checkout experience. Payment methods are saved in the secure %1$sPayPal Vault%2$s.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#vaulting-saving-a-payment-method" target="_blank">',
            '</a>'
        ) . $container->get('button.helper.vaulting-label'), 'description' => __('Allow registered buyers to save PayPal payments.', 'woocommerce-paypal-payments'), 'default' => \false, 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal', 'input_class' => $container->get('wcgateway.helper.vaulting-scope') ? array() : array('ppcp-disabled-checkbox')), 'digital_wallet_heading' => array('heading' => __('Digital Wallet Services', 'woocommerce-paypal-payments'), 'type' => 'ppcp-heading', 'description' => wp_kses_post(sprintf(
            // translators: %1$s and %2$s is a link tag.
            __('PayPal supports digital wallet services like Apple Pay or Google Pay
							to give your buyers more options to pay without a PayPal account.', 'woocommerce-paypal-payments'),
            '<a
                            rel="noreferrer noopener"
                            href="https://woo.com/document/woocommerce-paypal-payments/#vaulting-a-card"
                            >',
            '</a>'
        )), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array('dcc'), 'gateway' => 'dcc'));
        if (!$subscription_helper->plugin_is_active()) {
            unset($fields['subscriptions_mode']);
        }
        /**
         * Depending on your store location, some credit cards can't be used.
         * Here, we filter them out.
         */
        $card_options = $fields['disable_cards']['options'];
        $card_icons = $fields['card_icons']['options'];
        $dark_versions = array();
        foreach ($card_options as $card => $label) {
            if ($dcc_applies->can_process_card($card)) {
                if ('visa' === $card || 'mastercard' === $card) {
                    $dark_versions = array('visa-dark' => $card_icons['visa-dark'], 'mastercard-dark' => $card_icons['mastercard-dark']);
                }
                continue;
            }
            unset($card_options[$card]);
        }
        $fields['disable_cards']['options'] = $card_options;
        $fields['card_icons']['options'] = array_merge($dark_versions, $card_options);
        if ($container->get('api.merchant.country') !== 'MX') {
            unset($fields['mexico_installments']);
            unset($fields['mexico_installments_action_link']);
        }
        return $fields;
    },
    'wcgateway.all-funding-sources' => static function (ContainerInterface $container): array {
        return array('card' => _x('Credit or debit cards', 'Name of payment method', 'woocommerce-paypal-payments'), 'sepa' => _x('SEPA-Lastschrift', 'Name of payment method', 'woocommerce-paypal-payments'), 'bancontact' => _x('Bancontact', 'Name of payment method', 'woocommerce-paypal-payments'), 'blik' => _x('BLIK', 'Name of payment method', 'woocommerce-paypal-payments'), 'eps' => _x('eps', 'Name of payment method', 'woocommerce-paypal-payments'), 'ideal' => _x('iDEAL', 'Name of payment method', 'woocommerce-paypal-payments'), 'mybank' => _x('MyBank', 'Name of payment method', 'woocommerce-paypal-payments'), 'p24' => _x('Przelewy24', 'Name of payment method', 'woocommerce-paypal-payments'), 'venmo' => _x('Venmo', 'Name of payment method', 'woocommerce-paypal-payments'), 'trustly' => _x('Trustly', 'Name of payment method', 'woocommerce-paypal-payments'), 'paylater' => _x('PayPal Pay Later', 'Name of payment method', 'woocommerce-paypal-payments'), 'paypal' => _x('PayPal', 'Name of payment method', 'woocommerce-paypal-payments'));
    },
    'wcgateway.extra-funding-sources' => static function (ContainerInterface $container): array {
        return array('googlepay' => _x('Google Pay', 'Name of payment method', 'woocommerce-paypal-payments'), 'applepay' => _x('Apple Pay', 'Name of payment method', 'woocommerce-paypal-payments'));
    },
    /**
     * The sources that do not cause issues about redirecting (on mobile, ...) and sometimes not returning back.
     */
    'wcgateway.funding-sources-without-redirect' => static function (ContainerInterface $container): array {
        return array('paypal', 'paylater', 'venmo', 'card');
    },
    'wcgateway.settings.funding-sources' => static function (ContainerInterface $container): array {
        return array_diff_key($container->get('wcgateway.all-funding-sources'), array_flip(array('paylater', 'paypal')));
    },
    'wcgateway.checkout.address-preset' => static function (ContainerInterface $container): CheckoutPayPalAddressPreset {
        return new CheckoutPayPalAddressPreset($container->get('session.handler'));
    },
    'wcgateway.asset_getter' => static function (ContainerInterface $container): AssetGetter {
        $factory = $container->get('assets.asset_getter_factory');
        assert($factory instanceof AssetGetterFactory);
        return $factory->for_module('ppcp-wc-gateway');
    },
    'wcgateway.endpoint.return-url' => static function (ContainerInterface $container): ReturnUrlEndpoint {
        $gateway = $container->get('wcgateway.paypal-gateway');
        $endpoint = $container->get('api.endpoint.order');
        return new ReturnUrlEndpoint($gateway, $endpoint, $container->get('session.handler'), $container->get('woocommerce.logger.woocommerce'));
    },
    'wcgateway.endpoint.refresh-feature-status' => static function (ContainerInterface $container): RefreshFeatureStatusEndpoint {
        return new RefreshFeatureStatusEndpoint($container->get('wcgateway.settings'), new Cache('ppcp-timeout'), $container->get('woocommerce.logger.woocommerce'));
    },
    'wcgateway.transaction-url-sandbox' => static function (ContainerInterface $container): string {
        return 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
    },
    'wcgateway.transaction-url-live' => static function (ContainerInterface $container): string {
        return 'https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
    },
    'wcgateway.soft-descriptor' => static function (ContainerInterface $container): string {
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        if ($settings->has('soft_descriptor')) {
            return $settings->get('soft_descriptor');
        }
        return '';
    },
    'wcgateway.transaction-url-provider' => static function (ContainerInterface $container): TransactionUrlProvider {
        $sandbox_url_base = $container->get('wcgateway.transaction-url-sandbox');
        $live_url_base = $container->get('wcgateway.transaction-url-live');
        return new TransactionUrlProvider($sandbox_url_base, $live_url_base);
    },
    'wcgateway.configuration.card-configuration' => static function (ContainerInterface $container): CardPaymentsConfiguration {
        return new CardPaymentsConfiguration($container->get('settings.connection-state'), $container->get('wcgateway.settings'), $container->get('api.helpers.dccapplies'), $container->get('wcgateway.helper.dcc-product-status'), $container->get('api.shop.country'));
    },
    'wcgateway.helper.dcc-product-status' => static function (ContainerInterface $container): DCCProductStatus {
        $settings = $container->get('wcgateway.settings');
        $partner_endpoint = $container->get('api.endpoint.partners');
        return new DCCProductStatus($settings, $partner_endpoint, $container->get('dcc.status-cache'), $container->get('api.helpers.dccapplies'), $container->get('settings.flag.is-connected'), $container->get('api.helper.failure-registry'));
    },
    'wcgateway.helper.refund-fees-updater' => static function (ContainerInterface $container): RefundFeesUpdater {
        $order_endpoint = $container->get('api.endpoint.order');
        $logger = $container->get('woocommerce.logger.woocommerce');
        return new RefundFeesUpdater($order_endpoint, $logger);
    },
    'wcgateway.helper.fees-updater' => static function (ContainerInterface $container): FeesUpdater {
        return new FeesUpdater($container->get('api.endpoint.orders'), $container->get('api.factory.capture'), $container->get('woocommerce.logger.woocommerce'));
    },
    'button.helper.messages-disclaimers' => static function (ContainerInterface $container): MessagesDisclaimers {
        return new MessagesDisclaimers($container->get('api.shop.country'));
    },
    'wcgateway.funding-source.renderer' => function (ContainerInterface $container): FundingSourceRenderer {
        return new FundingSourceRenderer($container->get('wcgateway.settings'), array_merge($container->get('wcgateway.all-funding-sources'), $container->get('wcgateway.extra-funding-sources')));
    },
    'wcgateway.checkout-helper' => static function (ContainerInterface $container): CheckoutHelper {
        return new CheckoutHelper();
    },
    'wcgateway.pay-upon-invoice-order-endpoint' => static function (ContainerInterface $container): PayUponInvoiceOrderEndpoint {
        return new PayUponInvoiceOrderEndpoint($container->get('api.host'), $container->get('api.bearer'), $container->get('api.factory.order'), $container->get('wcgateway.fraudnet'), $container->get('woocommerce.logger.woocommerce'));
    },
    'wcgateway.pay-upon-invoice-payment-source-factory' => static function (ContainerInterface $container): PaymentSourceFactory {
        return new PaymentSourceFactory();
    },
    'wcgateway.pay-upon-invoice-gateway' => static function (ContainerInterface $container): PayUponInvoiceGateway {
        return new PayUponInvoiceGateway($container->get('wcgateway.pay-upon-invoice-order-endpoint'), $container->get('api.factory.purchase-unit'), $container->get('wcgateway.pay-upon-invoice-payment-source-factory'), $container->get('settings.environment'), $container->get('wcgateway.transaction-url-provider'), $container->get('woocommerce.logger.woocommerce'), $container->get('wcgateway.pay-upon-invoice-helper'), $container->get('wcgateway.checkout-helper'), $container->get('settings.flag.is-connected'), $container->get('wcgateway.processor.refunds'), $container->get('wcgateway.asset_getter'));
    },
    'wcgateway.fraudnet-source-website-id' => static function (ContainerInterface $container): FraudNetSourceWebsiteId {
        return new FraudNetSourceWebsiteId($container->get('api.merchant_id'));
    },
    'wcgateway.fraudnet' => static function (ContainerInterface $container): FraudNet {
        $source_website_id = $container->get('wcgateway.fraudnet-source-website-id');
        return new FraudNet((string) $source_website_id());
    },
    'wcgateway.pay-upon-invoice-helper' => static function (ContainerInterface $container): PayUponInvoiceHelper {
        return new PayUponInvoiceHelper($container->get('wcgateway.checkout-helper'), $container->get('api.shop.country'));
    },
    'wcgateway.pay-upon-invoice-product-status' => static function (ContainerInterface $container): PayUponInvoiceProductStatus {
        return new PayUponInvoiceProductStatus($container->get('wcgateway.settings'), $container->get('api.endpoint.partners'), $container->get('pui.status-cache'), $container->get('settings.flag.is-connected'), $container->get('api.helper.failure-registry'));
    },
    'wcgateway.installments-product-status' => static function (ContainerInterface $container): InstallmentsProductStatus {
        return new InstallmentsProductStatus($container->get('wcgateway.settings'), $container->get('api.endpoint.partners'), $container->get('installments.status-cache'), $container->get('settings.flag.is-connected'), $container->get('api.helper.failure-registry'));
    },
    'wcgateway.pwc-product-status' => static function (ContainerInterface $container): PWCProductStatus {
        return new PWCProductStatus($container->get('wcgateway.settings'), $container->get('api.endpoint.partners'), $container->get('pwc.status-cache'), $container->get('settings.flag.is-connected'), $container->get('api.helper.failure-registry'));
    },
    'wcgateway.pay-upon-invoice' => static function (ContainerInterface $container): PayUponInvoice {
        return new PayUponInvoice($container->get('wcgateway.pay-upon-invoice-order-endpoint'), $container->get('woocommerce.logger.woocommerce'), $container->get('wcgateway.settings'), $container->get('settings.flag.is-connected'), $container->get('wcgateway.current-ppcp-settings-page-id'), $container->get('wcgateway.pay-upon-invoice-product-status'), $container->get('wcgateway.pay-upon-invoice-helper'), $container->get('wcgateway.checkout-helper'), $container->get('api.factory.capture'));
    },
    'wcgateway.oxxo' => static function (ContainerInterface $container): OXXO {
        return new OXXO($container->get('wcgateway.checkout-helper'), $container->get('wcgateway.asset_getter'), $container->get('ppcp.asset-version'), $container->get('api.endpoint.order'), $container->get('woocommerce.logger.woocommerce'), $container->get('api.factory.capture'));
    },
    'wcgateway.oxxo-gateway' => static function (ContainerInterface $container): OXXOGateway {
        return new OXXOGateway($container->get('api.endpoint.order'), $container->get('api.factory.purchase-unit'), $container->get('api.factory.shipping-preference'), $container->get('wcgateway.builder.experience-context'), $container->get('wcgateway.asset_getter'), $container->get('wcgateway.transaction-url-provider'), $container->get('settings.environment'), $container->get('woocommerce.logger.woocommerce'));
    },
    'wcgateway.logging.is-enabled' => static function (ContainerInterface $container): bool {
        $settings = $container->get('wcgateway.settings');
        // Check if logging was enabled in plugin settings.
        $is_enabled = $settings->has('logging_enabled') && $settings->get('logging_enabled');
        // If not enabled, check if plugin is in onboarding mode.
        if (!$is_enabled) {
            $state = $container->get('settings.connection-state');
            assert($state instanceof ConnectionState);
            $is_enabled = $state->is_onboarding();
        }
        /**
         * Whether the logging of the plugin errors/events is enabled.
         *
         * @param bool $is_enabled Whether the logging is enabled.
         */
        return apply_filters('woocommerce_paypal_payments_is_logging_enabled', $is_enabled);
    },
    'wcgateway.use-place-order-button' => function (ContainerInterface $container): bool {
        /**
         * Whether to use the standard "Place order" button with redirect to PayPal instead of the PayPal smart buttons.
         */
        return apply_filters('woocommerce_paypal_payments_use_place_order_button', \false);
    },
    'wcgateway.place-order-button-text' => function (ContainerInterface $container): string {
        /**
         * The text for the standard "Place order" button, when the "Place order" button mode is enabled.
         */
        return apply_filters('woocommerce_paypal_payments_place_order_button_text', __('Proceed to PayPal', 'woocommerce-paypal-payments'));
    },
    'wcgateway.place-order-button-description' => function (ContainerInterface $container): string {
        /**
         * The text for additional description, when the "Place order" button mode is enabled.
         */
        return apply_filters('woocommerce_paypal_payments_place_order_button_description', __('Clicking "Proceed to PayPal" will redirect you to PayPal to complete your purchase.', 'woocommerce-paypal-payments'));
    },
    'wcgateway.helper.vaulting-scope' => static function (ContainerInterface $container): bool {
        try {
            $token = $container->get('api.bearer')->bearer();
            return $token->vaulting_available();
        } catch (RuntimeException $exception) {
            return \false;
        }
    },
    'button.helper.vaulting-label' => static function (ContainerInterface $container): string {
        $vaulting_label = '';
        if (!$container->get('wcgateway.helper.vaulting-scope')) {
            $vaulting_label .= sprintf(
                // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
                __(' To use vaulting features, you must %1$senable vaulting on your account%2$s.', 'woocommerce-paypal-payments'),
                '<a
					href="https://docs.woocommerce.com/document/woocommerce-paypal-payments/#enable-vaulting-on-your-live-account"
					target="_blank"
				>',
                '</a>'
            );
        }
        $vaulting_label .= '<p class="description">';
        $vaulting_label .= sprintf(
            // translators: %1$s, %2$s, %3$s and %4$s are the opening and closing of HTML <a> tag.
            __('This will disable all %1$sPay Later%2$s features and %3$sAlternative Payment Methods%4$s on your site.', 'woocommerce-paypal-payments'),
            '<a
					href="https://woocommerce.com/document/woocommerce-paypal-payments/#pay-later"
					target="_blank"
				>',
            '</a>',
            '<a
					href="https://woocommerce.com/document/woocommerce-paypal-payments/#alternative-payment-methods"
					target="_blank"
				>',
            '</a>'
        );
        $vaulting_label .= '</p>';
        return $vaulting_label;
    },
    'wcgateway.settings.dcc-gateway-title.default' => static function (ContainerInterface $container): string {
        return did_action('init') ? __('Debit & Credit Cards', 'woocommerce-paypal-payments') : 'Debit & Credit Cards';
    },
    'wcgateway.settings.card_billing_data_mode.default' => static function (ContainerInterface $container): string {
        return $container->get('api.shop.is-latin-america') ? \WooCommerce\PayPalCommerce\WcGateway\CardBillingMode::MINIMAL_INPUT : \WooCommerce\PayPalCommerce\WcGateway\CardBillingMode::USE_WC;
    },
    'wcgateway.settings.card_billing_data_mode' => static function (ContainerInterface $container): string {
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof ContainerInterface);
        return $settings->has('card_billing_data_mode') ? (string) $settings->get('card_billing_data_mode') : $container->get('wcgateway.settings.card_billing_data_mode.default');
    },
    'wcgateway.settings.allow_card_button_gateway.default' => static function (ContainerInterface $container): bool {
        return $container->get('api.shop.is-latin-america');
    },
    'wcgateway.settings.allow_card_button_gateway' => static function (ContainerInterface $container): bool {
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof ContainerInterface);
        return apply_filters('woocommerce_paypal_payments_enable_standard_card_button_gateway_settings', $settings->has('allow_card_button_gateway') ? (bool) $settings->get('allow_card_button_gateway') : $container->get('wcgateway.settings.allow_card_button_gateway.default'));
    },
    'wcgateway.settings.has_enabled_separate_button_gateways' => static function (ContainerInterface $container): bool {
        return (bool) $container->get('wcgateway.settings.allow_card_button_gateway');
    },
    'wcgateway.settings.should-disable-fraudnet-checkbox' => static function (ContainerInterface $container): bool {
        $pui_helper = $container->get('wcgateway.pay-upon-invoice-helper');
        assert($pui_helper instanceof PayUponInvoiceHelper);
        if ($pui_helper->is_pui_gateway_enabled()) {
            return \true;
        }
        return \false;
    },
    'wcgateway.settings.fraudnet-label' => static function (ContainerInterface $container): string {
        $label = sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Manage online risk with %1$sFraudNet%2$s.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#fraudnet" target="_blank">',
            '</a>'
        );
        if ('DE' === $container->get('api.shop.country')) {
            $label .= '<br/>' . sprintf(
                // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
                __('Required when %1$sPay upon Invoice%2$s is used.', 'woocommerce-paypal-payments'),
                '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#pay-upon-invoice-PUI" target="_blank">',
                '</a>'
            );
        }
        return $label;
    },
    'wcgateway.enable-dcc-url-sandbox' => static function (ContainerInterface $container): string {
        return 'https://www.sandbox.paypal.com/bizsignup/entry?product=ppcp';
    },
    'wcgateway.enable-dcc-url-live' => static function (ContainerInterface $container): string {
        return 'https://www.paypal.com/bizsignup/entry?product=ppcp';
    },
    'wcgateway.enable-pui-url-sandbox' => static function (ContainerInterface $container): string {
        return 'https://www.sandbox.paypal.com/bizsignup/entry?country.x=DE&product=payment_methods&capabilities=PAY_UPON_INVOICE';
    },
    'wcgateway.enable-pui-url-live' => static function (ContainerInterface $container): string {
        return 'https://www.paypal.com/bizsignup/entry?country.x=DE&product=payment_methods&capabilities=PAY_UPON_INVOICE';
    },
    'wcgateway.enable-reference-transactions-url-sandbox' => static function (ContainerInterface $container): string {
        return 'https://www.sandbox.paypal.com/bizsignup/entry?product=ADVANCED_VAULTING';
    },
    'wcgateway.enable-reference-transactions-url-live' => static function (ContainerInterface $container): string {
        return 'https://www.paypal.com/bizsignup/entry?product=ADVANCED_VAULTING';
    },
    'wcgateway.settings.connection.dcc-status-text' => static function (ContainerInterface $container): string {
        $is_connected = $container->get('settings.flag.is-connected');
        if (!$is_connected) {
            return '';
        }
        $dcc_product_status = $container->get('wcgateway.helper.dcc-product-status');
        assert($dcc_product_status instanceof DCCProductStatus);
        $environment = $container->get('settings.environment');
        assert($environment instanceof Environment);
        $dcc_enabled = $dcc_product_status->is_active();
        $enabled_status_text = esc_html__('Status: Available', 'woocommerce-paypal-payments');
        $disabled_status_text = esc_html__('Status: Not yet enabled', 'woocommerce-paypal-payments');
        $dcc_button_text = $dcc_enabled ? esc_html__('Settings', 'woocommerce-paypal-payments') : esc_html__('Enable Advanced Card Payments', 'woocommerce-paypal-payments');
        $enable_dcc_url = $environment->current_environment_is(Environment::PRODUCTION) ? $container->get('wcgateway.enable-dcc-url-live') : $container->get('wcgateway.enable-dcc-url-sandbox');
        $dcc_button_url = $dcc_enabled ? admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-credit-card-gateway') : $enable_dcc_url;
        return sprintf('<p>%1$s %2$s</p><p><a target="%3$s" href="%4$s" class="button">%5$s</a></p>', $dcc_enabled ? $enabled_status_text : $disabled_status_text, $dcc_enabled ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no"></span>', $dcc_enabled ? '_self' : '_blank', esc_url($dcc_button_url), esc_html($dcc_button_text));
    },
    'wcgateway.settings.connection.reference-transactions-status-text' => static function (ContainerInterface $container): string {
        $environment = $container->get('settings.environment');
        assert($environment instanceof Environment);
        $reference_transaction_status = $container->get('api.reference-transaction-status');
        assert($reference_transaction_status instanceof ReferenceTransactionStatus);
        $enabled = $reference_transaction_status->reference_transaction_enabled();
        $enabled_status_text = esc_html__('Status: Available', 'woocommerce-paypal-payments');
        $disabled_status_text = esc_html__('Status: Not yet enabled', 'woocommerce-paypal-payments');
        $button_text = $enabled ? esc_html__('Settings', 'woocommerce-paypal-payments') : esc_html__('Enable saving PayPal & Venmo', 'woocommerce-paypal-payments');
        $enable_url = $environment->current_environment_is(Environment::PRODUCTION) ? $container->get('wcgateway.enable-reference-transactions-url-live') : $container->get('wcgateway.enable-reference-transactions-url-sandbox');
        $button_url = $enabled ? admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway#field-paypal_saved_payments') : $enable_url;
        return sprintf('<p>%1$s %2$s</p><p><a target="%3$s" href="%4$s" class="button">%5$s</a></p>', $enabled ? $enabled_status_text : $disabled_status_text, $enabled ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no"></span>', $enabled ? '_self' : '_blank', esc_url($button_url), esc_html($button_text));
    },
    'wcgateway.settings.connection.pui-status-text' => static function (ContainerInterface $container): string {
        $is_connected = $container->get('settings.flag.is-connected');
        if (!$is_connected) {
            return '';
        }
        $pui_product_status = $container->get('wcgateway.pay-upon-invoice-product-status');
        assert($pui_product_status instanceof PayUponInvoiceProductStatus);
        $environment = $container->get('settings.environment');
        assert($environment instanceof Environment);
        $pui_enabled = $pui_product_status->is_active();
        $enabled_status_text = esc_html__('Status: Available', 'woocommerce-paypal-payments');
        $disabled_status_text = esc_html__('Status: Not yet enabled', 'woocommerce-paypal-payments');
        $enable_pui_url = $environment->current_environment_is(Environment::PRODUCTION) ? $container->get('wcgateway.enable-pui-url-live') : $container->get('wcgateway.enable-pui-url-sandbox');
        $pui_button_url = $pui_enabled ? admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-pay-upon-invoice-gateway') : $enable_pui_url;
        $pui_button_text = $pui_enabled ? esc_html__('Settings', 'woocommerce-paypal-payments') : esc_html__('Enable Pay upon Invoice', 'woocommerce-paypal-payments');
        return sprintf('<p>%1$s %2$s</p><p><a target="%3$s" href="%4$s" class="button">%5$s</a></p>', $pui_enabled ? $enabled_status_text : $disabled_status_text, $pui_enabled ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no"></span>', $pui_enabled ? '_self' : '_blank', esc_url($pui_button_url), esc_html($pui_button_text));
    },
    'pui.status-cache' => static function (ContainerInterface $container): Cache {
        return new Cache('ppcp-paypal-pui-status-cache');
    },
    'installments.status-cache' => static function (ContainerInterface $container): Cache {
        return new Cache('ppcp-paypal-installments-status-cache');
    },
    'pwc.status-cache' => static function (ContainerInterface $container): Cache {
        return new Cache('ppcp-paypal-pwc-status-cache');
    },
    'dcc.status-cache' => static function (ContainerInterface $container): Cache {
        return new Cache('ppcp-paypal-dcc-status-cache');
    },
    'wcgateway.button.locations' => static function (ContainerInterface $container): array {
        return array('product' => 'Single Product', 'cart' => 'Classic Cart', 'checkout' => 'Classic Checkout', 'mini-cart' => 'Mini Cart');
    },
    'wcgateway.button.default-locations' => static function (ContainerInterface $container): array {
        $button_locations = $container->get('wcgateway.button.locations');
        unset($button_locations['mini-cart']);
        return array_keys($button_locations);
    },
    'wcgateway.button.recommended-styling-notice' => static function (ContainerInterface $container): string {
        if (CartCheckoutDetector::has_block_checkout()) {
            $block_checkout_page_string_html = '<a href="' . esc_url(wc_get_page_permalink('checkout')) . '">' . __('Checkout block', 'woocommerce-paypal-payments') . '</a>';
        } else {
            $block_checkout_page_string_html = __('Checkout block', 'woocommerce-paypal-payments');
        }
        $notice_content = sprintf(
            /* translators: %1$s: URL to the Checkout edit page. */
            __('<span class="highlight">Important:</span> The <code>Cart</code> & <code>Express Checkout</code> <strong>Smart Button Stylings</strong> may be controlled by the %1$s configuration.', 'woocommerce-paypal-payments'),
            $block_checkout_page_string_html
        );
        return '<div class="ppcp-notice ppcp-notice-warning"><p>' . $notice_content . '</p></div>';
    },
    'wcgateway.settings.pay-later.messaging-locations' => static function (ContainerInterface $container): array {
        $button_locations = $container->get('wcgateway.button.locations');
        unset($button_locations['mini-cart']);
        return array_merge($button_locations, array('shop' => did_action('init') ? __('Shop', 'woocommerce-paypal-payments') : 'Shop', 'home' => did_action('init') ? __('Home', 'woocommerce-paypal-payments') : 'Home'));
    },
    'wcgateway.settings.pay-later.default-messaging-locations' => static function (ContainerInterface $container): array {
        $locations = $container->get('wcgateway.settings.pay-later.messaging-locations');
        unset($locations['home']);
        return array_keys($locations);
    },
    'wcgateway.settings.pay-later.button-locations' => static function (ContainerInterface $container): array {
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        $button_locations = $container->get('wcgateway.button.locations');
        $smart_button_selected_locations = $settings->has('smart_button_locations') ? $settings->get('smart_button_locations') : array();
        return array_intersect_key($button_locations, array_flip($smart_button_selected_locations));
    },
    'wcgateway.settings.pay-later.default-button-locations' => static function (ContainerInterface $container): array {
        return $container->get('wcgateway.button.default-locations');
    },
    'wcgateway.ppcp-gateways' => static function (ContainerInterface $container): array {
        return array(PayPalGateway::ID, CreditCardGateway::ID, PayUponInvoiceGateway::ID, CardButtonGateway::ID, OXXOGateway::ID, AxoGateway::ID);
    },
    'wcgateway.gateway-repository' => static function (ContainerInterface $container): GatewayRepository {
        return new GatewayRepository($container->get('wcgateway.ppcp-gateways'));
    },
    'wcgateway.is-fraudnet-enabled' => static function (ContainerInterface $container): bool {
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        if (apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- feature flags use this convention
            'woocommerce.feature-flags.woocommerce_paypal_payments.settings_enabled',
            getenv('PCP_SETTINGS_ENABLED') === '1'
        )) {
            return \true;
        }
        return $settings->has('fraudnet_enabled') && $settings->get('fraudnet_enabled');
    },
    'wcgateway.fraudnet-assets' => function (ContainerInterface $container): FraudNetAssets {
        return new FraudNetAssets($container->get('wcgateway.asset_getter'), $container->get('ppcp.asset-version'), $container->get('wcgateway.fraudnet'), $container->get('settings.environment'), $container->get('wcgateway.settings'), $container->get('wcgateway.gateway-repository'), $container->get('session.handler'), $container->get('wcgateway.is-fraudnet-enabled'), $container->get('button.helper.context'));
    },
    'wcgateway.cli.settings.command' => function (ContainerInterface $container): SettingsCommand {
        return new SettingsCommand($container->get('wcgateway.settings'));
    },
    'wcgateway.display-manager' => SingletonDecorator::make(static function (ContainerInterface $container): DisplayManager {
        $settings = $container->get('wcgateway.settings');
        return new DisplayManager($settings);
    }),
    'wcgateway.wp-paypal-locales-map' => static function (ContainerInterface $container): array {
        return apply_filters('woocommerce_paypal_payments_button_locales', array('' => __('Browser language', 'woocommerce-paypal-payments'), 'ar_DZ' => __('Arabic (Algeria)', 'woocommerce-paypal-payments'), 'ar_BH' => __('Arabic (Bahrain)', 'woocommerce-paypal-payments'), 'ar_EG' => __('Arabic (Egypt)', 'woocommerce-paypal-payments'), 'ar_JO' => __('Arabic (Jordan)', 'woocommerce-paypal-payments'), 'ar_KW' => __('Arabic (Kuwait)', 'woocommerce-paypal-payments'), 'ar_MA' => __('Arabic (Morocco)', 'woocommerce-paypal-payments'), 'ar_SA' => __('Arabic (Saudi Arabia)', 'woocommerce-paypal-payments'), 'cs_CZ' => __('Czech', 'woocommerce-paypal-payments'), 'zh_CN' => __('Chinese (Simplified)', 'woocommerce-paypal-payments'), 'zh_HK' => __('Chinese (Hong Kong)', 'woocommerce-paypal-payments'), 'zh_TW' => __('Chinese (Traditional)', 'woocommerce-paypal-payments'), 'da_DK' => __('Danish', 'woocommerce-paypal-payments'), 'nl_NL' => __('Dutch', 'woocommerce-paypal-payments'), 'en_AU' => __('English (Australia)', 'woocommerce-paypal-payments'), 'en_GB' => __('English (United Kingdom)', 'woocommerce-paypal-payments'), 'en_US' => __('English (United States)', 'woocommerce-paypal-payments'), 'fi_FI' => __('Finnish', 'woocommerce-paypal-payments'), 'fr_CA' => __('French (Canada)', 'woocommerce-paypal-payments'), 'fr_FR' => __('French (France)', 'woocommerce-paypal-payments'), 'de_DE' => __('German (Germany)', 'woocommerce-paypal-payments'), 'de_CH' => __('German (Switzerland)', 'woocommerce-paypal-payments'), 'de_AT' => __('German (Austria)', 'woocommerce-paypal-payments'), 'de_LU' => __('German (Luxembourg)', 'woocommerce-paypal-payments'), 'el_GR' => __('Greek', 'woocommerce-paypal-payments'), 'he_IL' => __('Hebrew', 'woocommerce-paypal-payments'), 'hu_HU' => __('Hungarian', 'woocommerce-paypal-payments'), 'id_ID' => __('Indonesian', 'woocommerce-paypal-payments'), 'it_IT' => __('Italian', 'woocommerce-paypal-payments'), 'ja_JP' => __('Japanese', 'woocommerce-paypal-payments'), 'ko_KR' => __('Korean', 'woocommerce-paypal-payments'), 'no_NO' => __('Norwegian', 'woocommerce-paypal-payments'), 'es_ES' => __('Spanish (Spain)', 'woocommerce-paypal-payments'), 'es_MX' => __('Spanish (Mexico)', 'woocommerce-paypal-payments'), 'pl_PL' => __('Polish', 'woocommerce-paypal-payments'), 'pt_BR' => __('Portuguese (Brazil)', 'woocommerce-paypal-payments'), 'pt_PT' => __('Portuguese (Portugal)', 'woocommerce-paypal-payments'), 'ru_RU' => __('Russian', 'woocommerce-paypal-payments'), 'sk_SK' => __('Slovak', 'woocommerce-paypal-payments'), 'sv_SE' => __('Swedish', 'woocommerce-paypal-payments'), 'th_TH' => __('Thai', 'woocommerce-paypal-payments')));
    },
    'wcgateway.endpoint.capture-card-payment' => static function (ContainerInterface $container): CaptureCardPayment {
        return new CaptureCardPayment($container->get('api.host'), $container->get('api.bearer'), $container->get('api.factory.order'), $container->get('api.factory.purchase-unit'), $container->get('api.endpoint.order'), $container->get('session.handler'), $container->get('wc-subscriptions.helpers.real-time-account-updater'), $container->get('wcgateway.settings'), $container->get('woocommerce.logger.woocommerce'));
    },
    'wcgateway.settings.wc-tasks.simple-redirect-task-factory' => static function (): SimpleRedirectTaskFactoryInterface {
        return new SimpleRedirectTaskFactory();
    },
    'wcgateway.settings.wc-tasks.task-registrar' => static function (): TaskRegistrarInterface {
        return new TaskRegistrar();
    },
    'wcgateway.settings.wc-tasks.pay-later-task-config' => static function (ContainerInterface $container): array {
        $section_id = PayPalGateway::ID;
        $pay_later_tab_id = Settings::PAY_LATER_TAB_ID;
        if ($container->has('paylater-configurator.is-available') && $container->get('paylater-configurator.is-available')) {
            return array(array('id' => 'pay-later-messaging-task', 'title' => __('Configure PayPal Pay Later messaging', 'woocommerce-paypal-payments'), 'description' => __('Decide where you want dynamic Pay Later messaging to show up and how you want it to look on your site.', 'woocommerce-paypal-payments'), 'redirect_url' => admin_url("admin.php?page=wc-settings&tab=checkout&section={$section_id}&ppcp-tab={$pay_later_tab_id}")));
        }
        return array();
    },
    'wcgateway.settings.wc-tasks.connect-task-config' => static function (ContainerInterface $container): array {
        $is_connected = $container->get('settings.flag.is-connected');
        $is_current_country_send_only = $container->get('wcgateway.is-send-only-country');
        if (!$is_connected && !$is_current_country_send_only) {
            return array(array('id' => 'connect-to-paypal-task', 'title' => __('Connect PayPal to complete setup', 'woocommerce-paypal-payments'), 'description' => __('PayPal Payments is almost ready. To get started, connect your account with the Activate PayPal Payments button.', 'woocommerce-paypal-payments'), 'redirect_url' => admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=' . Settings::CONNECTION_TAB_ID)));
        }
        return array();
    },
    'wcgateway.settings.wc-tasks.working-capital-config' => static function (ContainerInterface $container): array {
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        $settings_provider = $container->get('settings.settings-provider');
        assert($settings_provider instanceof SettingsProvider);
        $is_working_capital_feature_flag_enabled = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- feature flags use this convention
            'woocommerce.feature-flags.woocommerce_paypal_payments.working_capital_enabled',
            \true
        );
        $is_working_capital_eligible = $container->get('api.merchant.country') === 'US' && $settings->has('stay_updated') && $settings->get('stay_updated');
        if (!$settings_provider->merchant_connected() || !$is_working_capital_feature_flag_enabled || !$is_working_capital_eligible) {
            return array();
        }
        return array(array('id' => 'ppcp-working-capital-task', 'title' => __('Fuel your business growth with a PayPal Working Capital loan. Check eligibility', 'woocommerce-paypal-payments'), 'description' => '', 'redirect_url' => 'https://www.paypal.com/us/business/financial-services/working-capital?partner_camp_id=woocommerce_ppwc'));
    },
    'wcgateway.settings.wc-tasks.task-config-services' => static function (): array {
        return array('wcgateway.settings.wc-tasks.pay-later-task-config', 'wcgateway.settings.wc-tasks.connect-task-config', 'wcgateway.settings.wc-tasks.working-capital-config');
    },
    /**
     * A configuration for simple redirect wc tasks.
     *
     * @returns array<array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     redirect_url: string
     * }>
     */
    'wcgateway.settings.wc-tasks.simple-redirect-tasks-config' => static function (ContainerInterface $container): array {
        $list_of_config = array();
        $task_config_services = $container->get('wcgateway.settings.wc-tasks.task-config-services');
        foreach ($task_config_services as $service_id) {
            if ($container->has($service_id)) {
                $task_config = $container->get($service_id);
                $list_of_config = array_merge($list_of_config, $task_config);
            }
        }
        return $list_of_config;
    },
    /**
     * Retrieves the list of simple redirect task instances.
     *
     * @returns SimpleRedirectTask[]
     */
    'wcgateway.settings.wc-tasks.simple-redirect-tasks' => static function (ContainerInterface $container): array {
        $simple_redirect_tasks_config = $container->get('wcgateway.settings.wc-tasks.simple-redirect-tasks-config');
        $simple_redirect_task_factory = $container->get('wcgateway.settings.wc-tasks.simple-redirect-task-factory');
        assert($simple_redirect_task_factory instanceof SimpleRedirectTaskFactoryInterface);
        $simple_redirect_tasks = array();
        foreach ($simple_redirect_tasks_config as $config) {
            $id = $config['id'] ?? '';
            $title = $config['title'] ?? '';
            $description = $config['description'] ?? '';
            $redirect_url = $config['redirect_url'] ?? '';
            $simple_redirect_tasks[] = $simple_redirect_task_factory->create_task($id, $title, $description, $redirect_url);
        }
        return $simple_redirect_tasks;
    },
    'wcgateway.settings.inbox-note-factory' => static function (): InboxNoteFactory {
        return new InboxNoteFactory();
    },
    'wcgateway.settings.inbox-note-registrar' => static function (ContainerInterface $container): InboxNoteRegistrar {
        return new InboxNoteRegistrar($container->get('wcgateway.settings.inbox-notes'), $container->get('ppcp.base-name'));
    },
    /**
     * Retrieves the list of inbox note instances.
     *
     * @returns InboxNoteInterface[]
     */
    'wcgateway.settings.inbox-notes' => static function (ContainerInterface $container): array {
        $inbox_note_factory = $container->get('wcgateway.settings.inbox-note-factory');
        assert($inbox_note_factory instanceof InboxNoteFactory);
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        $settings_model = $container->get('settings.data.settings');
        assert($settings_model instanceof SettingsModel);
        $messages_apply = $container->get('button.helper.messages-apply');
        assert($messages_apply instanceof MessagesApply);
        $settings_provider = $container->get('settings.settings-provider');
        assert($settings_provider instanceof SettingsProvider);
        $is_working_capital_feature_flag_enabled = apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- feature flags use this convention
            'woocommerce.feature-flags.woocommerce_paypal_payments.working_capital_enabled',
            \true
        );
        $stay_updated = SettingsModule::should_use_the_old_ui() ? $settings->has('stay_updated') && $settings->get('stay_updated') : $settings_model->get_stay_updated();
        $is_working_capital_eligible = $container->get('api.merchant.country') === 'US' && $stay_updated;
        $message = sprintf(
            // translators: %1$s is the URL for the startup guide.
            __('We\'ve redesigned the settings for better performance and usability. This improved design will be the default for all WooCommerce installations to enjoy faster navigation, cleaner organization, and improved performance. Check out the <a href="%1$s" target="_blank">Startup Guide</a>, then click <a href="#" name="settings-switch-ui"><strong>Switch to New Settings</strong></a> to activate it.', 'woocommerce-paypal-payments'),
            'https://woocommerce.com/document/woocommerce-paypal-payments/paypal-payments-startup-guide/'
        );
        return array($inbox_note_factory->create_note(__('PayPal Working Capital', 'woocommerce-paypal-payments'), __('Business loans from $1k to $230k for first-time borrowers. Looking to fuel your business growth? With a PayPal Working Capital loan, approved loans are funded in minutes and repaid as a share of your sales. Minimum payment required every 90 days. The lender for PayPal Working Capital is WebBank.', 'woocommerce-paypal-payments'), Note::E_WC_ADMIN_NOTE_INFORMATIONAL, 'ppcp-working-capital-inbox-note', Note::E_WC_ADMIN_NOTE_UNACTIONED, $settings_provider->merchant_connected() && $is_working_capital_feature_flag_enabled && $is_working_capital_eligible, new InboxNoteAction('learn_more', __('Learn More', 'woocommerce-paypal-payments'), 'https://www.paypal.com/us/business/financial-services/working-capital?partner_camp_id=woocommerce_ppwc', Note::E_WC_ADMIN_NOTE_UNACTIONED, \true)), $inbox_note_factory->create_note(__('📢 Important: New PayPal Payments settings UI becoming default soon!', 'woocommerce-paypal-payments'), $message, Note::E_WC_ADMIN_NOTE_INFORMATIONAL, 'ppcp-settings-migration-inbox-note', Note::E_WC_ADMIN_NOTE_UNACTIONED, SettingsModule::should_use_the_old_ui(), new InboxNoteAction('switch_to_new_settings', __('Switch to New Settings', 'woocommerce-paypal-payments'), admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway'), Note::E_WC_ADMIN_NOTE_UNACTIONED, \true)));
    },
    'wcgateway.void-button.assets' => function (ContainerInterface $container): VoidButtonAssets {
        return new VoidButtonAssets($container->get('wcgateway.asset_getter'), $container->get('ppcp.asset-version'), $container->get('api.endpoint.order'), $container->get('wcgateway.processor.refunds'));
    },
    'wcgateway.void-button.endpoint' => function (ContainerInterface $container): VoidOrderEndpoint {
        return new VoidOrderEndpoint($container->get('button.request-data'), $container->get('api.endpoint.order'), $container->get('wcgateway.processor.refunds'), $container->get('woocommerce.logger.woocommerce'));
    },
    'wcgateway.settings.admin-settings-enabled' => static function (ContainerInterface $container): bool {
        return $container->has('settings.asset_getter') && !SettingsModule::should_use_the_old_ui();
    },
    'wcgateway.contact-module.eligibility.check' => static function (ContainerInterface $container): callable {
        $feature_enabled = (bool) apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- feature flags use this convention
            'woocommerce.feature-flags.woocommerce_paypal_payments.contact_module_enabled',
            getenv('PCP_CONTACT_MODULE_ENABLED') !== '0'
        );
        /**
         * Decides, whether the current merchant is eligible to use the
         * "Contact Module" feature on this site.
         */
        return static function () use ($feature_enabled, $container) {
            if (!$feature_enabled) {
                return \false;
            }
            $details = $container->get('settings.merchant-details');
            assert($details instanceof MerchantDetails);
            $enable_contact_module = 'US' === $details->get_merchant_country();
            /**
             * The contact module is enabled for US-based merchants by default.
             * This filter provides the official way to opt-out of using it on this store.
             */
            return (bool) apply_filters('woocommerce_paypal_payments_contact_module_enabled', $enable_contact_module);
        };
    },
    /**
     * Returns a centralized list of feature eligibility checks.
     *
     * This is a helper service which is used by the `MerchantDetails` class and
     * should not be directly accessed.
     */
    'wcgateway.feature-eligibility.list' => static function (ContainerInterface $container): array {
        return array(FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO => $container->get('save-payment-methods.eligibility.check'), FeaturesDefinition::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS => $container->get('card-fields.eligibility.check'), FeaturesDefinition::FEATURE_GOOGLE_PAY => $container->get('googlepay.eligibility.check'), FeaturesDefinition::FEATURE_APPLE_PAY => $container->get('applepay.eligibility.check'), FeaturesDefinition::FEATURE_CONTACT_MODULE => $container->get('wcgateway.contact-module.eligibility.check'));
    },
    /**
     * Returns a prefix for the site, ensuring the same site always gets the same prefix (unless the URL changes).
     */
    'wcgateway.settings.invoice-prefix' => static function (ContainerInterface $container): string {
        $site_url = get_site_url(get_current_blog_id());
        $hash = md5($site_url);
        $letters = preg_replace('~\d~', '', $hash) ?? '';
        $prefix = substr($letters, 0, 6);
        return $prefix ? $prefix . '-' : '';
    },
    /**
     * Returns random 6 characters length alphabetic prefix, followed by a hyphen.
     */
    'wcgateway.settings.invoice-prefix-random' => static function (ContainerInterface $container): string {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $prefix = '';
        for ($i = 0; $i < 6; $i++) {
            $prefix .= $characters[wp_rand(0, strlen($characters) - 1)];
        }
        return $prefix . '-';
    },
    'wcgateway.store-api.endpoint.cart' => static function (ContainerInterface $container): CartEndpoint {
        return new CartEndpoint($container->get('wcgateway.store-api.factory.cart'), $container->get('woocommerce.logger.woocommerce'));
    },
    'wcgateway.store-api.factory.cart' => static function (ContainerInterface $container): CartFactory {
        return new CartFactory($container->get('wcgateway.store-api.factory.cart-totals'), $container->get('wcgateway.store-api.factory.shipping-rates'));
    },
    'wcgateway.store-api.factory.cart-totals' => static function (ContainerInterface $container): CartTotalsFactory {
        return new CartTotalsFactory($container->get('wcgateway.store-api.factory.money'));
    },
    'wcgateway.store-api.factory.shipping-rates' => static function (ContainerInterface $container): ShippingRatesFactory {
        return new ShippingRatesFactory($container->get('wcgateway.store-api.factory.money'));
    },
    'wcgateway.store-api.factory.money' => static function (ContainerInterface $container): MoneyFactory {
        return new MoneyFactory();
    },
    'wcgateway.shipping.callback.endpoint' => static function (ContainerInterface $container): ShippingCallbackEndpoint {
        return new ShippingCallbackEndpoint($container->get('wcgateway.store-api.endpoint.cart'), $container->get('api.factory.amount'), $container->get('woocommerce.logger.woocommerce'));
    },
    'wcgateway.shipping.callback.factory.url' => static function (ContainerInterface $container): ShippingCallbackUrlFactory {
        return new ShippingCallbackUrlFactory($container->get('wcgateway.store-api.endpoint.cart'), $container->get('wcgateway.shipping.callback.endpoint'));
    },
    'wcgateway.server-side-shipping-callback-enabled' => static function (ContainerInterface $container): bool {
        // SSSC depends on Woo's Store API, which currently doesn't work with plain permalinks because of the rest_get_url_prefix bug.
        $has_plain_permalinks = empty(get_option('permalink_structure'));
        $last_webhook_storage = $container->get('webhook.last-webhook-storage');
        assert($last_webhook_storage instanceof WebhookEventStorage);
        // Not directly related, but if webhooks are not arriving then SSSC probably is not accessible too.
        $webhooks_working = !$last_webhook_storage->is_empty();
        $enabled = getenv('PCP_SERVER_SIDE_SHIPPING_CALLBACK_ENABLED') !== '0' && !$has_plain_permalinks && $webhooks_working;
        return apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
            'woocommerce.feature-flags.woocommerce_paypal_payments.server_side_shipping_callback_enabled',
            $enabled
        );
    },
    'wcgateway.appswitch-enabled' => static function (ContainerInterface $container): bool {
        return apply_filters(
            // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
            'woocommerce.feature-flags.woocommerce_paypal_payments.appswitch_enabled',
            getenv('PCP_APPSWITCH_ENABLED') !== '0'
        );
    },
);
