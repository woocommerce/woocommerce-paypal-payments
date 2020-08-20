<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\Button\Assets\DisabledSmartButton;
use Inpsyde\PayPalCommerce\Button\Assets\SmartButton;
use Inpsyde\PayPalCommerce\Button\Assets\SmartButtonInterface;
use Inpsyde\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\DataClientIdEndpoint;
use Inpsyde\PayPalCommerce\Button\Endpoint\RequestData;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\Button\Helper\MessagesApply;
use Inpsyde\PayPalCommerce\Button\Helper\ThreeDSecure;
use Inpsyde\PayPalCommerce\Onboarding\Environment;
use Inpsyde\PayPalCommerce\Onboarding\State;

return [
    'button.client_id' => static function (ContainerInterface $container): string {

        $settings = $container->get('wcgateway.settings');
        $clientId = $settings->has('client_id') ? $settings->get('client_id') : '';
        if ($clientId) {
            return $clientId;
        }

        $env = $container->get('onboarding.environment');
        /**
         * @var Environment $env
         */

        /**
         * ToDo: Add production platform client Id.
         */
        return $env->currentEnvironmentIs(Environment::SANDBOX) ?
            'AQB97CzMsd58-It1vxbcDAGvMuXNCXRD9le_XUaMlHB_U7XsU9IiItBwGQOtZv9sEeD6xs2vlIrL4NiD' : '';
    },
    'button.smart-button' => static function (ContainerInterface $container): SmartButtonInterface {

        $state = $container->get('onboarding.state');
        /**
         * @var State $state
         */
        if ($state->currentState() < State::STATE_PROGRESSIVE) {
            return new DisabledSmartButton();
        }
        $settings = $container->get('wcgateway.settings');
        $paypalDisabled = !$settings->has('enabled') || ! $settings->get('enabled');
        $creditCardDisabled = !$settings->has('dcc_gateway_enabled') || ! $settings->get('dcc_gateway_enabled');
        if ($paypalDisabled && $creditCardDisabled) {
            return new DisabledSmartButton();
        }
        $payeeRepository = $container->get('api.repository.payee');
        $identityToken = $container->get('api.endpoint.identity-token');
        $payerFactory = $container->get('api.factory.payer');
        $requestData = $container->get('button.request-data');

        $clientId = $container->get('button.client_id');
        $dccApplies = $container->get('api.helpers.dccapplies');
        $subscriptionHelper = $container->get('subscription.helper');
        $messagesApply = $container->get('button.helper.messages-apply');
        return new SmartButton(
            $container->get('button.url'),
            $container->get('session.handler'),
            $settings,
            $payeeRepository,
            $identityToken,
            $payerFactory,
            $clientId,
            $requestData,
            $dccApplies,
            $subscriptionHelper,
            $messagesApply
        );
    },
    'button.url' => static function (ContainerInterface $container): string {
        return plugins_url(
            '/modules.local/ppcp-button/',
            dirname(__FILE__, 3) . '/woocommerce-paypal-commerce-gateway.php'
        );
    },
    'button.request-data' => static function (ContainerInterface $container): RequestData {
        return new RequestData();
    },
    'button.endpoint.change-cart' => static function (ContainerInterface $container): ChangeCartEndpoint {
        if (!\WC()->cart) {
            throw new RuntimeException('cant initialize endpoint at this moment');
        }
        $cart = WC()->cart;
        $shipping = WC()->shipping();
        $requestData = $container->get('button.request-data');
        $repository = $container->get('api.repository.cart');
        $dataStore = \WC_Data_Store::load('product');
        return new ChangeCartEndpoint($cart, $shipping, $requestData, $repository, $dataStore);
    },
    'button.endpoint.create-order' => static function (ContainerInterface $container): CreateOrderEndpoint {
        $requestData = $container->get('button.request-data');
        $repository = $container->get('api.repository.cart');
        $apiClient = $container->get('api.endpoint.order');
        $payerFactory = $container->get('api.factory.payer');
        $sessionHandler = $container->get('session.handler');
        $settings = $container->get('wcgateway.settings');
        return new CreateOrderEndpoint(
            $requestData,
            $repository,
            $apiClient,
            $payerFactory,
            $sessionHandler,
            $settings
        );
    },
    'button.endpoint.approve-order' => static function (ContainerInterface $container): ApproveOrderEndpoint {
        $requestData = $container->get('button.request-data');
        $apiClient = $container->get('api.endpoint.order');
        $sessionHandler = $container->get('session.handler');
        $threeDSecure = $container->get('button.helper.three-d-secure');
        return new ApproveOrderEndpoint($requestData, $apiClient, $sessionHandler, $threeDSecure);
    },
    'button.endpoint.data-client-id' => static function(ContainerInterface $container) : DataClientIdEndpoint {
        $requestData = $container->get('button.request-data');
        $tokenEndpoint = $container->get('api.endpoint.identity-token');
        return new DataClientIdEndpoint(
            $requestData,
            $tokenEndpoint
        );
    },
    'button.helper.three-d-secure' => static function (ContainerInterface $container): ThreeDSecure {
        return new ThreeDSecure();
    },
    'button.helper.messages-apply' => static function (ContainerInterface $container): MessagesApply {
        return new MessagesApply();
    },
];
