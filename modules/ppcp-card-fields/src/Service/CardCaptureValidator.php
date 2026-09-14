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
     * The order can be captured; there is no rejection reason.
     */
    public const REASON_NONE = 'none';
    /**
     * The order carries no payment source, so it cannot be captured.
     */
    public const REASON_MALFORMED = 'malformed';
    /**
     * 3DS authentication was attempted but did not pass ( liability_shift = NO ).
     */
    public const REASON_3DS_FAILED = '3ds_failed';
    /**
     * The 3DS result is inconclusive ( liability_shift = UNKNOWN or absent ).
     */
    public const REASON_3DS_UNCLEAR = '3ds_unclear';
    /**
     * Checks whether an order is valid for capture.
     *
     * @param Order $order PayPal order.
     *
     * @return bool
     */
    public function is_valid(Order $order): bool
    {
        return $this->rejection_reason($order) === self::REASON_NONE;
    }
    /**
     * Determines why an order cannot be captured, if at all.
     *
     * @param Order $order PayPal order.
     *
     * @return string One of the REASON_* constants; REASON_NONE when the order can be captured.
     */
    public function rejection_reason(Order $order): string
    {
        $order_status = $order->status();
        if ($order_status->name() === OrderStatus::APPROVED) {
            return self::REASON_NONE;
        }
        $payment_source = $order->payment_source();
        if (!$payment_source) {
            return self::REASON_MALFORMED;
        }
        if ($payment_source->name() !== 'card') {
            return self::REASON_NONE;
        }
        /**
         * LiabilityShift determines how to proceed with authentication.
         *
         * @link https://developer.paypal.com/docs/checkout/advanced/customize/3d-secure/response-parameters/
         */
        $liability_shift = $payment_source->properties()->authentication_result->liability_shift ?? '';
        if (in_array($liability_shift, array('POSSIBLE', 'YES'), \true)) {
            return self::REASON_NONE;
        }
        if ($liability_shift === 'NO') {
            return self::REASON_3DS_FAILED;
        }
        return self::REASON_3DS_UNCLEAR;
    }
}
