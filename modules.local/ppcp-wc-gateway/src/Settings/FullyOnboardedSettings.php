<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\Onboarding\Environment;

class FullyOnboardedSettings extends StartSettings implements SettingsFields
{

    use SettingsTrait;
    public function __construct(Environment $environment)
    {
        $this->environment = $environment;
    }

    public function fields(): array
    {
        return array_merge(
            $this->gateway(),
            $this->buttons(),
            $this->creditCards()
        );
    }

    private function gateway(): array
    {
        return array_merge(
            $this->defaultFields(),
            [
            'intent' => [
                'title' => __('Intent', 'woocommerce-paypal-gateway'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'capture',
                'desc_tip' => true,
                'description' => __(
                    'The intent to either capture payment immediately or authorize a payment for an order after order creation.',
                    'woocommerce-paypal-gateway'
                ),
                'options' => [
                    'capture' => __('Capture', 'woocommerce-paypal-gateway'),
                    'authorize' => __('Authorize', 'woocommerce-paypal-gateway'),
                ],
            ],
            ]
        );
    }


    private function creditCards(): array
    {

        return [

            'credit_card_settings' => [
                'title' => __('Credit Card Settings', 'woocommerce-paypal-gateway'),
                'type' => 'title',
                'description' => __(
                    'Customize the appearance of Credit Card Payments on your site.',
                    'woocommerce-paypal-gateway'
                ),
            ],
            'dcc_cart_enabled' => [
                'title' => __('Enable credit card on cart', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __('Allow your customers to pay with credit card directly in your cart.', 'woocommerce-paypal-gateway'),
                'default' => 'yes',
            ],
            'dcc_mini_cart_enabled' => [
                'title' => __('Enable credit card on mini cart', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __('Allow your customers to pay with credit card directly in your mini cart.', 'woocommerce-paypal-gateway'),
                'default' => 'yes',
            ],
            'dcc_checkout_enabled' => [
                'title' => __('Enable credit card on checkout', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __('Allow your customers to pay with credit card in the checkout.', 'woocommerce-paypal-gateway'),
                'default' => 'yes',
            ],
            'dcc_single_product_enabled' => [
                'title' => __('Enable credit card on products', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __('Allow your customers to pay with credit card instantly on the product page.', 'woocommerce-paypal-gateway'),
                'default' => 'yes',
            ],
            'disable_cards' => [
                'title' => __('Disable specific credid cards', 'woocommerce-paypal-gateway'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'default' => [],
                'desc_tip' => true,
                'description' => __(
                    'By default all possible credit cards will be shown. You can disable some cards, if you wish.',
                    'woocommerce-paypal-gateway'
                ),
                'options' => [
                    'visa' => _x('Visa', 'Name of credit card', 'woocommerce-paypal-gateway'),
                    'mastercard' => _x('Mastercard', 'Name of credit card', 'woocommerce-paypal-gateway'),
                    'amex' => _x('American Express', 'Name of credit card', 'woocommerce-paypal-gateway'),
                    'discover' => _x('Discover', 'Name of credit card', 'woocommerce-paypal-gateway'),
                    'jcb' => _x('JCB', 'Name of credit card', 'woocommerce-paypal-gateway'),
                    'elo' => _x('Elo', 'Name of credit card', 'woocommerce-paypal-gateway'),
                    'hiper' => _x('Hiper', 'Name of credit card', 'woocommerce-paypal-gateway'),
                ],
            ],
        ];
    }
}
