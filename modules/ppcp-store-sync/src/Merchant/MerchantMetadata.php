<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Merchant;

/**
 * DTO containing merchant metadata for registration and catalog ingestion.
 */
class MerchantMetadata
{
    /**
     * Store name.
     *
     * @var string
     */
    public string $store_name;
    /**
     * Store URL (canonical, without a trailing slash).
     *
     * CRITICAL: This serves as the stable identifier for this merchant.
     *
     * @var string
     */
    public string $store_url;
    /**
     * The base REST-endpoint of the Cart API.
     *
     * @var string
     */
    public string $api_base_url;
    /**
     * Base country code (ISO 3166-1 alpha-2).
     *
     * @var string
     */
    public string $store_country;
    /**
     * Country of the PayPal merchant (ISO 3166-1 alpha-2).
     *
     * @var string
     */
    public string $merchant_country;
    /**
     * Base currency code (ISO 4217).
     *
     * @var string
     */
    public string $currency;
    /**
     * PayPal merchant ID.
     *
     * @var string
     */
    public string $paypal_merchant_id;
    /**
     * Catalog URL (same as store_url for push-based catalog ingestion).
     *
     * @var string
     */
    public string $catalog_url;
    /**
     * Constructor.
     *
     * @param string $store_name         Store name.
     * @param string $store_url          Store URL (canonical identifier).
     * @param string $api_base_url       The base URL for the cart API.
     * @param string $store_country      Base country code.
     * @param string $currency           Base currency code.
     * @param string $paypal_merchant_id PayPal merchant ID.
     * @param string $catalog_url        Catalog URL.
     * @param string $merchant_country   Merchant country code.
     */
    public function __construct(string $store_name, string $store_url, string $api_base_url, string $store_country, string $currency, string $paypal_merchant_id, string $catalog_url, string $merchant_country)
    {
        $this->store_name = $store_name;
        $this->store_url = $store_url;
        $this->api_base_url = $api_base_url;
        $this->store_country = $store_country;
        $this->currency = $currency;
        $this->paypal_merchant_id = $paypal_merchant_id;
        $this->catalog_url = $catalog_url;
        $this->merchant_country = $merchant_country;
    }
}
