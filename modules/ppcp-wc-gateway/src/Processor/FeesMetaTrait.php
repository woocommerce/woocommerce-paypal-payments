<?php

/**
 * Operations with PayPal fees metadata.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerReceivableBreakdown;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Trait FeesMetaTrait.
 */
trait FeesMetaTrait
{
    /**
     * Stores the PayPal fee breakdown of a payment on the order, which
     * FeesRenderer shows as the PayPal fee and payout.
     *
     * @param WC_Order                  $wc_order  The WC order the payment belongs to.
     * @param SellerReceivableBreakdown $breakdown The payment's fee breakdown.
     */
    protected function store_paypal_fees(WC_Order $wc_order, SellerReceivableBreakdown $breakdown): void
    {
        $wc_order->update_meta_data(PayPalGateway::FEES_META_KEY, $breakdown->to_array());
        $paypal_fee = $breakdown->paypal_fee();
        if ($paypal_fee) {
            $wc_order->update_meta_data('PayPal Transaction Fee', (string) $paypal_fee->value());
        }
        $wc_order->save_meta_data();
    }
}
