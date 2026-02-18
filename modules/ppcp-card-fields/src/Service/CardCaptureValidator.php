<?php

/**
 * Service for checking if an order with card payment source can be captured.
 *
 * @package WooCommerce\PayPalCommerce\CardFields\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\CardFields\Service;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
/**
 * CardCaptureValidator class.
 */
class CardCaptureValidator
{
    /**
     * Checks whether an order is valid for capture.
     *
     * @param Order $order PayPal order.
     *
     * @return bool
     */
    public function is_valid(Order $order): bool
    {
        $order_status = $order->status();
        if ($order_status->name() === OrderStatus::APPROVED) {
            return \true;
        }
        $payment_source = $order->payment_source();
        if (!$payment_source) {
            return \false;
        }
        if ($payment_source->name() !== 'card') {
            return \true;
        }
        /**
         * LiabilityShift determines how to proceed with authentication.
         *
         * @link https://developer.paypal.com/docs/checkout/advanced/customize/3d-secure/response-parameters/
         */
        $liability_shift = $payment_source->properties()->authentication_result->liability_shift ?? '';
        return in_array($liability_shift, array('POSSIBLE', 'YES'), \true);
    }
}
