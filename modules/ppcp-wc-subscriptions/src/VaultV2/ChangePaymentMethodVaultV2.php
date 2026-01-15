<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcSubscriptions\VaultV2;

use Exception;
use WC_Order;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
class ChangePaymentMethodVaultV2
{
    private Context $context;
    public function __construct(Context $context)
    {
        $this->context = $context;
    }
    /**
     * @throws Exception If changing payment fails.
     */
    public function to_paypal_payment(WC_Order $wc_order): bool
    {
        if (!$this->context->is_subscription_change_payment_method_page()) {
            return \true;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $saved_paypal_payment = wc_clean(wp_unslash($_POST['saved_paypal_payment'] ?? ''));
        if ($saved_paypal_payment && is_numeric($saved_paypal_payment)) {
            $payment_token = WC_Payment_Tokens::get((int) $saved_paypal_payment);
            if ($payment_token) {
                $wc_order->add_payment_token($payment_token);
                $wc_order->save();
                return \false;
            }
            throw new Exception(__('Could not change payment.', 'woocommerce-paypal-payments'));
        }
        return \true;
    }
}
