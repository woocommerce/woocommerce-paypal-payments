<?php

/**
 * Identifies a product that cannot produce a payable order.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WC_Product;
trait FreeProductHandlerTrait
{
    /**
     * Whether the product on view would make an order with nothing to charge.
     *
     * A free product with shipping to pay is still payable.
     */
    protected function is_unpayable_free_product(): bool
    {
        $product = wc_get_product();
        if (!$product instanceof WC_Product) {
            return \false;
        }
        // Priced from the cheapest variation, which may be free when others are not.
        if ($product->is_type(array('variable', 'variable-subscription'))) {
            return \false;
        }
        if ((float) wc_get_price_including_tax($product) > 0) {
            return \false;
        }
        return !$product->needs_shipping() || !wc_shipping_enabled();
    }
}
