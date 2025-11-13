/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnClassicProduct = ( testOrder: ShopOrder ) => {
	const { products, payment, merchant } = testOrder;
	
	test(
		testOrder.title,
		annotateVisitor( testOrder.customer ),
		async ( {
			product,
			classicCheckout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
		} ) => {

			await product.visit( products[ 0 ].slug );
			await product.payPalUi.makePayment( { merchant, payment } );
			await classicCheckout.completeOrderFromProduct( testOrder );
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
