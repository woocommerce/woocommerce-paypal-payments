/**
 * Internal dependencies
 */
import { test, annotateVisitor } from '../../../utils';

export const transactionsOnCart = ( tests ) => {
	for ( const tested of tests ) {
		test(
			tested.title,
			annotateVisitor( tested.customer ),
			async ( {
				cart,
				checkout,
				wooCommerceApi,
				orderReceived,
				ppapi,
				wooCommerceOrderEdit,
				utils,
			} ) => {
				await utils.fillVisitorsCart( tested.products );

				await cart.makeOrder( tested );
				await checkout.fillCheckoutForm( tested.customer );
				await checkout.placeOrder();

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
