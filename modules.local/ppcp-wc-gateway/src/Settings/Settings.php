<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\WcGateway\Gateway\WcGateway;

class Settings
{
    private $gateway;
    private $formFields;

    public function __construct(WcGateway $gateway, SettingsFields $formFields)
    {
        $this->gateway = $gateway;
        $this->formFields = $formFields;
    }

    // phpcs:ignore Inpsyde.CodeQuality.ReturnTypeDeclaration.NoReturnType
    public function get(string $settingsKey)
    {
        return $this->gateway->get_option($settingsKey);
    }
}
