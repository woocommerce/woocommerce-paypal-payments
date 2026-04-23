/**
 * Internal dependencies
 */
import { test, expect, annotateVisitor } from '../../../utils';

export const transactionsOnPayByLink = ( tests ) => {
	for ( const tested of tests ) {
		test(
			tested.title,
			annotateVisitor( tested.customer ),
			async ( {
				wooCommerceUtils,
				payForOrder,
				wooCommerceApi,
				orderReceived,
				ppapi,
				wooCommerceOrderEdit,
			} ) => {
				const order = await wooCommerceUtils.createApiOrder( tested );

				await payForOrder.makeOrder( tested, order );
				// Expect Order Received page to be loaded
				await orderReceived.assertOrderDetails( tested );

				await expect( order.id ).toEqual(
					await orderReceived.getOrderNumber()
				);
				const orderJson = await wooCommerceApi.getOrder( order.id );

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
				await wooCommerceOrderEdit.visit( order.id );
				await wooCommerceOrderEdit.assertOrderDetails(
					tested,
					pcpData
				);
			}
		);
	}
};
