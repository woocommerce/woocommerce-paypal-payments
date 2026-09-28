/**
 * External dependencies
 */
import {
	countTotals,
	getAmountPercentage,
} from '@inpsyde/playwright-utils/build';
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
 * Refunds on PayPal's side, so only the PAYMENT.CAPTURE.REFUNDED webhook can update the order.
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
			// Same budget as the async-capture gateways: the refund comes back as a webhook.
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
				// Only the refund webhook can move the order to refunded.
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
