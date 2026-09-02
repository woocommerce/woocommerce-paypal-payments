<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcSubscriptions\Service;

use WooCommerce\PayPalCommerce\Button\Helper\Context;
class ChangePaymentMethod
{
    private Context $context;
    public function __construct(Context $context)
    {
        $this->context = $context;
    }
    public function to_paypal_payment(): bool
    {
        if (!$this->context->is_subscription_change_payment_method_page()) {
            return \true;
        }
        return \false;
    }
}
