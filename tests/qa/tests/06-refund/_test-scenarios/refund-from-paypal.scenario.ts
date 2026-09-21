/**
 * External dependencies
 */
import { countTotals, getAmountPercentage } from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import {
	test,
	expect,
	annotateVisitor,
	waitForOrderStatus,
	waitForTransactionId,
} from '../../../utils';
import { ShopRefund } from '../../../resources';

/**
 * Refund initiated on PayPal's side, not from WooCommerce.
 *
 * The existing testRefund scenario refunds through the WooCommerce order-edit
 * screen, which calls the PayPal API directly and updates the order in the same
 * request - the webhook is irrelevant to whether it passes. Here the refund is
 * issued straight to PayPal, as a merchant refunding from the PayPal dashboard
 * would, so WooCommerce has no way of hearing about it except the
 * PAYMENT.CAPTURE.REFUNDED webhook. That makes the refund showing up in
 * WooCommerce a genuine end-to-end assertion of webhook delivery.
 *
 * @param testData The refund test data.
 */
export const testRefundFromPayPal = ( testData: ShopRefund ) => {
	const {
		title,
		customer,
		refundPercentage,
		refundOrderStatus,
		payment,
		currency,
		merchant,
		products,
	} = testData;

	test(
		title,
		annotateVisitor( customer ),
		async ( {
			utils,
			classicCheckout,
			orderReceived,
			wooCommerceApi,
			payPalApi,
		} ) => {
			// Generous: the refund has to round-trip through PayPal and come
			// back as a webhook, the same budget the async-capture gateways get.
			test.setTimeout( 3 * 60_000 );

			let orderId: number;
			let captureId: string;
			const total = await countTotals( testData );
			const isFullRefund = refundPercentage === 100;
			const refundAmount = getAmountPercentage(
				total.order,
				refundPercentage
			);

			await test.step( 'Precondition: place a paid order', async () => {
				await utils.fillVisitorsCart( products );
				await classicCheckout.visit();
				await classicCheckout.completeCheckoutDetails( testData );
				await classicCheckout.payPalUi.makePayment( {
					merchant,
					payment,
				} );

				await orderReceived.page.waitForLoadState();
				orderId = await orderReceived.getOrderNumber();

				await waitForOrderStatus( wooCommerceApi, orderId, {
					expectedStatus: 'processing',
					timeout: 30_000,
				} );
				captureId = await waitForTransactionId(
					wooCommerceApi,
					orderId
				);
			} );

			await test.step( 'Refund the capture through PayPal, bypassing WooCommerce', async () => {
				const payPalRefund = await payPalApi.refundCapture(
					captureId,
					merchant,
					isFullRefund
						? undefined
						: {
								currency_code: currency,
								value: Number( refundAmount ).toFixed( 2 ),
						  }
				);

				expect(
					payPalRefund.status,
					'Assert PayPal accepted the refund'
				).toEqual( 'COMPLETED' );
			} );

			await test.step( 'Assert WooCommerce learns about the refund via webhook', async () => {
				// Nothing in WooCommerce initiated this refund, so reaching the
				// refunded state at all proves PAYMENT.CAPTURE.REFUNDED arrived.
				await waitForOrderStatus( wooCommerceApi, orderId, {
					expectedStatus: refundOrderStatus,
					timeout: 2.5 * 60_000,
				} );

				const order = await wooCommerceApi.getOrder( orderId );
				expect(
					order.refunds,
					'Assert the webhook created a refund on the order'
				).not.toHaveLength( 0 );
				expect(
					order.refunds[ 0 ].total,
					'Assert the refund is for the expected amount'
				).toEqual( `-${ Number( refundAmount ).toFixed( 2 ) }` );
			} );
		}
	);
};
