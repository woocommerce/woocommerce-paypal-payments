<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

class ProgressiveSettings extends StartSettings implements SettingsFields
{

    public function fields(): array
    {
        return array_merge(
            [
                'onboarding' => [
                    'type' => 'ppcp_onboarding',
                ],
            ],
            parent::fields(),
            [
                'reset' => [
                    'type' => 'ppcp_reset',
                ],
            ]
        );
    }
}
