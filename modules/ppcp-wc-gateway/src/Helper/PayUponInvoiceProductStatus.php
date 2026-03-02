<?php

/**
 * Manage the Seller status.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
class PayUponInvoiceProductStatus extends ProductStatus
{
    public const KEY = 'products_pui_enabled';
    protected function check_api_response(SellerStatus $seller_status): bool
    {
        foreach ($seller_status->products() as $product) {
            if ($product->name() !== 'PAYMENT_METHODS') {
                continue;
            }
            if (!in_array($product->vetting_status(), array(SellerStatusProduct::VETTING_STATUS_APPROVED, SellerStatusProduct::VETTING_STATUS_SUBSCRIBED), \true)) {
                continue;
            }
            if (in_array('PAY_UPON_INVOICE', $product->capabilities(), \true)) {
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
