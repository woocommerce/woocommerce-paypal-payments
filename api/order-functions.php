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
use WooCommerce\PayPalCommerce\PPCP;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;

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
