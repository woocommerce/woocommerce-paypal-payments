<?php

/**
 * Status of the Pay With Crypto merchant connection.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
class PWCProductStatus extends ProductStatus
{
    public const KEY = 'products_pwc_enabled';
    public const CAPABILITY_NAME = 'CRYPTO_PYMTS';
    protected function check_api_response(SellerStatus $seller_status): bool
    {
        foreach ($seller_status->capabilities() as $capability) {
            if ($capability->name() === self::CAPABILITY_NAME && $capability->status() === SellerStatusCapability::STATUS_ACTIVE) {
                return \true;
            }
        }
        return \false;
    }
}
