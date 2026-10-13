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
        $cart = WC()->cart;
        // Cheap check first: cart_requires_vaulting() walks every item.
        if (!$cart || (float) $cart->get_total('numeric') > 0) {
            return \false;
        }
        return $this->cart_requires_vaulting();
    }
    /**
     * Whether the cart holds a subscription whose renewals are paid from a
     * vaulted payment method rather than billed by PayPal against a plan.
     *
     * `is_free_trial_cart()` without the total, i.e. the half a coupon cannot
     * change, for callers that pair it with a live total of their own.
     */
    public function cart_requires_vaulting(): bool
    {
        if (!$this->is_wcs_plugin_active()) {
            return \false;
        }
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
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
