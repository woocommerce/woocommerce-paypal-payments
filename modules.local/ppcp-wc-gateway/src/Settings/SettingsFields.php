<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

class SettingsFields
{
    public function fields(): array
    {
        return array_merge(
            $this->gateway(),
            $this->account(),
            $this->buttons(),
        );
    }

    protected function gateway()
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
        ];
    }

    protected function account()
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

    protected function buttons()
    {
        return [
            'button_settings' => [
                'title' => __('Button Settings', 'woocommerce-paypal-gateway'),
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
        ];
    }
}
