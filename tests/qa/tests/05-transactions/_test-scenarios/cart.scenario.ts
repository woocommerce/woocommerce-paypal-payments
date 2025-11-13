/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { test, annotateVisitor } from '../../../utils';

export const transactionsOnCart = ( testOrder: ShopOrder ) => {
	test(
		testOrder.title,
		annotateVisitor( testOrder.customer ),
		async ( {
			cart,
			checkout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
			utils,
		} ) => {
			await utils.fillVisitorsCart( testOrder.products );
			await cart.visit();
			await cart.makeOrder( testOrder );
			await checkout.fillCheckoutForm( testOrder.customer );
			await checkout.placeOrder();

			// Expect Order Received page to be loaded
			await orderReceived.assertOrderDetails( testOrder );

			const orderId = await orderReceived.getOrderNumber();
			const { transaction_id: transactionId } =
				await wooCommerceApi.getOrder( orderId );
			const payPalFee = await payPalApi.getFee(
				transactionId,
				testOrder
			);
			const payPalPayout = await payPalApi.getPayout(
				transactionId,
				testOrder
			);
			const pcpData = { transactionId, payPalFee, payPalPayout };

			// await payPalApi.assertOrder( orderJson, testData );
			// await payPalApi.assertPayment(
			// 	orderJson.transaction_id,
			// 	testData
			// );

			await wooCommerceOrderEdit.assertOrderDetails(
				orderId,
				testOrder,
				pcpData
			);
		}
	);
};
