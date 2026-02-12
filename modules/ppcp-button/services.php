<?php

/**
 * The button module services.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Button\Assets\DisabledSmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButton;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveSubscriptionEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CartScriptParamsEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\DataClientIdEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\GetOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Endpoint\SaveCheckoutFormEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\SimulateCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ValidateCheckoutEndpoint;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\CartProductsHelper;
use WooCommerce\PayPalCommerce\Button\Helper\CheckoutFormSaver;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Button\Helper\DisabledFundingSources;
use WooCommerce\PayPalCommerce\Button\Helper\EarlyOrderHandler;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\Button\Helper\WooCommerceOrderCreator;
use WooCommerce\PayPalCommerce\Button\Session\CartDataFactory;
use WooCommerce\PayPalCommerce\Button\Session\CartDataTransientStorage;
use WooCommerce\PayPalCommerce\Button\Validation\CheckoutFormValidator;
use WooCommerce\PayPalCommerce\Button\VaultV2\StartPayPalVaultingEndpoint;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
return array(
    'button.client_id' => static function (ContainerInterface $container): string {
        $settings = $container->get('wcgateway.settings');
        $client_id = $settings->has('client_id') ? $settings->get('client_id') : '';
        if ($client_id) {
            return $client_id;
        }
        $env = $container->get('settings.environment');
        /**
         * The environment.
         *
         * @var Environment $env
         */
        return $env->current_environment_is(Environment::SANDBOX) ? CONNECT_WOO_SANDBOX_CLIENT_ID : CONNECT_WOO_CLIENT_ID;
    },
    'button.client_id_for_admin' => static function (ContainerInterface $container): string {
        $dummy_ids = array('AU' => 'AQ5yx7aGjD0fWKDMQrngSznDlfSfWvio9j1fCeuLC5foFoimaM_d1AbeRmEvc9jVuJU7BbopMSd4aNPG', 'DE' => 'AYZigu5BLwbJ_QKNasp_1k0kJUon7NRyazh8Lo-bthJuKetzXRBEzUlbeUIvUfsBxrcN-K0UEk-V6Lq9', 'ES' => 'Aa3A3B4MvF2_Xwoj7kG_4qI_hh2pRmuvjRefIgp8B0HSIIGnqsx2Wd8IGOvhyX1G2WLOMl0xGJsiHpXl', 'FR' => 'AYIb1W_LbKGlgpOwk64dGk8PPQnIx0H4wdmQfdNt8M6cCaAsSgQ6O-TwTDF6y9Jpp_5BxtHoYYMQDCb5', 'GB' => 'AZvAtq7qHoM0yefv-ptnmAvN3gDm9oNj2A7oDqhw_d-pEdWW5q68b7_xd-U2-dQs_kipnmLhV3-7vWkU', 'IT' => 'AZm7Rq3sLabDbtq2vRCRVtMRJ09SLi6HeoRy4JuUdFQ6j0D_x-wEZtRzjBhY4NzAcFC_T7GTBdvSYEwK', 'US' => 'Ad5dKzVsWZvPD3YgjhZ24LKNKmJqg2Xo3uKx7yuazPiARFc9xJWg1mM-vy-eJhb1V7xn5mPnp_QjAMaM');
        $shop_country = $container->get('api.shop.country');
        return $dummy_ids[$shop_country] ?? $container->get('button.client_id');
    },
    // This service may not work correctly when called too early.
    'button.context' => static function (ContainerInterface $container): string {
        $context = $container->get('button.helper.context');
        return $context->context();
    },
    'button.smart-button' => static function (ContainerInterface $container): SmartButtonInterface {
        $context = $container->get('button.context');
        $settings_status = $container->get('wcgateway.settings.status');
        assert($settings_status instanceof SettingsStatus);
        if (in_array($context, array('checkout', 'pay-now'), \true)) {
            $redirect_to_pay = $container->get('wcgateway.use-place-order-button');
            if ($redirect_to_pay) {
                // No smart buttons, redirect the current page to PayPal for payment.
                return new DisabledSmartButton();
            }
            $no_smart_buttons = !$settings_status->is_smart_button_enabled_for_location($context);
            $dcc_configuration = $container->get('wcgateway.configuration.card-configuration');
            assert($dcc_configuration instanceof CardPaymentsConfiguration);
            if ($no_smart_buttons && !$dcc_configuration->is_enabled()) {
                // Smart buttons disabled, and also not using advanced card payments.
                return new DisabledSmartButton();
            }
        }
        $is_connected = $container->get('settings.flag.is-connected');
        if (!$is_connected) {
            return new DisabledSmartButton();
        }
        $settings = $container->get('wcgateway.settings');
        $paypal_disabled = !$settings->has('enabled') || !$settings->get('enabled');
        if ($paypal_disabled) {
            return new DisabledSmartButton();
        }
        $payer_factory = $container->get('api.factory.payer');
        $request_data = $container->get('button.request-data');
        $client_id = $container->get('button.client_id');
        $dcc_applies = $container->get('api.helpers.dccapplies');
        $subscription_helper = $container->get('wc-subscriptions.helper');
        $messages_apply = $container->get('button.helper.messages-apply');
        $environment = $container->get('settings.environment');
        $payment_token_repository = $container->get('vaulting.repository.payment-token');
        return new SmartButton($container->get('button.asset_getter'), $container->get('ppcp.asset-version'), $container->get('session.handler'), $settings, $payer_factory, $client_id, $request_data, $dcc_applies, $subscription_helper, $messages_apply, $environment, $payment_token_repository, $settings_status, $container->get('api.shop.currency.getter'), $container->get('button.basic-checkout-validation-enabled'), $container->get('button.early-wc-checkout-validation-enabled'), $container->get('button.pay-now-contexts'), $container->get('wcgateway.funding-sources-without-redirect'), $container->get('vaulting.vault-v3-enabled'), $container->get('api.endpoint.payment-tokens'), $container->get('woocommerce.logger.woocommerce'), $container->get('button.handle-shipping-in-paypal'), $container->get('wcgateway.server-side-shipping-callback-enabled'), $container->get('wcgateway.appswitch-enabled'), $container->get('button.helper.disabled-funding-sources'), $container->get('wcgateway.configuration.card-configuration'), $container->get('api.helper.partner-attribution'), $container->get('blocks.settings.final_review_enabled'), $container->get('button.helper.context'));
    },
    'button.asset_getter' => static function (ContainerInterface $container): AssetGetter {
        $factory = $container->get('assets.asset_getter_factory');
        assert($factory instanceof AssetGetterFactory);
        return $factory->for_module('ppcp-button');
    },
    'button.pay-now-contexts' => static function (ContainerInterface $container): array {
        $defaults = array('checkout', 'pay-now');
        if ($container->get('button.handle-shipping-in-paypal')) {
            return array_merge($defaults, array('cart', 'product', 'mini-cart'));
        }
        return $defaults;
    },
    'button.request-data' => static function (ContainerInterface $container): RequestData {
        return new RequestData();
    },
    'button.endpoint.simulate-cart' => static function (ContainerInterface $container): SimulateCartEndpoint {
        if (!\WC()->cart) {
            throw new RuntimeException('cant initialize endpoint at this moment');
        }
        $smart_button = $container->get('button.smart-button');
        $cart = WC()->cart;
        $request_data = $container->get('button.request-data');
        $cart_products = $container->get('button.helper.cart-products');
        $logger = $container->get('woocommerce.logger.woocommerce');
        return new SimulateCartEndpoint($smart_button, $cart, $request_data, $cart_products, $logger);
    },
    'button.endpoint.change-cart' => static function (ContainerInterface $container): ChangeCartEndpoint {
        if (!\WC()->cart) {
            throw new RuntimeException('cant initialize endpoint at this moment');
        }
        $cart = WC()->cart;
        $shipping = WC()->shipping();
        $request_data = $container->get('button.request-data');
        $purchase_unit_factory = $container->get('api.factory.purchase-unit');
        $cart_products = $container->get('button.helper.cart-products');
        $logger = $container->get('woocommerce.logger.woocommerce');
        return new ChangeCartEndpoint($cart, $shipping, $request_data, $purchase_unit_factory, $cart_products, $logger);
    },
    'button.endpoint.create-order' => static function (ContainerInterface $container): CreateOrderEndpoint {
        $request_data = $container->get('button.request-data');
        $purchase_unit_factory = $container->get('api.factory.purchase-unit');
        $order_endpoint = $container->get('api.endpoint.order');
        $payer_factory = $container->get('api.factory.payer');
        $session_handler = $container->get('session.handler');
        $settings = $container->get('wcgateway.settings');
        $early_order_handler = $container->get('button.helper.early-order-handler');
        $registration_needed = $container->get('button.current-user-must-register');
        $logger = $container->get('woocommerce.logger.woocommerce');
        return new CreateOrderEndpoint($request_data, $purchase_unit_factory, $container->get('api.factory.shipping-preference'), $container->get('api.factory.return-url'), $container->get('api.factory.contact-preference'), $container->get('wcgateway.builder.experience-context'), $order_endpoint, $payer_factory, $session_handler, $settings, $early_order_handler, $container->get('button.session.factory.card-data'), $container->get('button.session.storage.card-data.transient'), $registration_needed, $container->get('wcgateway.settings.card_billing_data_mode'), $container->get('button.early-wc-checkout-validation-enabled'), $container->get('button.pay-now-contexts'), $container->get('button.handle-shipping-in-paypal'), $container->get('wcgateway.server-side-shipping-callback-enabled'), $container->get('wcgateway.funding-sources-without-redirect'), $logger);
    },
    'button.helper.early-order-handler' => static function (ContainerInterface $container): EarlyOrderHandler {
        return new EarlyOrderHandler($container->get('settings.flag.is-connected'), $container->get('wcgateway.order-processor'), $container->get('session.handler'));
    },
    'button.endpoint.approve-order' => static function (ContainerInterface $container): ApproveOrderEndpoint {
        $request_data = $container->get('button.request-data');
        $order_endpoint = $container->get('api.endpoint.order');
        $session_handler = $container->get('session.handler');
        $three_d_secure = $container->get('button.helper.three-d-secure');
        $settings = $container->get('wcgateway.settings');
        $dcc_applies = $container->get('api.helpers.dccapplies');
        $order_helper = $container->get('api.order-helper');
        $final_review_enabled = $container->get('blocks.settings.final_review_enabled');
        $wc_order_creator = $container->get('button.helper.wc-order-creator');
        $gateway = $container->get('wcgateway.paypal-gateway');
        $logger = $container->get('woocommerce.logger.woocommerce');
        $context = $container->get('button.helper.context');
        return new ApproveOrderEndpoint($request_data, $order_endpoint, $session_handler, $three_d_secure, $settings, $dcc_applies, $order_helper, $final_review_enabled, $gateway, $wc_order_creator, $logger, $context);
    },
    'button.endpoint.approve-subscription' => static function (ContainerInterface $container): ApproveSubscriptionEndpoint {
        return new ApproveSubscriptionEndpoint($container->get('button.request-data'), $container->get('api.endpoint.order'), $container->get('session.handler'), $container->get('blocks.settings.final_review_enabled'), $container->get('button.helper.wc-order-creator'), $container->get('wcgateway.paypal-gateway'), $container->get('button.helper.context'));
    },
    'button.helper.context' => static function (ContainerInterface $container): Context {
        $session_handler = $container->get('session.handler');
        $subscription_status = $container->get('paypal-subscriptions.status');
        return new Context($session_handler, $subscription_status);
    },
    'button.checkout-form-saver' => static function (ContainerInterface $container): CheckoutFormSaver {
        return new CheckoutFormSaver($container->get('session.handler'));
    },
    'button.endpoint.save-checkout-form' => static function (ContainerInterface $container): SaveCheckoutFormEndpoint {
        return new SaveCheckoutFormEndpoint($container->get('button.request-data'), $container->get('button.checkout-form-saver'), $container->get('woocommerce.logger.woocommerce'));
    },
    'button.endpoint.data-client-id' => static function (ContainerInterface $container): DataClientIdEndpoint {
        $request_data = $container->get('button.request-data');
        $identity_token = $container->get('api.endpoint.identity-token');
        $logger = $container->get('woocommerce.logger.woocommerce');
        return new DataClientIdEndpoint($request_data, $identity_token, $logger);
    },
    'button.vault-v2.endpoint.vault-paypal' => static function (ContainerInterface $container): StartPayPalVaultingEndpoint {
        return new StartPayPalVaultingEndpoint($container->get('button.request-data'), $container->get('vault-v2.endpoint.payment-token'), $container->get('woocommerce.logger.woocommerce'));
    },
    'button.endpoint.validate-checkout' => static function (ContainerInterface $container): ValidateCheckoutEndpoint {
        return new ValidateCheckoutEndpoint($container->get('button.request-data'), $container->get('button.validation.wc-checkout-validator'), $container->get('woocommerce.logger.woocommerce'));
    },
    'button.endpoint.cart-script-params' => static function (ContainerInterface $container): CartScriptParamsEndpoint {
        return new CartScriptParamsEndpoint($container->get('button.smart-button'), $container->get('woocommerce.logger.woocommerce'));
    },
    'button.endpoint.get-order' => static function (ContainerInterface $container): GetOrderEndpoint {
        $request_data = $container->get('button.request-data');
        $order_endpoint = $container->get('api.endpoint.order');
        $logger = $container->get('woocommerce.logger.woocommerce');
        $cart_data_storage = $container->get('button.session.storage.card-data.transient');
        return new GetOrderEndpoint($request_data, $order_endpoint, $logger, $cart_data_storage);
    },
    'button.helper.cart-products' => static function (ContainerInterface $container): CartProductsHelper {
        $data_store = \WC_Data_Store::load('product');
        return new CartProductsHelper($data_store);
    },
    'button.helper.three-d-secure' => static function (ContainerInterface $container): ThreeDSecure {
        return new ThreeDSecure($container->get('api.factory.card-authentication-result-factory'), $container->get('woocommerce.logger.woocommerce'));
    },
    'button.helper.messages-apply' => static function (ContainerInterface $container): MessagesApply {
        return new MessagesApply($container->get('api.paylater-countries'), $container->get('api.merchant.country'));
    },
    'button.helper.disabled-funding-sources' => static function (ContainerInterface $container): DisabledFundingSources {
        return new DisabledFundingSources($container->get('wcgateway.settings'), $container->get('wcgateway.all-funding-sources'), $container->get('wcgateway.configuration.card-configuration'), $container->get('api.shop.country'));
    },
    'button.is-logged-in' => static function (ContainerInterface $container): bool {
        return is_user_logged_in();
    },
    'button.registration-required' => static function (ContainerInterface $container): bool {
        return WC()->checkout()->is_registration_required();
    },
    'button.current-user-must-register' => static function (ContainerInterface $container): bool {
        return !$container->get('button.is-logged-in') && $container->get('button.registration-required');
    },
    'button.basic-checkout-validation-enabled' => static function (ContainerInterface $container): bool {
        /**
         * The filter allowing to disable the basic client-side validation of the checkout form
         * when the PayPal button is clicked.
         */
        return (bool) apply_filters('woocommerce_paypal_payments_basic_checkout_validation_enabled', \false);
    },
    'button.early-wc-checkout-validation-enabled' => static function (ContainerInterface $container): bool {
        /**
         * The filter allowing to disable the WC validation of the checkout form
         * when the PayPal button is clicked.
         * The validation is triggered in a non-standard way and may cause issues on some sites.
         */
        return (bool) apply_filters('woocommerce_paypal_payments_early_wc_checkout_validation_enabled', \true);
    },
    'button.validation.wc-checkout-validator' => static function (ContainerInterface $container): CheckoutFormValidator {
        return new CheckoutFormValidator();
    },
    /**
     * If true, the shipping methods are sent to PayPal allowing the customer to select it inside the popup.
     * May result in slower popup performance, additional loading.
     */
    'button.handle-shipping-in-paypal' => static function (ContainerInterface $container): bool {
        return !$container->get('blocks.settings.final_review_enabled');
    },
    'button.helper.wc-order-creator' => static function (ContainerInterface $container): WooCommerceOrderCreator {
        return new WooCommerceOrderCreator($container->get('wcgateway.funding-source.renderer'), $container->get('session.handler'), $container->get('wc-subscriptions.helper'), $container->get('button.session.factory.card-data'), $container->get('api.factory.shipping'), $container->get('api.factory.payer'));
    },
    'button.session.factory.card-data' => static function (ContainerInterface $container): CartDataFactory {
        return new CartDataFactory();
    },
    'button.session.storage.card-data.transient' => static function (ContainerInterface $container): CartDataTransientStorage {
        return new CartDataTransientStorage();
    },
);
