<?php
/**
 * The API for operations with orders.
 *
 * @package WooCommerce\PayPalCommerce\Api
 *
 * @phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Api;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\PPCP;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\RefundFeesUpdater;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;

/**
 * Returns the PayPal order.
 *
 * @param string|WC_Order $paypal_id_or_wc_order The ID of PayPal order or a WC order (with the ID in meta).
 * @throws InvalidArgumentException When the argument cannot be used for retrieving the order.
 * @throws Exception When the operation fails.
 */
function ppcp_get_paypal_order( $paypal_id_or_wc_order ): Order {
	if ( $paypal_id_or_wc_order instanceof WC_Order ) {
		$paypal_id_or_wc_order = $paypal_id_or_wc_order->get_meta( PayPalGateway::ORDER_ID_META_KEY );
		if ( ! $paypal_id_or_wc_order ) {
			throw new InvalidArgumentException( 'PayPal order ID not found in meta.' );
		}
	}
	if ( ! is_string( $paypal_id_or_wc_order ) ) {
		throw new InvalidArgumentException( 'Invalid PayPal order ID, string expected.' );
	}

	$order_endpoint = PPCP::container()->get( 'api.endpoint.order' );
	assert( $order_endpoint instanceof OrderEndpoint );

	return $order_endpoint->order( $paypal_id_or_wc_order );
}

/**
 * Captures the PayPal order.
 *
 * @param WC_Order $wc_order The WC order.
 * @throws InvalidArgumentException When the order cannot be captured.
 * @throws Exception When the operation fails.
 */
function ppcp_capture_order( WC_Order $wc_order ): void {
	$intent = strtoupper( (string) $wc_order->get_meta( PayPalGateway::INTENT_META_KEY ) );

	if ( $intent !== 'AUTHORIZE' ) {
		throw new InvalidArgumentException( 'Only orders with "authorize" intent can be captured.' );
	}
	$captured = wc_string_to_bool( $wc_order->get_meta( AuthorizedPaymentsProcessor::CAPTURED_META_KEY ) );
	if ( $captured ) {
		throw new InvalidArgumentException( 'The order is already captured.' );
	}

	$authorized_payment_processor = PPCP::container()->get( 'wcgateway.processor.authorized-payments' );
	assert( $authorized_payment_processor instanceof AuthorizedPaymentsProcessor );

	if ( ! $authorized_payment_processor->capture_authorized_payment( $wc_order ) ) {
		throw new RuntimeException( 'Capture failed.' );
	}
}

/**
 * Refunds the PayPal order.
 * Note that you can use wc_refund_payment() to trigger the refund in WC and PayPal.
 *
 * @param WC_Order $wc_order The WC order.
 * @param float    $amount The refund amount.
 * @param string   $reason The reason for the refund.
 * @return string The PayPal refund ID.
 * @throws InvalidArgumentException When the order cannot be refunded.
 * @throws Exception When the operation fails.
 */
function ppcp_refund_order( WC_Order $wc_order, float $amount, string $reason = '' ): string {
	$order = ppcp_get_paypal_order( $wc_order );

	$refund_processor = PPCP::container()->get( 'wcgateway.processor.refunds' );
	assert( $refund_processor instanceof RefundProcessor );

	return $refund_processor->refund( $order, $wc_order, $amount, $reason );
}

/**
 * Voids the authorization.
 *
 * @param WC_Order $wc_order The WC order.
 * @throws InvalidArgumentException When the order cannot be voided.
 * @throws Exception When the operation fails.
 */
function ppcp_void_order( WC_Order $wc_order ): void {
	$order = ppcp_get_paypal_order( $wc_order );

	$refund_processor = PPCP::container()->get( 'wcgateway.processor.refunds' );
	assert( $refund_processor instanceof RefundProcessor );

	$refund_processor->void( $order );
}

/**
 * Updates the PayPal refund fees totals on an order.
 *
 * @param WC_Order $wc_order The WC order.
 */
function ppcp_update_order_refund_fees( WC_Order $wc_order ): void {
	$updater = PPCP::container()->get( 'wcgateway.helper.refund-fees-updater' );
	assert( $updater instanceof RefundFeesUpdater );
	$updater->update( $wc_order );
}
