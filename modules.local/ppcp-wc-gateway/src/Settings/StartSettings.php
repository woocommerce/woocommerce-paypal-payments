<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

class StartSettings implements SettingsFields
{
    use SettingsTrait;

    public function fields(): array
    {
        return [
            'merchant_email' => [
                'title' => __('PayPal Email', 'woocommerce-paypal-gateway'),
                'type' => 'email',
                'description' => __(
                    'Please enter the email address with which you want to receive payments.',
                    'woocommerce-paypal-gateway'
                ),
                'default' => '',
                'desc_tip' => true,
            ],
            'sandbox_on' => [
                'title' => __('Enable Sandbox', 'woocommerce-paypal-gateway'),
                'type' => 'checkbox',
                'label' => __(
                    'For testing your integration, you can enable the sandbox.',
                    'woocommerce-paypal-gateway'
                ),
                'default' => 'no',
            ],
        ];
    }
}
