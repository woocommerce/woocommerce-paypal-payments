<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Setting;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Settings\Extension\ExtensionSettingsModule;
use WooCommerce\PayPalCommerce\Settings\Extension\ExtensionRestEndpoint;
use WooCommerce\PayPalCommerce\StoreSync\Registration\RegistrationEligibility;
class AgenticSettingsModule extends ExtensionSettingsModule
{
    private RegistrationEligibility $eligibility_check;
    public function __construct(ExtensionRestEndpoint $settings_endpoint, RegistrationEligibility $eligibility_check, AssetGetter $asset_getter)
    {
        parent::__construct($settings_endpoint, $asset_getter);
        $this->eligibility_check = $eligibility_check;
    }
    protected function is_available(): bool
    {
        return $this->eligibility_check->is_eligible();
    }
}
