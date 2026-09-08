<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Merchant;

use WooCommerce;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Endpoint\AgenticRestEndpoint;
/**
 * Provides merchant metadata for registration and catalog operations.
 *
 * This is the single source of truth for store identification across
 * registration and catalog ingestion.
 */
class MerchantMetadataProvider
{
    private WooCommerce $wc;
    private GeneralSettings $general_settings;
    private StoreCurrencyValue $store_currency;
    public function __construct(WooCommerce $wc, GeneralSettings $general_settings, StoreCurrencyValue $store_currency)
    {
        $this->wc = $wc;
        $this->general_settings = $general_settings;
        $this->store_currency = $store_currency;
    }
    /**
     * Get current merchant metadata.
     */
    public function get_metadata(): \WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadata
    {
        $merchant = $this->get_merchant_connection();
        $merchant_id = '';
        if ($this->general_settings->is_merchant_connected()) {
            $merchant_id = $merchant->merchant_id;
        }
        return new \WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadata(get_bloginfo('name'), $this->get_canonical_store_url(), $this->get_api_base_url(), $this->wc->countries->get_base_country(), $this->store_currency->value(), $merchant_id, $this->get_canonical_store_url(), $merchant->merchant_country);
    }
    /**
     * Get canonical store URL used as a stable identifier.
     *
     * CRITICAL: This must remain stable between registration and catalog ingestion.
     * The store URL serves as the primary key for identifying this merchant.
     */
    private function get_canonical_store_url(): string
    {
        return untrailingslashit(get_site_url());
    }
    private function get_api_base_url(): string
    {
        return rest_url(AgenticRestEndpoint::NAMESPACE);
    }
    private function get_merchant_connection(): MerchantConnectionDTO
    {
        return $this->general_settings->get_merchant_data();
    }
}
