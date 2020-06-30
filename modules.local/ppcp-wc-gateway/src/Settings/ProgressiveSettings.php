<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\Onboarding\Environment;

class ProgressiveSettings implements SettingsFields
{

    use SettingsTrait;

    public function __construct(Environment $environment)
    {
        $this->environment = $environment;
    }

    public function fields(): array
    {
        $fields = array_merge(
            [
                'onboarding' => [
                    'type' => 'ppcp_onboarding',
                ],
            ],
            $this->defaultFields(),
            [
                'reset' => [
                    'type' => 'ppcp_reset',
                ],
            ]
        );
        return $fields;
    }
}
