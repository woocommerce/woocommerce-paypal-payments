/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { test, expect, annotateVisitor } from '../../../utils';

export const transactionsOnPayByLink = ( testsData: ShopOrder[] ) => {
	for ( const testData of testsData ) {
		test(
			testData.title,
			annotateVisitor( testData.customer ),
			async ( {
				wooCommerceUtils,
				payForOrder,
				wooCommerceApi,
				orderReceived,
				payPalApi,
				wooCommerceOrderEdit,
			} ) => {
				const order = await wooCommerceUtils.createApiOrder( testData );

				await payForOrder.makeOrder( testData, order );
				// Expect Order Received page to be loaded
				await orderReceived.assertOrderDetails( testData );

				await expect( order.id ).toEqual(
					await orderReceived.getOrderNumber()
				);
				const orderJson = await wooCommerceApi.getOrder( order.id );

				const pcpData = {
					transactionId: orderJson.transaction_id,
					payPalFee: await payPalApi.getFee(
						orderJson.transaction_id,
						testData
					),
					payPalPayout: await payPalApi.getPayout(
						orderJson.transaction_id,
						testData
					),
				};

				// await payPalApi.assertOrder( orderJson, testData );
				// await payPalApi.assertPayment(
				// 	orderJson.transaction_id,
				// 	testData
				// );
				await wooCommerceOrderEdit.assertOrderDetails(
					order.id,
					testData,
					pcpData
				);
			}
		);
	}
};
