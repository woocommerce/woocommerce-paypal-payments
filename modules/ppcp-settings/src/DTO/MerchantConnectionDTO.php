<?php

/**
 * Data transfer object. Stores all connection credentials of the PayPal merchant connection.
 *
 * @package WooCommerce\PayPalCommerce\Settings\DTO;
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\DTO;

use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;
/**
 * DTO that collects all details of a "merchant connection".
 *
 * Intentionally has no internal logic, sanitation or validation.
 */
class MerchantConnectionDTO
{
    /**
     * Whether this connection is a sandbox account.
     *
     * @var bool
     */
    public bool $is_sandbox = \false;
    /**
     * API client ID.
     *
     * @var string
     */
    public string $client_id = '';
    /**
     * API client secret.
     *
     * @var string
     */
    public string $client_secret = '';
    /**
     * PayPal's 13-character merchant ID.
     *
     * @var string
     */
    public string $merchant_id = '';
    /**
     * Email address of the merchant account.
     *
     * @var string
     */
    public string $merchant_email = '';
    /**
     * Merchant's country.
     *
     * @var string
     */
    public string $merchant_country = '';
    /**
     * Whether the merchant is a business or personal account.
     * Possible values: ['business'|'personal'|'unknown']
     *
     * @var string
     */
    public string $seller_type = SellerTypeEnum::UNKNOWN;
    /**
     * Constructor.
     *
     * @param bool   $is_sandbox       Whether this connection is a sandbox account.
     * @param string $client_id        API client ID.
     * @param string $client_secret    API client secret.
     * @param string $merchant_id      PayPal's 13-character merchant ID.
     * @param string $merchant_email   Email address of the merchant account.
     * @param string $merchant_country Merchant's country.
     * @param string $seller_type      Whether the merchant is a business or personal account.
     */
    public function __construct(bool $is_sandbox, string $client_id, string $client_secret, string $merchant_id, string $merchant_email = '', string $merchant_country = '', string $seller_type = SellerTypeEnum::UNKNOWN)
    {
        $this->is_sandbox = $is_sandbox;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->merchant_id = $merchant_id;
        $this->merchant_email = $merchant_email;
        $this->merchant_country = $merchant_country;
        $this->seller_type = $seller_type;
    }
}
