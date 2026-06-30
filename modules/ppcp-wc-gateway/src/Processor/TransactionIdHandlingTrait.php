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
        $capture = $payments->captures()[0] ?? null;
        if ($capture) {
            return $capture->id();
        }
        $authorization = $payments->authorizations()[0] ?? null;
        if ($authorization) {
            return $authorization->id();
        }
        return null;
    }
}
