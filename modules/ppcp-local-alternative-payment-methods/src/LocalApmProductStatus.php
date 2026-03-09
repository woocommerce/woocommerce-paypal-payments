<?php

/**
 * Status of local alternative payment methods.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
class LocalApmProductStatus extends ProductStatus
{
    public const KEY = 'products_local_apms_enabled';
    protected function check_api_response(SellerStatus $seller_status): bool
    {
        foreach ($seller_status->capabilities() as $capability) {
            if ('ACTIVE' !== $capability->status()) {
                continue;
            }
            if ('PAYPAL_CHECKOUT_ALTERNATIVE_PAYMENT_METHODS' === $capability->name()) {
                return \true;
            }
        }
        return \false;
    }
}
