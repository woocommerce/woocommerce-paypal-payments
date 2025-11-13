/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnClassicCart = ( testsData: ShopOrder[] ) => {
	for ( const testData of testsData ) {
		test(
			testData.title,
			annotateVisitor( testData.customer ),
			async ( {
				classicCart,
				classicCheckout,
				wooCommerceApi,
				orderReceived,
				payPalApi,
				wooCommerceOrderEdit,
				utils,
			} ) => {
				await utils.fillVisitorsCart( testData.products );
				await classicCart.visit();
				await classicCart.makeOrder( testData );
				await classicCheckout.fillCheckoutForm( testData.customer );
				await classicCheckout.placeOrder();
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
