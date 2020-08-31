<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\IdentityToken;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\LoginSeller;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PartnerReferrals;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentTokenEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AddressFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AmountFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ApplicationContextFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ItemFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PatchCollectionFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PayeeFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PayerFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PaymentsFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PaymentSourceFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ShippingFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\WebhookFactory;
use Inpsyde\PayPalCommerce\ApiClient\Helper\DccApplies;
use Inpsyde\PayPalCommerce\ApiClient\Repository\ApplicationContextRepository;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PayeeRepository;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PayPalRequestIdRepository;
use Inpsyde\PayPalCommerce\Onboarding\Environment;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use WpOop\TransientCache\CachePoolFactory;

return [
    'api.host' => function(ContainerInterface $container) : string {
        return 'https://api.paypal.com';
    },
    'api.paypal-host' => function(ContainerInterface $container) : string {
        return 'https://api.paypal.com';
    },
    'api.partner_merchant_id' => static function () : string {
        return '';
    },
    'api.merchant_email' => function () : string {
        return '';
    },
    'api.merchant_id' => function () : string {
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
        global $wpdb;
        $cacheFactory = new CachePoolFactory($wpdb);
        $pool = $cacheFactory->createCachePool('ppcp-token');
        $key = $container->get('api.key');
        $secret = $container->get('api.secret');

        $host = $container->get('api.host');
        $logger = $container->get('woocommerce.logger.woocommerce');
        return new PayPalBearer(
            $pool,
            $host,
            $key,
            $secret,
            $logger
        );
    },
    'api.endpoint.payment-token' => static function (ContainerInterface $container) : PaymentTokenEndpoint {
        return new PaymentTokenEndpoint(
            $container->get('api.host'),
            $container->get('api.bearer'),
            $container->get('api.factory.payment-token'),
            $container->get('woocommerce.logger.woocommerce'),
            $container->get('api.prefix')
        );
    },
    'api.endpoint.webhook' => static function (ContainerInterface $container) : WebhookEndpoint {

        return new WebhookEndpoint(
            $container->get('api.host'),
            $container->get('api.bearer'),
            $container->get('api.factory.webhook'),
            $container->get('woocommerce.logger.woocommerce')
        );
    },
    'api.endpoint.partner-referrals' => static function (ContainerInterface $container) : PartnerReferrals {

        return new PartnerReferrals(
            $container->get('api.host'),
            $container->get('api.bearer'),
            $container->get('api.repository.partner-referrals-data'),
            $container->get('woocommerce.logger.woocommerce')
        );
    },
    'api.endpoint.identity-token' => static function (ContainerInterface $container) : IdentityToken {

        $logger = $container->get('woocommerce.logger.woocommerce');
        $prefix = $container->get('api.prefix');
        return new IdentityToken(
            $container->get('api.host'),
            $container->get('api.bearer'),
            $logger,
            $prefix
        );
    },
    'api.endpoint.payments' => static function (ContainerInterface $container): PaymentsEndpoint {
        $authorizationFactory = $container->get('api.factory.authorization');
        $logger = $container->get('woocommerce.logger.woocommerce');

        return new PaymentsEndpoint(
            $container->get('api.host'),
            $container->get('api.bearer'),
            $authorizationFactory,
            $logger
        );
    },
    'api.endpoint.login-seller' => static function (ContainerInterface $container) : LoginSeller {

        $logger = $container->get('woocommerce.logger.woocommerce');
        return new LoginSeller(

            $container->get('api.paypal-host'),
            $container->get('api.partner_merchant_id'),
            $logger
        );
    },
    'api.endpoint.order' => static function (ContainerInterface $container): OrderEndpoint {
        $orderFactory = $container->get('api.factory.order');
        $patchCollectionFactory = $container->get('api.factory.patch-collection-factory');
        $logger = $container->get('woocommerce.logger.woocommerce');

        /**
         * @var Settings $settings
         */
        $settings = $container->get('wcgateway.settings');
        $intent = $settings->has('intent') && strtoupper((string) $settings->get('intent')) === 'AUTHORIZE' ? 'AUTHORIZE' : 'CAPTURE';
        $applicationContextRepository = $container->get('api.repository.application-context');
        $paypalRequestId = $container->get('api.repository.paypal-request-id');
        return new OrderEndpoint(
            $container->get('api.host'),
            $container->get('api.bearer'),
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $logger,
            $applicationContextRepository,
            $paypalRequestId
        );
    },
    'api.repository.paypal-request-id' => static function(ContainerInterface $container) : PayPalRequestIdRepository {
        return new PayPalRequestIdRepository();
    },
    'api.repository.application-context' => static function(ContainerInterface $container) : ApplicationContextRepository {

        $settings = $container->get('wcgateway.settings');
        return new ApplicationContextRepository($settings);
    },
    'api.repository.partner-referrals-data' => static function (ContainerInterface $container) : PartnerReferralsData {

        $merchantEmail = $container->get('api.merchant_email');
        $dccApplies = $container->get('api.helpers.dccapplies');
        return new PartnerReferralsData($merchantEmail, $dccApplies);
    },
    'api.repository.cart' => static function (ContainerInterface $container): CartRepository {
        $factory = $container->get('api.factory.purchase-unit');
        return new CartRepository($factory);
    },
    'api.repository.payee' => static function (ContainerInterface $container): PayeeRepository {
        $merchantEmail = $container->get('api.merchant_email');
        $merchantId = $container->get('api.merchant_id');
        return new PayeeRepository($merchantEmail, $merchantId);
    },
    'api.factory.application-context' => static function (ContainerInterface $container) : ApplicationContextFactory {
        return new ApplicationContextFactory();
    },
    'api.factory.payment-token' => static function (ContainerInterface $container) : PaymentTokenFactory {
        return new PaymentTokenFactory();
    },
    'api.factory.webhook' => static function (ContainerInterface $container): WebhookFactory {
        return new WebhookFactory();
    },
    'api.factory.purchase-unit' => static function (ContainerInterface $container): PurchaseUnitFactory {

        $amountFactory = $container->get('api.factory.amount');
        $payeeRepository = $container->get('api.repository.payee');
        $payeeFactory = $container->get('api.factory.payee');
        $itemFactory = $container->get('api.factory.item');
        $shippingFactory = $container->get('api.factory.shipping');
        $paymentsFactory = $container->get('api.factory.payments');
        $prefix = $container->get('api.prefix');

        return new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFactory,
            $prefix
        );
    },
    'api.factory.patch-collection-factory' => static function (ContainerInterface $container): PatchCollectionFactory {
        return new PatchCollectionFactory();
    },
    'api.factory.payee' => static function (ContainerInterface $container): PayeeFactory {
        return new PayeeFactory();
    },
    'api.factory.item' => static function (ContainerInterface $container): ItemFactory {
        return new ItemFactory();
    },
    'api.factory.shipping' => static function (ContainerInterface $container): ShippingFactory {
        $addressFactory = $container->get('api.factory.address');
        return new ShippingFactory($addressFactory);
    },
    'api.factory.amount' => static function (ContainerInterface $container): AmountFactory {
        $itemFactory = $container->get('api.factory.item');
        return new AmountFactory($itemFactory);
    },
    'api.factory.payer' => static function (ContainerInterface $container): PayerFactory {
        $addressFactory = $container->get('api.factory.address');
        return new PayerFactory($addressFactory);
    },
    'api.factory.address' => static function (ContainerInterface $container): AddressFactory {
        return new AddressFactory();
    },
    'api.factory.payment-source' => static function (ContainerInterface $container): PaymentSourceFactory {
        return new PaymentSourceFactory();
    },
    'api.factory.order' => static function (ContainerInterface $container): OrderFactory {
        $purchaseUnitFactory = $container->get('api.factory.purchase-unit');
        $payerFactory = $container->get('api.factory.payer');
        $applicationContextRepository = $container->get('api.repository.application-context');
        $applicationContextFactory = $container->get('api.factory.application-context');
        $paymentSourceFactory = $container->get('api.factory.payment-source');
        return new OrderFactory(
            $purchaseUnitFactory,
            $payerFactory,
            $applicationContextRepository,
            $applicationContextFactory,
            $paymentSourceFactory
        );
    },
    'api.factory.payments' => static function (ContainerInterface $container): PaymentsFactory {
        $authorizationFactory = $container->get('api.factory.authorization');
        return new PaymentsFactory($authorizationFactory);
    },
    'api.factory.authorization' => static function (ContainerInterface $container): AuthorizationFactory {
        return new AuthorizationFactory();
    },
    'api.helpers.dccapplies' => static function (ContainerInterface $container) : DccApplies {
        return new DccApplies();
    },
];
