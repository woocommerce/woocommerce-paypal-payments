/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnCheckout = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant } = testOrder;

	test(
		title,
		annotateVisitor( customer ),
		async ( {
			checkout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
			utils,
		} ) => {
			await utils.fillVisitorsCart( products );
			await checkout.visit();
			await checkout.completeCheckoutDetails( testOrder );
			await checkout.payPalUi.makePayment( { merchant, payment } );
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
