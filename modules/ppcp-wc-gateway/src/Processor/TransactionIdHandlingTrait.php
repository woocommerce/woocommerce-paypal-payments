<?php

/**
 * Functions for retrieving/saving order transaction ID.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
/**
 * Trait PaymentsStatusHandlingTrait.
 */
trait TransactionIdHandlingTrait
{
    /**
     * Sets transaction ID to the WC order.
     *
     * @param string               $transaction_id The transaction ID to set.
     * @param WC_Order             $wc_order The order to set transaction ID to.
     * @param LoggerInterface|null $logger The logger to log errors.
     *
     * @return bool
     */
    public function update_transaction_id(string $transaction_id, WC_Order $wc_order, ?LoggerInterface $logger = null): bool
    {
        try {
            $wc_order->set_transaction_id($transaction_id);
            $wc_order->save();
            $wc_order->add_order_note(sprintf(
                /* translators: %s is the PayPal transaction ID */
                __('PayPal transaction ID: %s', 'woocommerce-paypal-payments'),
                $transaction_id
            ));
            return \true;
        } catch (Exception $exception) {
            if ($logger) {
                $logger->warning(sprintf('Failed to set transaction ID %1$s. %2$s', $transaction_id, $exception->getMessage()));
            }
            return \false;
        }
    }
    /**
     * Retrieves transaction id from PayPal order.
     *
     * @param Order $order The order to get transaction id from.
     *
     * @return string|null
     */
    public function get_paypal_order_transaction_id(Order $order): ?string
    {
        $purchase_unit = $order->purchase_units()[0] ?? null;
        if (!$purchase_unit) {
            return null;
        }
        $payments = $purchase_unit->payments();
        if (null === $payments) {
            return null;
        }
        foreach ($payments->captures() as $capture) {
            if ($this->is_dead_capture($capture)) {
                continue;
            }
            return $capture->id();
        }
        foreach ($payments->authorizations() as $authorization) {
            if ($authorization->status()->is(AuthorizationStatus::DENIED)) {
                continue;
            }
            return $authorization->id();
        }
        return null;
    }
    /**
     * Whether a capture never took money and so is not this order's transaction.
     *
     * Storing one as the WooCommerce transaction id is not merely inaccurate: an order
     * carrying that meta is treated as already paid, so every later attempt on it -
     * a Subscriptions retry, or the shopper's own order-pay page - is skipped, and the
     * order cannot be paid again without the meta being removed by hand.
     *
     * PENDING is deliberately absent: it may yet settle, so its id is the reference to
     * keep. So are the refunded states, which describe money that did move.
     */
    private function is_dead_capture(Capture $capture): bool
    {
        return $capture->status()->is(CaptureStatus::DECLINED) || $capture->status()->is(CaptureStatus::FAILED);
    }
}
