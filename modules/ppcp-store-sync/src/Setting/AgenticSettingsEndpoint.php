<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Setting;

use WooCommerce\PayPalCommerce\Settings\Extension\ExtensionRestEndpoint;
class AgenticSettingsEndpoint extends ExtensionRestEndpoint
{
    protected const PATH = 'store-sync';
    protected function sanitize_rest_data(array $data): ?array
    {
        return $data;
    }
}
