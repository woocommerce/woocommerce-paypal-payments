<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

//phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong

class SettingsFields
{
    public function fields(): array
    {
        return array_merge(
            $this->gateway(),
            $this->account(),
            $this->buttons(),
            $this->creditCards(),
        );
    }

    private function gateway(): array
    {
        return [
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable PayPal Payments', 'woocommerce-paypal-gateway'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title', 'woocommerce-paypal-gateway'),
                'type' => 'text',
                'description' => __(
                    'This controls the title which the user sees during checkout.',
                    'woocommerce-paypal-gateway'
                ),
                'default' => __('PayPal', 'woocommerce-paypal-gateway'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'woocommerce-paypal-gateway'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __(
                    'This controls the description which the user sees during checkout.',
                    'woocommerce-paypal-gateway'
                ),
                'default' => __(
                    'Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account.',
                    'woocommerce-paypal-gateway'
                ),
            ],
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
        ];
    }

    private function account(): array
    {
        return [
            'account_settings' => [
                'title' => __('Account Settings', 'woocommerce-paypal-gateway'),
                'type' => 'title',
                'description' => '',
            ],
            'sandbox_on' => [
                'title' => __('Enable Sandbox', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __(
                    'For testing your integration, you can enable the sandbox.',
                    'woocommerce-paypal-gateway'
                ),
                'default' => 'yes',
            ],
        ];
    }

    private function buttons(): array
    {
        return [
            'button_settings' => [
                'title' => __('SmartButton Settings', 'woocommerce-paypal-gateway'),
                'type' => 'title',
                'description' => __(
                    'Customize the appearance of PayPal Payments on your site.',
                    'woocommerce-paypal-gateway'
                ),
            ],
            'button_single_product_enabled' => [
                'title' => __('Buttons on Single Product', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable on Single Product', 'woocommerce-paypal-gateway'),
                'default' => 'yes',
            ],
            'button_mini_cart_enabled' => [
                'title' => __('Buttons on Mini Cart', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable on Mini Cart', 'woocommerce-paypal-gateway'),
                'default' => 'yes',
            ],
            'button_cart_enabled' => [
                'title' => __('Buttons on Cart', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable on Cart', 'woocommerce-paypal-gateway'),
                'default' => 'yes',
            ],
            'button_color' => [
                'title' => __('Color', 'woocommerce-paypal-gateway'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'gold',
                'desc_tip' => true,
                'description' => __(
                    'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
                    'woocommerce-paypal-gateway'
                ),
                'options' => [
                    'gold' => __('Gold (Recommended)', 'woocommerce-paypal-gateway'),
                    'blue' => __('Blue', 'woocommerce-paypal-gateway'),
                    'silver' => __('Silver', 'woocommerce-paypal-gateway'),
                    'black' => __('Black', 'woocommerce-paypal-gateway'),
                ],
            ],
            'button_shape' => [
                'title' => __('Shape', 'woocommerce-paypal-gateway'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'rect',
                'desc_tip' => true,
                'description' => __(
                    'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
                    'woocommerce-paypal-gateway'
                ),
                'options' => [
                    'pill' => __('Pill', 'woocommerce-paypal-gateway'),
                    'rect' => __('Rectangle', 'woocommerce-paypal-gateway'),
                ],
            ],
            'disable_funding' => [
                'title' => __('Disable funding sources', 'woocommerce-paypal-gateway'),
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'default' => [],
                'desc_tip' => true,
                'description' => __(
                    'By default all possible funding sources will be shown. You can disable some sources, if you wish.',
                    'woocommerce-paypal-gateway'
                ),
                'options' => [
                    'card' => _x('Credit or debit cards', 'Name of payment method', 'woocommerce-paypal-gateway'),
                    'credit' => _x('PayPal Credit', 'Name of payment method', 'woocommerce-paypal-gateway'),
                    'venmo' => _x('Venmo', 'Name of payment method', 'woocommerce-paypal-gateway'),
                    'sepa' => _x('SEPA-Lastschrift', 'Name of payment method', 'woocommerce-paypal-gateway'),
                    'bancontact' => _x('Bancontact', 'Name of payment method', 'woocommerce-paypal-gateway'),
                    'eps' => _x('eps', 'Name of payment method', 'woocommerce-paypal-gateway'),
                    'giropay' => _x('giropay', 'Name of payment method', 'woocommerce-paypal-gateway'),
                    'ideal' => _x('iDEAL', 'Name of payment method', 'woocommerce-paypal-gateway'),
                    'mybank' => _x('MyBank', 'Name of payment method', 'woocommerce-paypal-gateway'),
                    'p24' => _x('Przelewy24', 'Name of payment method', 'woocommerce-paypal-gateway'),
                    'sofort' => _x('Sofort', 'Name of payment method', 'woocommerce-paypal-gateway'),
                ],
            ],
        ];
    }

    private function creditCards() : array {
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
