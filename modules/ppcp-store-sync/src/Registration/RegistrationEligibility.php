<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Registration;

use WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadataProvider;
class RegistrationEligibility
{
    private MerchantMetadataProvider $metadata_provider;
    public function __construct(MerchantMetadataProvider $metadata_provider)
    {
        $this->metadata_provider = $metadata_provider;
    }
    public function is_eligible(): bool
    {
        $merchant = $this->metadata_provider->get_metadata();
        if (!$merchant->paypal_merchant_id) {
            return \false;
        }
        $store_country = strtoupper(trim($merchant->store_country));
        $merchant_country = strtoupper(trim($merchant->merchant_country));
        // todo: shipping country must be US.
        return 'US' === $store_country && 'US' === $merchant_country;
    }
}
