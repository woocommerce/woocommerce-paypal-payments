/**
 * Internal dependencies
 */
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnClassicProduct = ( testsData ) => {
	for ( const testData of testsData ) {
		test(
			testData.title,
			annotateVisitor( testData.customer ),
			async ( {
				product,
				classicCheckout,
				wooCommerceApi,
				orderReceived,
				payPalApi,
				wooCommerceOrderEdit,
			} ) => {
				await product.makeOrder( testData );
				await classicCheckout.completeOrderFromProduct( testData );
				// Expect Order Received page to be loaded
				await orderReceived.assertOrderDetails( testData );

				const orderId = await orderReceived.getOrderNumber();
				const orderJson = await wooCommerceApi.getOrder( orderId );

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

				await payPalApi.assertOrder( orderJson, testData );
				await payPalApi.assertPayment( orderJson.transaction_id, testData );
				await wooCommerceOrderEdit.assertOrderDetails(
					orderId,
					testData,
					pcpData
				);
			}
		);
	}
};
