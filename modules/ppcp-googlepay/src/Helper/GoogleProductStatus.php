<?php

/**
 * Status of the GooglePay merchant connection.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Googlepay\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
class GoogleProductStatus extends ProductStatus
{
    public const KEY = 'products_googlepay_enabled';
    public const CAPABILITY_NAME = 'GOOGLE_PAY';
    public function check_local_state(bool $skip_filters = \false): ?bool
    {
        $state = null;
        if (!$skip_filters) {
            $state = apply_filters('woocommerce_paypal_payments_google_pay_product_status', null);
        }
        return is_bool($state) ? $state : parent::check_local_state();
    }
    protected function check_api_response(SellerStatus $seller_status): bool
    {
        foreach ($seller_status->products() as $product) {
            if ($product->name() !== 'PAYMENT_METHODS') {
                continue;
            }
            if (in_array(self::CAPABILITY_NAME, $product->capabilities(), \true)) {
                return \true;
            }
        }
        foreach ($seller_status->capabilities() as $capability) {
            if ($capability->name() === self::CAPABILITY_NAME && $capability->status() === SellerStatusCapability::STATUS_ACTIVE) {
                return \true;
            }
        }
        return \false;
    }
}
