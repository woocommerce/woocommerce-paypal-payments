<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;


class StartSettings implements SettingsFields
{

    use SettingsTrait;

    public function fields(): array
    {
        return array_merge(
            $this->defaultFields(),
            [
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
            ]
        );
    }
}