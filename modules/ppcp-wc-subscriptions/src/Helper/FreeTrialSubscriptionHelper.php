<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcSubscriptions\Helper;

use WC_Subscriptions;
use WC_Subscriptions_Product;
class FreeTrialSubscriptionHelper
{
    /**
     * Checks if the cart contains only free trial.
     */
    public function is_free_trial_cart(): bool
    {
        if (!$this->is_wcs_plugin_active()) {
            return \false;
        }
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty() || (float) $cart->get_total('numeric') > 0) {
            return \false;
        }
        foreach ($cart->get_cart() as $item) {
            $product = $item['data'] ?? null;
            if ($product && WC_Subscriptions_Product::is_subscription($product) && !$product->get_meta('ppcp_subscription_plan')) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * Whether the subscription plugin is active or not.
     */
    protected function is_wcs_plugin_active(): bool
    {
        return class_exists(WC_Subscriptions::class);
    }
}
