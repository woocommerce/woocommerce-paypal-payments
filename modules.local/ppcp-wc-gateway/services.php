<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Inpsyde\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use Inpsyde\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use Inpsyde\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;
use Inpsyde\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use Inpsyde\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use Inpsyde\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use Inpsyde\PayPalCommerce\WcGateway\Settings\Settings;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsListener;
use Inpsyde\PayPalCommerce\WcGateway\Settings\SettingsRenderer;
use Inpsyde\Woocommerce\Logging\Logger\NullLogger;
use Inpsyde\Woocommerce\Logging\Logger\WooCommerceLogger;
use Psr\Log\LoggerInterface;
use WpOop\TransientCache\CachePoolFactory;

return [
    'wcgateway.gateway' => static function (ContainerInterface $container): WcGateway {
        $orderProcessor = $container->get('wcgateway.order-processor');
        $settingsRenderer = $container->get('wcgateway.settings.render');
        $authorizedPayments = $container->get('wcgateway.processor.authorized-payments');
        $notice = $container->get('wcgateway.notice.authorize-order-action');
        $settings = $container->get('wcgateway.settings');

        return new WcGateway(
            $settingsRenderer,
            $orderProcessor,
            $authorizedPayments,
            $notice,
            $settings
        );
    },
    'wcgateway.disabler' => static function (ContainerInterface $container): DisableGateways {
        $sessionHandler = $container->get('session.handler');
        $settings = $container->get('wcgateway.settings');
        return new DisableGateways($sessionHandler, $settings);
    },
    'wcgateway.settings' => static function (ContainerInterface $container): Settings {
        return new Settings();
    },
    'wcgateway.notice.connect' => static function (ContainerInterface $container): ConnectAdminNotice {
        $state = $container->get('onboarding.state');
        $settings = $container->get('wcgateway.settings');
        return new ConnectAdminNotice($state, $settings);
    },
    'wcgateway.notice.authorize-order-action' =>
        static function (ContainerInterface $container): AuthorizeOrderActionNotice {
            return new AuthorizeOrderActionNotice();
        },
    'wcgateway.settings.render' => static function (ContainerInterface $container): SettingsRenderer {
        $settings = $container->get('wcgateway.settings');
        $state = $container->get('onboarding.state');
        $fields = $container->get('wcgateway.settings.fields');
        return new SettingsRenderer($settings, $state, $fields);
    },
    'wcgateway.settings.listener' => static function (ContainerInterface $container): SettingsListener {
        $settings = $container->get('wcgateway.settings');
        $fields = $container->get('wcgateway.settings.fields');
        $webhookRegistrar = $container->get('webhook.registrar');
        $state = $container->get('onboarding.state');

        global $wpdb;
        $cacheFactory = new CachePoolFactory($wpdb);
        $pool = $cacheFactory->createCachePool('ppcp-token');
        return new SettingsListener($settings, $fields, $webhookRegistrar, $pool, $state);
    },
    'wcgateway.order-processor' => static function (ContainerInterface $container): OrderProcessor {

        $sessionHandler = $container->get('session.handler');
        $cartRepository = $container->get('api.repository.cart');
        $orderEndpoint = $container->get('api.endpoint.order');
        $paymentsEndpoint = $container->get('api.endpoint.payments');
        $orderFactory = $container->get('api.factory.order');
        return new OrderProcessor(
            $sessionHandler,
            $cartRepository,
            $orderEndpoint,
            $paymentsEndpoint,
            $orderFactory
        );
    },
    'wcgateway.processor.authorized-payments' => static function (ContainerInterface $container): AuthorizedPaymentsProcessor {
        $orderEndpoint = $container->get('api.endpoint.order');
        $paymentsEndpoint = $container->get('api.endpoint.payments');
        return new AuthorizedPaymentsProcessor($orderEndpoint, $paymentsEndpoint);
    },
    'wcgateway.admin.order-payment-status' => static function (ContainerInterface $container): PaymentStatusOrderDetail {
        return new PaymentStatusOrderDetail();
    },
    'wcgateway.admin.orders-payment-status-column' => static function (ContainerInterface $container): OrderTablePaymentStatusColumn {
        $settings = $container->get('wcgateway.settings');
        return new OrderTablePaymentStatusColumn($settings);
    },

    'wcgateway.settings.fields' => static function (ContainerInterface $container): array {
        $settings = $container->get('wcgateway.settings');
        $sandboxText = $settings->has('sandbox_on') && $settings->get('sandbox_on') ?
            __(
                'You are currently in the sandbox mode to test your installation. You can switch this, by clicking <button name="%1$s" value="%2$s">Reset</button>',
                'woocommerce-paypal-commerce-gateway'
            ) : __(
                'You are in live mode. This means, you can receive money into your account. You can switch this, by clicking <button name="%1$s" value="%2$s">Reset</button>',
                'woocommerce-paypal-commerce-gateway'
            );
        $sandboxText = sprintf(
            $sandboxText,
            'save',
            'reset'
        );

        $merchantEmailText = sprintf(
            __(
                'You are connected with your email address <mark>%1$s</mark>.
                If you want to change this settings, please click <button name="%2$s" value="%3$s">Reset</button>',
                'woocommerce-paypal-commerce-gateway'
            ),
            $settings->has('merchant_email') ? $settings->get('merchant_email') : '',
            'save',
            'reset'
        );
        return [
            'ppcp_onboarding' => [
                'title' => __('Connect to PayPal', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'ppcp_onboarding',
                'screens' => [
                    State::STATE_PROGRESSIVE,
                ],
            ],
            'sandbox_on' => [
                'title' => __('Sandbox', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'checkbox',
                'label' => __('To test your Woocommerce installation, you can use the sandbox mode.', 'woocommerce-paypal-commerce-gateway'),
                'default' => 0,
                'screens' => [
                    State::STATE_START,
                ],
            ],
            'sandbox_on_info' => [
                'title' => __('Sandbox', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'ppcp-text',
                'text' => $sandboxText,
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
                'hidden' => 'sandbox_on',
            ],
            'merchant_email' => [
                'title' => __('Email address', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __('The email address of your PayPal account.', 'woocommerce-paypal-commerce-gateway'),
                'default' => '',
                'screens' => [
                    State::STATE_START,
                ],
            ],
            'merchant_email_info' => [
                'title' => __('Email address', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'ppcp-text',
                'text' => $merchantEmailText,
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
                'hidden' => 'merchant_email',
            ],
            'title' => [
                'title' => __('Title', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'text',
                'description' => __(
                    'This controls the title which the user sees during checkout.',
                    'woocommerce-paypal-commerce-gateway'
                ),
                'default' => __('PayPal', 'woocommerce-paypal-commerce-gateway'),
                'desc_tip' => true,
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
            ],
            'description' => [
                'title' => __('Description', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __(
                    'This controls the description which the user sees during checkout.',
                    'woocommerce-paypal-commerce-gateway'
                ),
                'default' => __(
                    'Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account.',
                    'woocommerce-paypal-commerce-gateway'
                ),
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
            ],
            'intent' => [
                'title' => __('Intent', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'select',
                'class' => ['wc-enhanced-select'],
                'default' => 'capture',
                'desc_tip' => true,
                'description' => __(
                    'The intent to either capture payment immediately or authorize a payment for an order after order creation.',
                    'woocommerce-paypal-commerce-gateway'
                ),
                'options' => [
                    'capture' => __('Capture', 'woocommerce-paypal-commerce-gateway'),
                    'authorize' => __('Authorize', 'woocommerce-paypal-commerce-gateway'),
                ],
                'screens' => [
                    State::STATE_ONBOARDED,
                ],
            ],
            'button_single_product_enabled' => [
                'title' => __('Buttons on Single Product', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable on Single Product', 'woocommerce-paypal-commerce-gateway'),
                'default' => true,
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
            ],
            'button_mini_cart_enabled' => [
                'title' => __('Buttons on Mini Cart', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable on Mini Cart', 'woocommerce-paypal-commerce-gateway'),
                'default' => true,
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
            ],
            'button_cart_enabled' => [
                'title' => __('Buttons on Cart', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable on Cart', 'woocommerce-paypal-commerce-gateway'),
                'default' => true,
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
            ],
            'button_label' => [
                'title' => __('Button Label', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'select',
                'class' => ['wc-enhanced-select'],
                'default' => 'paypal',
                'desc_tip' => true,
                'description' => __(
                    'This controls the label on the primary button.',
                    'woocommerce-paypal-commerce-gateway'
                ),
                'options' => [
                    'paypal' => __('PayPal', 'woocommerce-paypal-commerce-gateway'),
                    'checkout' => __('PayPal Checkout', 'woocommerce-paypal-commerce-gateway'),
                    'buynow' => __('PayPal Buy Now', 'woocommerce-paypal-commerce-gateway'),
                    'pay' => __('Pay with PayPal', 'woocommerce-paypal-commerce-gateway'),
                ],
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
            ],
            'brand_name' => [
                'title' => __('Brand Name', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'text',
                'default' => get_bloginfo('name'),
                'desc_tip' => true,
                'description' => __(
                    'Control the name of your shop, customers will see in the PayPal process.',
                    'woocommerce-paypal-commerce-gateway'
                ),
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
            ],
            'button_color' => [
                'title' => __('Color', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'select',
                'class' => ['wc-enhanced-select'],
                'default' => 'gold',
                'desc_tip' => true,
                'description' => __(
                    'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
                    'woocommerce-paypal-commerce-gateway'
                ),
                'options' => [
                    'gold' => __('Gold (Recommended)', 'woocommerce-paypal-commerce-gateway'),
                    'blue' => __('Blue', 'woocommerce-paypal-commerce-gateway'),
                    'silver' => __('Silver', 'woocommerce-paypal-commerce-gateway'),
                    'black' => __('Black', 'woocommerce-paypal-commerce-gateway'),
                ],
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
            ],
            'button_shape' => [
                'title' => __('Shape', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'select',
                'class' => ['wc-enhanced-select'],
                'default' => 'rect',
                'desc_tip' => true,
                'description' => __(
                    'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
                    'woocommerce-paypal-commerce-gateway'
                ),
                'options' => [
                    'pill' => __('Pill', 'woocommerce-paypal-commerce-gateway'),
                    'rect' => __('Rectangle', 'woocommerce-paypal-commerce-gateway'),
                ],
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
            ],
            'disable_funding' => [
                'title' => __('Disable funding sources', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'ppcp-multiselect',
                'class' => ['wc-enhanced-select'],
                'default' => [],
                'desc_tip' => true,
                'description' => __(
                    'By default all possible funding sources will be shown. You can disable some sources, if you wish.',
                    'woocommerce-paypal-commerce-gateway'
                ),
                'options' => [
                    'card' => _x('Credit or debit cards', 'Name of payment method', 'woocommerce-paypal-commerce-gateway'),
                    'credit' => _x('PayPal Credit', 'Name of payment method', 'woocommerce-paypal-commerce-gateway'),
                    'venmo' => _x('Venmo', 'Name of payment method', 'woocommerce-paypal-commerce-gateway'),
                    'sepa' => _x('SEPA-Lastschrift', 'Name of payment method', 'woocommerce-paypal-commerce-gateway'),
                    'bancontact' => _x('Bancontact', 'Name of payment method', 'woocommerce-paypal-commerce-gateway'),
                    'eps' => _x('eps', 'Name of payment method', 'woocommerce-paypal-commerce-gateway'),
                    'giropay' => _x('giropay', 'Name of payment method', 'woocommerce-paypal-commerce-gateway'),
                    'ideal' => _x('iDEAL', 'Name of payment method', 'woocommerce-paypal-commerce-gateway'),
                    'mybank' => _x('MyBank', 'Name of payment method', 'woocommerce-paypal-commerce-gateway'),
                    'p24' => _x('Przelewy24', 'Name of payment method', 'woocommerce-paypal-commerce-gateway'),
                    'sofort' => _x('Sofort', 'Name of payment method', 'woocommerce-paypal-commerce-gateway'),
                ],
                'screens' => [
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
            ],
            'dcc_cart_enabled' => [
                'title' => __('Enable credit card on cart', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'checkbox',
                'label' => __('Allow your customers to pay with credit card directly in your cart.', 'woocommerce-paypal-commerce-gateway'),
                'default' => true,
                'screens' => [
                    State::STATE_ONBOARDED,
                ],
            ],
            'dcc_mini_cart_enabled' => [
                'title' => __('Enable credit card on mini cart', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'checkbox',
                'label' => __('Allow your customers to pay with credit card directly in your mini cart.', 'woocommerce-paypal-commerce-gateway'),
                'default' => true,
                'screens' => [
                    State::STATE_ONBOARDED,
                ],
            ],
            'dcc_checkout_enabled' => [
                'title' => __('Enable credit card on checkout', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'checkbox',
                'label' => __('Allow your customers to pay with credit card in the checkout.', 'woocommerce-paypal-commerce-gateway'),
                'default' => true,
                'screens' => [
                    State::STATE_ONBOARDED,
                ],
            ],
            'dcc_single_product_enabled' => [
                'title' => __('Enable credit card on products', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'checkbox',
                'label' => __('Allow your customers to pay with credit card instantly on the product page.', 'woocommerce-paypal-commerce-gateway'),
                'default' => true,
                'screens' => [
                    State::STATE_ONBOARDED,
                ],
            ],
            'disable_cards' => [
                'title' => __('Disable specific credid cards', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'ppcp-multiselect',
                'class' => ['wc-enhanced-select'],
                'default' => [],
                'desc_tip' => true,
                'description' => __(
                    'By default all possible credit cards will be shown. You can disable some cards, if you wish.',
                    'woocommerce-paypal-commerce-gateway'
                ),
                'options' => [
                    'visa' => _x('Visa', 'Name of credit card', 'woocommerce-paypal-commerce-gateway'),
                    'mastercard' => _x('Mastercard', 'Name of credit card', 'woocommerce-paypal-commerce-gateway'),
                    'amex' => _x('American Express', 'Name of credit card', 'woocommerce-paypal-commerce-gateway'),
                    'discover' => _x('Discover', 'Name of credit card', 'woocommerce-paypal-commerce-gateway'),
                    'jcb' => _x('JCB', 'Name of credit card', 'woocommerce-paypal-commerce-gateway'),
                    'elo' => _x('Elo', 'Name of credit card', 'woocommerce-paypal-commerce-gateway'),
                    'hiper' => _x('Hiper', 'Name of credit card', 'woocommerce-paypal-commerce-gateway'),
                ],
                'screens' => [
                    State::STATE_ONBOARDED,
                ],
            ],
            'logging_enabled' => [
                'title' => __('Logging', 'woocommerce-paypal-commerce-gateway'),
                'type' => 'checkbox',
                'desc_tip' => true,
                'label' => __('Enable logging', 'woocommerce-paypal-commerce-gateway'),
                'description' => __('Enable logging of unexpected behavior. This can also log private data and should only be enabled in a development or stage environment.', 'woocommerce-paypal-commerce-gateway'),
                'default' => false,
                'screens' => [
                    State::STATE_START,
                    State::STATE_PROGRESSIVE,
                    State::STATE_ONBOARDED,
                ],
            ],
        ];
    },
];
