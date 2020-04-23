<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\CacheModule\Provider\CacheProviderInterface;
use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Config\Config;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AddressFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AmountFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\AuthorizationFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ErrorResponseCollectionFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ItemFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PatchCollectionFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PayeeFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PayerFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PaymentsFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ShippingFactory;
use Inpsyde\PayPalCommerce\ApiClient\Repository\CartRepository;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PayeeRepository;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use WpOop\TransientCache\CachePoolFactory;

return [

    'api.host' => function (ContainerInterface $container) : string {
        return 'https://api.sandbox.paypal.com';
    },
    'api.key' => function (ContainerInterface $container) : string {
        return 'AQB97CzMsd58-It1vxbcDAGvMuXNCXRD9le_XUaMlHB_U7XsU9IiItBwGQOtZv9sEeD6xs2vlIrL4NiD';
    },
    'api.secret' => function (ContainerInterface $container) : string {
        return 'EILGMYK_0iiSbja8hT-nCBGl0BvKxEB4riHgyEO7QWDeUzCJ5r42JUEvrI7gpGyw0Qww8AIXxSdCIAny';
    },
    'api.bearer' => function (ContainerInterface $container) : Bearer {
        global $wpdb;
        $cacheFactory = new CachePoolFactory($wpdb);
        $pool = $cacheFactory->createCachePool('ppcp-token');
        return new Bearer(
            $pool,
            $container->get('api.host'),
            $container->get('api.key'),
            $container->get('api.secret')
        );
    },
    'api.endpoint.payments' => function (ContainerInterface $container): PaymentsEndpoint {
        $authorizationFactory = $container->get('api.factory.authorization');
        $errorResponseFactory = $container->get('api.factory.response-error');

        return new PaymentsEndpoint(
            $container->get('api.host'),
            $container->get('api.bearer'),
            $authorizationFactory,
            $errorResponseFactory
        );
    },
    'api.endpoint.order' => function (ContainerInterface $container): OrderEndpoint {
        $orderFactory = $container->get('api.factory.order');
        $patchCollectionFactory = $container->get('api.factory.patch-collection-factory');
        $errorResponseFactory = $container->get('api.factory.response-error');

        /**
         * @var Settings $settings
         */
        $settings = $container->get('wcgateway.settings');
        $intent = strtoupper($settings->get('intent'));

        return new OrderEndpoint(
            $container->get('api.host'),
            $container->get('api.bearer'),
            $orderFactory,
            $patchCollectionFactory,
            $intent,
            $errorResponseFactory
        );
    },
    'api.repository.cart' => function (ContainerInterface $container) : CartRepository {
        $factory = $container->get('api.factory.purchase-unit');
        return new CartRepository($factory);
    },
    'api.config.config' => function (ContainerInterface $container) : Config {
        return new Config();
    },
    'api.repository.payee' => function (ContainerInterface $container) : PayeeRepository {
        $config = $container->get('api.config.config');
        return new PayeeRepository($config);
    },
    'api.factory.purchase-unit' => function (ContainerInterface $container) : PurchaseUnitFactory {

        $amountFactory = $container->get('api.factory.amount');
        $payeeRepository = $container->get('api.repository.payee');
        $payeeFactory = $container->get('api.factory.payee');
        $itemFactory = $container->get('api.factory.item');
        $shippingFactory = $container->get('api.factory.shipping');
        $paymentsFactory = $container->get('api.factory.payments');

        return new PurchaseUnitFactory(
            $amountFactory,
            $payeeRepository,
            $payeeFactory,
            $itemFactory,
            $shippingFactory,
            $paymentsFactory
        );
    },
    'api.factory.patch-collection-factory' => function (ContainerInterface $container)
        : PatchCollectionFactory {
        return new PatchCollectionFactory();
    },
    'api.factory.payee' => function (ContainerInterface $container) : PayeeFactory {
        return new PayeeFactory();
    },
    'api.factory.item' => function (ContainerInterface $container) : ItemFactory {
        return new ItemFactory();
    },
    'api.factory.shipping' => function (ContainerInterface $container) : ShippingFactory {
        $addressFactory = $container->get('api.factory.address');
        return new ShippingFactory($addressFactory);
    },
    'api.factory.amount' => function (ContainerInterface $container) : AmountFactory {
        $itemFactory = $container->get('api.factory.item');
        return new AmountFactory($itemFactory);
    },
    'api.factory.payer' => function (ContainerInterface $container) : PayerFactory {
        $addressFactory = $container->get('api.factory.address');
        return new PayerFactory($addressFactory);
    },
    'api.factory.address' => function (ContainerInterface $container) : AddressFactory {
        return new AddressFactory();
    },
    'api.factory.response-error' => function (ContainerInterface $container) : ErrorResponseCollectionFactory {
        return new ErrorResponseCollectionFactory();
    },
    'api.factory.order' => function (ContainerInterface $container) : OrderFactory {
        $purchaseUnitFactory = $container->get('api.factory.purchase-unit');
        $payerFactory = $container->get('api.factory.payer');
        return new OrderFactory($purchaseUnitFactory, $payerFactory);
    },
    'api.factory.payments' => function (ContainerInterface $container): PaymentsFactory {
        $authorizationFactory = $container->get('api.factory.authorization');
        return new PaymentsFactory($authorizationFactory);
    },
    'api.factory.authorization' => function (ContainerInterface $container): AuthorizationFactory {
        return new AuthorizationFactory();
    },
];
