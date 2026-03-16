<?php

/**
 * Status of the ApplePay merchant connection.
 *
 * @package WooCommerce\PayPalCommerce\Applepay\Assets
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Applepay\Assets;

use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
class AppleProductStatus extends ProductStatus
{
    public const KEY = 'products_apple_enabled';
    public const CAPABILITY_NAME = 'APPLE_PAY';
    public function check_local_state(bool $skip_filters = \false): ?bool
    {
        $state = null;
        if (!$skip_filters) {
            $state = apply_filters('woocommerce_paypal_payments_apple_pay_product_status', null);
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
