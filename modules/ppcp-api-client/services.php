<?php

/**
 * The services of the API client.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\ClientCredentials;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\ConnectBearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\SdkClientToken;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\UserIdToken;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingPlans;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\CatalogProducts;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\IdentityToken;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\LoginSeller;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\Orders;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnerReferrals;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentMethodTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AddressFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\BillingCycleFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CaptureFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CardAuthenticationResultFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ContactPreferenceFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExchangeRateFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\FraudProcessorResponseFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ItemFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\MoneyFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PatchCollectionFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayeeFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentPreferencesFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentsFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenActionLinksFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PlanFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PlatformFeeFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ProductFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\RefundFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\RefundPayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ReturnUrlFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\SellerPayableBreakdownFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\SellerReceivableBreakdownFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\SellerStatusFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingOptionFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookEventFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\ApiClient\Helper\OrderHelper;
use WooCommerce\PayPalCommerce\ApiClient\Helper\OrderTransient;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PartnerAttribution;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PaymentLevelEligibility;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PaymentLevelHelper;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PurchaseUnitSanitizer;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ReferenceTransactionStatus;
use WooCommerce\PayPalCommerce\ApiClient\Repository\CustomerRepository;
use WooCommerce\PayPalCommerce\ApiClient\Repository\OrderRepository;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PayeeRepository;
use WooCommerce\PayPalCommerce\ApiClient\VaultV2\PaymentTokenEndpoint;
use WooCommerce\PayPalCommerce\Common\Pattern\SingletonDecorator;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Enum\InstallationPathEnum;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\EnvironmentConfig;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
return array(
    'api.host' => static function (ContainerInterface $container): string {
        $environment = $container->get('settings.environment');
        assert($environment instanceof Environment);
        if ($environment->is_sandbox()) {
            return (string) $container->get('api.sandbox-host');
        }
        return (string) $container->get('api.production-host');
    },
    'api.paypal-host' => function (ContainerInterface $container): string {
        return PAYPAL_API_URL;
    },
    // It seems this 'api.paypal-website-url' key is always overridden in ppcp-onboarding/services.php.
    'api.paypal-website-url' => function (ContainerInterface $container): string {
        return PAYPAL_URL;
    },
    'api.factory.paypal-checkout-url' => function (ContainerInterface $container): callable {
        return function (string $id) use ($container): string {
            return $container->get('api.paypal-website-url') . '/checkoutnow?token=' . $id;
        };
    },
    'api.partner_merchant_id' => static function (): string {
        return '';
    },
    'api.merchant_email' => function (): string {
        return '';
    },
    'api.merchant_id' => function (): string {
        return '';
    },
    'api.key' => static function (): string {
        return '';
    },
    'api.secret' => static function (): string {
        return '';
    },
    'api.prefix' => static function (): string {
        return 'WC-';
    },
    'api.bearer' => static function (ContainerInterface $container): Bearer {
        $is_connected = $container->get('settings.flag.is-connected');
        if (!$is_connected) {
            return new ConnectBearer();
        }
        return new PayPalBearer($container->get('api.paypal-bearer-cache'), $container->get('api.host'), $container->get('api.key'), $container->get('api.secret'), $container->get('woocommerce.logger.woocommerce'), $container->get('wcgateway.settings'));
    },
    'api.endpoint.partners' => static function (ContainerInterface $container): PartnersEndpoint {
        return new PartnersEndpoint($container->get('api.host'), $container->get('api.bearer'), $container->get('woocommerce.logger.woocommerce'), $container->get('api.factory.sellerstatus'), $container->get('api.partner_merchant_id'), $container->get('api.merchant_id'), $container->get('api.helper.failure-registry'));
    },
    'api.factory.sellerstatus' => static function (ContainerInterface $container): SellerStatusFactory {
        return new SellerStatusFactory();
    },
    'vault-v2.endpoint.payment-token' => static function (ContainerInterface $container): PaymentTokenEndpoint {
        return new PaymentTokenEndpoint($container->get('api.host'), $container->get('api.bearer'), $container->get('api.factory.payment-token'), $container->get('api.factory.payment-token-action-links'), $container->get('woocommerce.logger.woocommerce'), $container->get('api.repository.customer'));
    },
    'api.endpoint.payment-tokens' => static function (ContainerInterface $container): PaymentTokensEndpoint {
        return new PaymentTokensEndpoint($container->get('api.host'), $container->get('api.bearer'), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.endpoint.webhook' => static function (ContainerInterface $container): WebhookEndpoint {
        return new WebhookEndpoint($container->get('api.host'), $container->get('api.bearer'), $container->get('api.factory.webhook'), $container->get('api.factory.webhook-event'), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.endpoint.partner-referrals' => static function (ContainerInterface $container): PartnerReferrals {
        return new PartnerReferrals($container->get('api.host'), $container->get('api.bearer'), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.endpoint.partner-referrals-sandbox' => static function (ContainerInterface $container): PartnerReferrals {
        return new PartnerReferrals(CONNECT_WOO_SANDBOX_URL, new ConnectBearer(), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.endpoint.partner-referrals-production' => static function (ContainerInterface $container): PartnerReferrals {
        return new PartnerReferrals(CONNECT_WOO_URL, new ConnectBearer(), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.endpoint.identity-token' => static function (ContainerInterface $container): IdentityToken {
        $logger = $container->get('woocommerce.logger.woocommerce');
        $settings = $container->get('wcgateway.settings');
        $customer_repository = $container->get('api.repository.customer');
        return new IdentityToken($container->get('api.host'), $container->get('api.bearer'), $logger, $settings, $customer_repository);
    },
    'api.endpoint.payments' => static function (ContainerInterface $container): PaymentsEndpoint {
        $authorizations_factory = $container->get('api.factory.authorization');
        $capture_factory = $container->get('api.factory.capture');
        $logger = $container->get('woocommerce.logger.woocommerce');
        return new PaymentsEndpoint($container->get('api.host'), $container->get('api.bearer'), $authorizations_factory, $capture_factory, $logger);
    },
    'api.endpoint.login-seller' => static function (ContainerInterface $container): LoginSeller {
        $logger = $container->get('woocommerce.logger.woocommerce');
        return new LoginSeller($container->get('api.paypal-host'), $container->get('api.partner_merchant_id'), $logger);
    },
    'api.endpoint.order' => static function (ContainerInterface $container): OrderEndpoint {
        $order_factory = $container->get('api.factory.order');
        $patch_collection_factory = $container->get('api.factory.patch-collection-factory');
        $logger = $container->get('woocommerce.logger.woocommerce');
        $session_handler = $container->get('session.handler');
        assert($session_handler instanceof SessionHandler);
        $bn_code = $session_handler->bn_code();
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        $intent = $settings->has('intent') && strtoupper((string) $settings->get('intent')) === 'AUTHORIZE' ? 'AUTHORIZE' : 'CAPTURE';
        $subscription_helper = $container->get('wc-subscriptions.helper');
        return new OrderEndpoint($container->get('api.host'), $container->get('api.bearer'), $order_factory, $patch_collection_factory, $intent, $logger, $subscription_helper, $container->get('wcgateway.is-fraudnet-enabled'), $container->get('wcgateway.fraudnet'), $bn_code);
    },
    'api.endpoint.orders' => static function (ContainerInterface $container): Orders {
        return new Orders($container->get('api.host'), $container->get('api.bearer'), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.reference-transaction-status' => static fn(ContainerInterface $container): ReferenceTransactionStatus => new ReferenceTransactionStatus($container->get('api.endpoint.partners'), $container->get('api.reference-transaction-status-cache')),
    'api.endpoint.catalog-products' => static function (ContainerInterface $container): CatalogProducts {
        return new CatalogProducts($container->get('api.host'), $container->get('api.bearer'), $container->get('api.factory.product'), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.endpoint.billing-plans' => static function (ContainerInterface $container): BillingPlans {
        return new BillingPlans($container->get('api.host'), $container->get('api.bearer'), $container->get('api.factory.billing-cycle'), $container->get('api.factory.plan'), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.endpoint.billing-subscriptions' => static function (ContainerInterface $container): BillingSubscriptions {
        return new BillingSubscriptions($container->get('api.host'), $container->get('api.bearer'), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.endpoint.payment-method-tokens' => static function (ContainerInterface $container): PaymentMethodTokensEndpoint {
        return new PaymentMethodTokensEndpoint($container->get('api.host'), $container->get('api.bearer'), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.repository.partner-referrals-data' => static function (ContainerInterface $container): PartnerReferralsData {
        $dcc_applies = $container->get('api.helpers.dccapplies');
        return new PartnerReferralsData($dcc_applies);
    },
    'api.repository.payee' => static function (ContainerInterface $container): PayeeRepository {
        $merchant_email = $container->get('api.merchant_email');
        $merchant_id = $container->get('api.merchant_id');
        return new PayeeRepository($merchant_email, $merchant_id);
    },
    'api.repository.customer' => static function (ContainerInterface $container): CustomerRepository {
        $prefix = $container->get('api.prefix');
        return new CustomerRepository($prefix);
    },
    'api.repository.order' => static function (ContainerInterface $container): OrderRepository {
        return new OrderRepository($container->get('api.endpoint.order'));
    },
    'api.factory.contact-preference' => static function (ContainerInterface $container): ContactPreferenceFactory {
        if ($container->has('settings.data.settings')) {
            $settings = $container->get('settings.data.settings');
            assert($settings instanceof SettingsModel);
            $contact_module_active = $settings->get_enable_contact_module();
        } else {
            // #legacy-ui: Auto-enable the feature; can be disabled via eligibility hook.
            $contact_module_active = \true;
        }
        return new ContactPreferenceFactory($contact_module_active, $container->get('settings.merchant-details'));
    },
    'api.factory.payment-token' => static function (ContainerInterface $container): PaymentTokenFactory {
        return new PaymentTokenFactory();
    },
    'api.factory.payment-token-action-links' => static function (ContainerInterface $container): PaymentTokenActionLinksFactory {
        return new PaymentTokenActionLinksFactory();
    },
    'api.factory.webhook' => static function (ContainerInterface $container): WebhookFactory {
        return new WebhookFactory();
    },
    'api.factory.webhook-event' => static function (ContainerInterface $container): WebhookEventFactory {
        return new WebhookEventFactory();
    },
    'api.factory.capture' => static function (ContainerInterface $container): CaptureFactory {
        $amount_factory = $container->get('api.factory.amount');
        return new CaptureFactory($amount_factory, $container->get('api.factory.seller-receivable-breakdown'), $container->get('api.factory.fraud-processor-response'));
    },
    'api.factory.refund' => static function (ContainerInterface $container): RefundFactory {
        $amount_factory = $container->get('api.factory.amount');
        return new RefundFactory($amount_factory, $container->get('api.factory.seller-payable-breakdown'), $container->get('api.factory.refund_payer'));
    },
    'api.factory.purchase-unit' => static function (ContainerInterface $container): PurchaseUnitFactory {
        $amount_factory = $container->get('api.factory.amount');
        $item_factory = $container->get('api.factory.item');
        $shipping_factory = $container->get('api.factory.shipping');
        $payments_factory = $container->get('api.factory.payments');
        $prefix = $container->get('api.prefix');
        $soft_descriptor = $container->get('wcgateway.soft-descriptor');
        $sanitizer = $container->get('api.helper.purchase-unit-sanitizer');
        return new PurchaseUnitFactory($amount_factory, $item_factory, $shipping_factory, $payments_factory, $container->get('api.helpers.paymentLevelHelper'), $container->get('api.helpers.paymentLevelEligibility'), $container->get('settings.settings-provider'), $prefix, $soft_descriptor, $sanitizer);
    },
    'api.factory.patch-collection-factory' => static function (ContainerInterface $container): PatchCollectionFactory {
        return new PatchCollectionFactory();
    },
    'api.factory.payee' => static function (ContainerInterface $container): PayeeFactory {
        return new PayeeFactory();
    },
    'api.factory.item' => static function (ContainerInterface $container): ItemFactory {
        return new ItemFactory($container->get('api.shop.currency.getter'));
    },
    'api.factory.shipping' => static function (ContainerInterface $container): ShippingFactory {
        return new ShippingFactory($container->get('api.factory.address'), $container->get('api.factory.shipping-option'));
    },
    'api.factory.return-url' => static function (ContainerInterface $container): ReturnUrlFactory {
        return new ReturnUrlFactory();
    },
    'api.factory.shipping-preference' => static function (ContainerInterface $container): ShippingPreferenceFactory {
        return new ShippingPreferenceFactory();
    },
    'api.factory.shipping-option' => static function (ContainerInterface $container): ShippingOptionFactory {
        return new ShippingOptionFactory($container->get('api.factory.money'));
    },
    'api.factory.amount' => static function (ContainerInterface $container): AmountFactory {
        $item_factory = $container->get('api.factory.item');
        return new AmountFactory($item_factory, $container->get('api.factory.money'), $container->get('api.shop.currency.getter'));
    },
    'api.factory.money' => static function (ContainerInterface $container): MoneyFactory {
        return new MoneyFactory();
    },
    'api.factory.payer' => static function (ContainerInterface $container): PayerFactory {
        $address_factory = $container->get('api.factory.address');
        return new PayerFactory($address_factory);
    },
    'api.factory.refund_payer' => static function (ContainerInterface $container): RefundPayerFactory {
        return new RefundPayerFactory();
    },
    'api.factory.address' => static function (ContainerInterface $container): AddressFactory {
        return new AddressFactory();
    },
    'api.factory.order' => static function (ContainerInterface $container): OrderFactory {
        $purchase_unit_factory = $container->get('api.factory.purchase-unit');
        $payer_factory = $container->get('api.factory.payer');
        return new OrderFactory($purchase_unit_factory, $payer_factory);
    },
    'api.factory.payments' => static function (ContainerInterface $container): PaymentsFactory {
        $authorizations_factory = $container->get('api.factory.authorization');
        $capture_factory = $container->get('api.factory.capture');
        $refund_factory = $container->get('api.factory.refund');
        return new PaymentsFactory($authorizations_factory, $capture_factory, $refund_factory);
    },
    'api.factory.authorization' => static function (ContainerInterface $container): AuthorizationFactory {
        return new AuthorizationFactory($container->get('api.factory.fraud-processor-response'));
    },
    'api.factory.exchange-rate' => static function (ContainerInterface $container): ExchangeRateFactory {
        return new ExchangeRateFactory();
    },
    'api.factory.platform-fee' => static function (ContainerInterface $container): PlatformFeeFactory {
        return new PlatformFeeFactory($container->get('api.factory.money'), $container->get('api.factory.payee'));
    },
    'api.factory.seller-receivable-breakdown' => static function (ContainerInterface $container): SellerReceivableBreakdownFactory {
        return new SellerReceivableBreakdownFactory($container->get('api.factory.money'), $container->get('api.factory.exchange-rate'), $container->get('api.factory.platform-fee'));
    },
    'api.factory.seller-payable-breakdown' => static function (ContainerInterface $container): SellerPayableBreakdownFactory {
        return new SellerPayableBreakdownFactory($container->get('api.factory.money'), $container->get('api.factory.platform-fee'));
    },
    'api.factory.fraud-processor-response' => static function (ContainerInterface $container): FraudProcessorResponseFactory {
        return new FraudProcessorResponseFactory();
    },
    'api.factory.product' => static function (ContainerInterface $container): ProductFactory {
        return new ProductFactory();
    },
    'api.factory.billing-cycle' => static function (ContainerInterface $container): BillingCycleFactory {
        return new BillingCycleFactory($container->get('api.shop.currency.getter'));
    },
    'api.factory.payment-preferences' => static function (ContainerInterface $container): PaymentPreferencesFactory {
        return new PaymentPreferencesFactory($container->get('api.shop.currency.getter'));
    },
    'api.factory.plan' => static function (ContainerInterface $container): PlanFactory {
        return new PlanFactory($container->get('api.factory.billing-cycle'), $container->get('api.factory.payment-preferences'));
    },
    'api.factory.card-authentication-result-factory' => static function (ContainerInterface $container): CardAuthenticationResultFactory {
        return new CardAuthenticationResultFactory();
    },
    'api.helpers.dccapplies' => static function (ContainerInterface $container): DccApplies {
        return new DccApplies($container->get('api.dcc-supported-country-currency-matrix'), $container->get('api.dcc-supported-country-card-matrix'), $container->get('api.shop.currency.getter'), $container->get('api.merchant.country'));
    },
    'api.shop.currency.getter' => static function (ContainerInterface $container): CurrencyGetter {
        return new CurrencyGetter();
    },
    'api.shop.country' => static function (ContainerInterface $container): string {
        $location = wc_get_base_location();
        return $location['country'];
    },
    'api.shop.is-psd2-country' => static function (ContainerInterface $container): bool {
        return in_array($container->get('api.shop.country'), $container->get('api.psd2-countries'), \true);
    },
    'api.shop.is-currency-supported' => static function (ContainerInterface $container): bool {
        return in_array($container->get('api.shop.currency.getter')->get(), $container->get('api.supported-currencies'), \true);
    },
    'api.shop.is-latin-america' => static function (ContainerInterface $container): bool {
        return in_array($container->get('api.shop.country'), array('AI', 'AG', 'AR', 'AW', 'BS', 'BB', 'BZ', 'BM', 'BO', 'BR', 'VG', 'KY', 'CL', 'CO', 'CR', 'DM', 'DO', 'EC', 'SV', 'FK', 'GF', 'GD', 'GP', 'GT', 'GY', 'HN', 'JM', 'MQ', 'MX', 'MS', 'AN', 'NI', 'PA', 'PY', 'PE', 'KN', 'LC', 'PM', 'VC', 'SR', 'TT', 'TC', 'UY', 'VE'), \true);
    },
    /**
     * Currencies supported by PayPal.
     *
     * From https://developer.paypal.com/docs/reports/reference/paypal-supported-currencies/
     */
    'api.supported-currencies' => static function (ContainerInterface $container): array {
        return array('AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'THB', 'USD');
    },
    /**
     * The matrix which countries and currency combinations can be used for DCC.
     */
    'api.dcc-supported-country-currency-matrix' => static function (ContainerInterface $container): array {
        $default_currencies = apply_filters('woocommerce_paypal_payments_supported_currencies', array('AUD', 'BRL', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'HKD', 'GBP', 'HUF', 'ILS', 'JPY', 'MXN', 'NOK', 'NZD', 'PHP', 'PLN', 'SGD', 'SEK', 'THB', 'TWD', 'USD'));
        /**
         * Returns which countries and currency combinations can be used for DCC.
         */
        return apply_filters('woocommerce_paypal_payments_supported_country_currency_matrix', array('AU' => $default_currencies, 'AT' => $default_currencies, 'BE' => $default_currencies, 'BG' => $default_currencies, 'CA' => $default_currencies, 'CN' => $default_currencies, 'CY' => $default_currencies, 'CZ' => $default_currencies, 'DK' => $default_currencies, 'EE' => $default_currencies, 'FI' => $default_currencies, 'FR' => $default_currencies, 'DE' => $default_currencies, 'GR' => $default_currencies, 'HK' => $default_currencies, 'HU' => $default_currencies, 'IE' => $default_currencies, 'IT' => $default_currencies, 'JP' => $default_currencies, 'LV' => $default_currencies, 'LI' => $default_currencies, 'LT' => $default_currencies, 'LU' => $default_currencies, 'MT' => $default_currencies, 'MX' => array('MXN'), 'NL' => $default_currencies, 'PL' => $default_currencies, 'PT' => $default_currencies, 'RO' => $default_currencies, 'SK' => $default_currencies, 'SG' => $default_currencies, 'SI' => $default_currencies, 'ES' => $default_currencies, 'SE' => $default_currencies, 'GB' => $default_currencies, 'US' => $default_currencies, 'NO' => $default_currencies, 'YT' => $default_currencies, 'RE' => $default_currencies, 'GP' => $default_currencies, 'GF' => $default_currencies, 'MQ' => $default_currencies));
    },
    /**
     * Which countries support which credit cards. Empty credit card arrays mean no restriction on currency.
     */
    'api.dcc-supported-country-card-matrix' => static function (ContainerInterface $container): array {
        $mastercard_visa_amex = array('mastercard' => array(), 'visa' => array(), 'amex' => array());
        /**
         * Returns which countries support which credit cards. Empty credit card arrays mean no restriction on currency.
         */
        return apply_filters('woocommerce_paypal_payments_supported_country_card_matrix', array(
            'AU' => array('mastercard' => array(), 'visa' => array(), 'amex' => array('AUD')),
            'AT' => $mastercard_visa_amex,
            'BE' => $mastercard_visa_amex,
            'BG' => $mastercard_visa_amex,
            'CN' => array('mastercard' => array(), 'visa' => array()),
            'CY' => $mastercard_visa_amex,
            'CZ' => $mastercard_visa_amex,
            'DE' => $mastercard_visa_amex,
            'DK' => $mastercard_visa_amex,
            'EE' => $mastercard_visa_amex,
            'ES' => $mastercard_visa_amex,
            'FI' => $mastercard_visa_amex,
            'FR' => $mastercard_visa_amex,
            'GB' => $mastercard_visa_amex,
            'GR' => $mastercard_visa_amex,
            'HK' => $mastercard_visa_amex,
            'HU' => $mastercard_visa_amex,
            'IE' => $mastercard_visa_amex,
            'IT' => $mastercard_visa_amex,
            'US' => array('mastercard' => array(), 'visa' => array(), 'amex' => array('USD'), 'discover' => array('USD')),
            'CA' => array('mastercard' => array(), 'visa' => array(), 'amex' => array('CAD', 'USD'), 'jcb' => array('CAD')),
            'LI' => $mastercard_visa_amex,
            'LT' => $mastercard_visa_amex,
            'LU' => $mastercard_visa_amex,
            'LV' => $mastercard_visa_amex,
            'MT' => $mastercard_visa_amex,
            'MX' => $mastercard_visa_amex,
            'NL' => $mastercard_visa_amex,
            'NO' => $mastercard_visa_amex,
            'PL' => $mastercard_visa_amex,
            'PT' => $mastercard_visa_amex,
            'RO' => $mastercard_visa_amex,
            'SE' => $mastercard_visa_amex,
            'SI' => $mastercard_visa_amex,
            'SK' => $mastercard_visa_amex,
            'SG' => $mastercard_visa_amex,
            'JP' => array('mastercard' => array(), 'visa' => array(), 'amex' => array('JPY'), 'jcb' => array('JPY')),
            'YT' => $mastercard_visa_amex,
            // Mayotte.
            'RE' => $mastercard_visa_amex,
            // Reunion.
            'GP' => $mastercard_visa_amex,
            // Guadelope.
            'GF' => $mastercard_visa_amex,
            // French Guiana.
            'MQ' => $mastercard_visa_amex,
        ));
    },
    'api.psd2-countries' => static function (ContainerInterface $container): array {
        return array('AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GB', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE');
    },
    'api.paylater-countries' => static function (ContainerInterface $container): array {
        $default_countries = array('US', 'DE', 'GB', 'FR', 'AU', 'IT', 'ES', 'CA');
        return apply_filters('woocommerce_paypal_payments_supported_paylater_countries', $default_countries);
    },
    'api.order-helper' => static function (ContainerInterface $container): OrderHelper {
        return new OrderHelper();
    },
    'api.helper.order-transient' => static function (ContainerInterface $container): OrderTransient {
        $cache = $container->get('api.paypal-bearer-cache');
        $purchase_unit_sanitizer = $container->get('api.helper.purchase-unit-sanitizer');
        return new OrderTransient($cache, $purchase_unit_sanitizer);
    },
    'api.helper.failure-registry' => static function (ContainerInterface $container): FailureRegistry {
        $cache = new Cache('ppcp-paypal-api-status-cache');
        return new FailureRegistry($cache);
    },
    'api.helper.purchase-unit-sanitizer' => SingletonDecorator::make(static function (ContainerInterface $container): PurchaseUnitSanitizer {
        $settings = $container->get('wcgateway.settings');
        assert($settings instanceof Settings);
        $behavior = $settings->has('subtotal_mismatch_behavior') ? $settings->get('subtotal_mismatch_behavior') : null;
        $line_name = $settings->has('subtotal_mismatch_line_name') ? $settings->get('subtotal_mismatch_line_name') : null;
        return new PurchaseUnitSanitizer($behavior, $line_name);
    }),
    'api.client-credentials' => static function (ContainerInterface $container): ClientCredentials {
        return new ClientCredentials($container->get('wcgateway.settings'));
    },
    'api.paypal-bearer-cache' => static function (ContainerInterface $container): Cache {
        return new Cache('ppcp-paypal-bearer');
    },
    'api.client-credentials-cache' => static function (ContainerInterface $container): Cache {
        return new Cache('ppcp-client-credentials-cache');
    },
    'api.user-id-token-cache' => static function (ContainerInterface $container): Cache {
        return new Cache('ppcp-id-token-cache');
    },
    'api.reference-transaction-status-cache' => static function (ContainerInterface $container): Cache {
        return new Cache('ppcp-reference-transaction-status-cache');
    },
    'api.user-id-token' => static function (ContainerInterface $container): UserIdToken {
        return new UserIdToken($container->get('api.host'), $container->get('woocommerce.logger.woocommerce'), $container->get('api.client-credentials'), $container->get('api.user-id-token-cache'));
    },
    'api.sdk-client-token' => static function (ContainerInterface $container): SdkClientToken {
        return new SdkClientToken($container->get('api.host'), $container->get('woocommerce.logger.woocommerce'), $container->get('api.client-credentials'), $container->get('api.client-credentials-cache'));
    },
    'api.paypal-host-production' => static function (ContainerInterface $container): string {
        return PAYPAL_API_URL;
    },
    'api.paypal-host-sandbox' => static function (ContainerInterface $container): string {
        return PAYPAL_SANDBOX_API_URL;
    },
    'api.paypal-website-url-production' => static function (ContainerInterface $container): string {
        return PAYPAL_URL;
    },
    'api.paypal-website-url-sandbox' => static function (ContainerInterface $container): string {
        return PAYPAL_SANDBOX_URL;
    },
    'api.partner_merchant_id-production' => static function (ContainerInterface $container): string {
        return CONNECT_WOO_MERCHANT_ID;
    },
    'api.partner_merchant_id-sandbox' => static function (ContainerInterface $container): string {
        return CONNECT_WOO_SANDBOX_MERCHANT_ID;
    },
    'api.endpoint.login-seller-production' => static function (ContainerInterface $container): LoginSeller {
        return new LoginSeller($container->get('api.paypal-host-production'), $container->get('api.partner_merchant_id-production'), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.endpoint.login-seller-sandbox' => static function (ContainerInterface $container): LoginSeller {
        return new LoginSeller($container->get('api.paypal-host-sandbox'), $container->get('api.partner_merchant_id-sandbox'), $container->get('woocommerce.logger.woocommerce'));
    },
    'api.env.paypal-host' => static function (ContainerInterface $container): EnvironmentConfig {
        /**
         * Environment specific API host names.
         *
         * @type EnvironmentConfig<string>
         */
        return EnvironmentConfig::create('string', $container->get('api.paypal-host-production'), $container->get('api.paypal-host-sandbox'));
    },
    'api.env.endpoint.login-seller' => static function (ContainerInterface $container): EnvironmentConfig {
        /**
         * Environment specific LoginSeller API instances.
         *
         * @type EnvironmentConfig<LoginSeller>
         */
        return EnvironmentConfig::create(LoginSeller::class, $container->get('api.endpoint.login-seller-production'), $container->get('api.endpoint.login-seller-sandbox'));
    },
    'api.env.endpoint.partner-referrals' => static function (ContainerInterface $container): EnvironmentConfig {
        /**
         * Environment specific PartnerReferrals API instances.
         *
         * @type EnvironmentConfig<PartnerReferrals>
         */
        return EnvironmentConfig::create(PartnerReferrals::class, $container->get('api.endpoint.partner-referrals-production'), $container->get('api.endpoint.partner-referrals-sandbox'));
    },
    'api.sandbox-host' => static function (ContainerInterface $container): string {
        $is_connected = $container->get('settings.flag.is-connected');
        if ($is_connected) {
            return PAYPAL_SANDBOX_API_URL;
        }
        return CONNECT_WOO_SANDBOX_URL;
    },
    'api.production-host' => static function (ContainerInterface $container): string {
        $is_connected = $container->get('settings.flag.is-connected');
        if ($is_connected) {
            return PAYPAL_API_URL;
        }
        return CONNECT_WOO_URL;
    },
    'api.helper.partner-attribution' => static function (ContainerInterface $container): PartnerAttribution {
        return new PartnerAttribution('ppcp_bn_code', array(InstallationPathEnum::CORE_PROFILER => 'WooPPCP_Ecom_PS_CoreProfiler', InstallationPathEnum::PAYMENT_SETTINGS => 'WooPPCP_Ecom_PS_CoreProfiler'), PPCP_PAYPAL_BN_CODE);
    },
    /**
     * If connected, return the PayPal Onboarded merchant country.
     * Fallback to WooCommerce Store Country otherwise.
     */
    'api.merchant.country' => static function (ContainerInterface $container): string {
        return $container->get('settings.flag.is-connected') ? $container->get('settings.data.general')->get_merchant_country() : $container->get('api.shop.country');
    },
    'api.helpers.paymentLevelHelper' => static function (ContainerInterface $container): PaymentLevelHelper {
        return new PaymentLevelHelper($container->get('settings.settings-provider'));
    },
    'api.helpers.paymentLevelEligibility' => static function (ContainerInterface $container): PaymentLevelEligibility {
        return new PaymentLevelEligibility($container->get('api.merchant.country'), $container->get('api.shop.currency.getter'));
    },
);
