<?php

/**
 * Manage the Seller status for Installments.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
class InstallmentsProductStatus extends ProductStatus
{
    public const KEY = 'products_installments_enabled';
    protected function check_api_response(SellerStatus $seller_status): bool
    {
        foreach ($seller_status->capabilities() as $capability) {
            if ($capability->name() !== 'INSTALLMENTS') {
                continue;
            }
            if ($capability->status() === 'ACTIVE') {
                return \true;
            }
        }
        return \false;
    }
    protected function get_cache_lifespan(bool $is_eligible): int
    {
        return MONTH_IN_SECONDS;
    }
}
