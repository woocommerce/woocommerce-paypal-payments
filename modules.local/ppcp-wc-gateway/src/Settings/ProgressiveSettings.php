<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

class ProgressiveSettings extends StartSettings implements SettingsFields
{

    public function fields(): array
    {
        $fields = parent::fields();
        $fields[] = [
            'type' => 'ppcp_onboarding',
        ];
        return $fields;
    }
}
