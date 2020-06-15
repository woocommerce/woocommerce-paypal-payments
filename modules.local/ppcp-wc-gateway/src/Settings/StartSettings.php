<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;


class StartSettings implements SettingsFields
{

    public function fields(): array
    {
        return [
            'merchant_email' => [
                'title' => __('Email', 'woocommerce-paypal-gateway'),
                'type' => 'email',
                'description' => __(
                    'Please enter the email address with which you want to receive payments.',
                    'woocommerce-paypal-gateway'
                ),
                'default' => '',
                'desc_tip' => true,
            ],];
    }
}