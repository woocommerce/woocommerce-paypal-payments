/**
 * Internal dependencies
 */
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnCheckout = ( tests ) => {
	for ( const tested of tests ) {
		test(
			tested.title,
			annotateVisitor( tested.customer ),
			async ( {
				checkout,
				wooCommerceApi,
				orderReceived,
				ppapi,
				wooCommerceOrderEdit,
				utils,
			} ) => {
				await utils.fillVisitorsCart( tested.products );

				await checkout.makeOrder( tested );
				// Expect Order Received page to be loaded
				await orderReceived.assertOrderDetails( tested );

				const orderId = await orderReceived.getOrderNumber();
				const orderJson = await wooCommerceApi.getOrder( orderId );

				const pcpData = {
					transactionId: orderJson.transaction_id,
					payPalFee: await ppapi.getFee(
						orderJson.transaction_id,
						tested
					),
					payPalPayout: await ppapi.getPayout(
						orderJson.transaction_id,
						tested
					),
				};

				await ppapi.assertOrder( orderJson, tested );
				await ppapi.assertPayment( orderJson.transaction_id, tested );
				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertOrderDetails(
					tested,
					pcpData
				);
			}
		);
	}
};
