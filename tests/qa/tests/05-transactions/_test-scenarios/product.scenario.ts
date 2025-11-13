/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnProduct = ( testsData: ShopOrder[] ) => {
	for ( const testData of testsData ) {
		const { products, payment, merchant } = testData;
		
		test(
			testData.title,
			annotateVisitor( testData.customer ),
			async ( {
				product,
				checkout,
				wooCommerceApi,
				orderReceived,
				payPalApi,
				wooCommerceOrderEdit,
			} ) => {
				await product.visit( products[ 0 ].slug );
				await product.payPalUi.makePayment( { merchant, payment } );
				await checkout.completeOrderFromProduct( testData );
				// Expect Order Received page to be loaded
				await orderReceived.assertOrderDetails( testData );

				const orderId = await orderReceived.getOrderNumber();
				const { transaction_id: transactionId } =
					await wooCommerceApi.getOrder( orderId );
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
					orderId,
					testData,
					pcpData
				);
			}
		);
	}
};
