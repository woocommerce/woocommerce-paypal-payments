<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;


trait SettingsTrait
{

    private function defaultFields() : array
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
}