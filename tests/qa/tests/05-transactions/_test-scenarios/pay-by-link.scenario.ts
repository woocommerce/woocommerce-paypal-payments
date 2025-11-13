/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { test, expect, annotateVisitor } from '../../../utils';

export const transactionsOnPayByLink = ( testsData: ShopOrder[] ) => {
	for ( const testData of testsData ) {
		const { payment, merchant } = testData;
		
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

				await payForOrder.visit( order.id, order.order_key );
				await payForOrder.payPalUi.makePayment( { merchant, payment } );
				// Expect Order Received page to be loaded
				await orderReceived.assertOrderDetails( testData );

				await expect( order.id ).toEqual(
					await orderReceived.getOrderNumber()
				);
				const { transaction_id: transactionId } =
					await wooCommerceApi.getOrder( order.id );
				const payPalFee = await payPalApi.getFee(
					transactionId,
					testData
				);
				const payPalPayout = await payPalApi.getPayout(
					transactionId,
					testData
				);
				const pcpData = { transactionId, payPalFee, payPalPayout };

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
