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
                'sandbox_on' => [
                    'title' => __('Enable Sandbox', 'woocommerce-paypal-gateway'),
                    'type' => 'checkbox',
                    'label' => __(
                        'For testing your integration, you can enable the sandbox.',
                        'woocommerce-paypal-gateway'
                    ),
                    'default' => 'no',
                ],
            ]
        );
    }
}
