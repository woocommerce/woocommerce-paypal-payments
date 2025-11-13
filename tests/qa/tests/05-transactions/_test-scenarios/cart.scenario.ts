/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { test, annotateVisitor } from '../../../utils';

export const transactionsOnCart = ( testsData: ShopOrder[] ) => {
	for ( const testData of testsData ) {
		test(
			testData.title,
			annotateVisitor( testData.customer ),
			async ( {
				cart,
				checkout,
				wooCommerceApi,
				orderReceived,
				payPalApi,
				wooCommerceOrderEdit,
				utils,
			} ) => {
				await utils.fillVisitorsCart( testData.products );
				await cart.visit();
				await cart.makeOrder( testData );
				await checkout.fillCheckoutForm( testData.customer );
				await checkout.placeOrder();

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
