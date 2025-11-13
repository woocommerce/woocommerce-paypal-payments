/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnClassicCheckout = ( testOrder: ShopOrder ) => {
	test(
		testOrder.title,
		annotateVisitor( testOrder.customer ),
		async ( {
			classicCheckout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
			utils,
		} ) => {
			await utils.fillVisitorsCart( testOrder.products );
			await classicCheckout.visit();
			await classicCheckout.makeOrder( testOrder );
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

export const transactionsOnClassicCheckoutOxxo = ( testOrder: ShopOrder ) => {
	test.fixme(
		testOrder.title,
		annotateVisitor( testOrder.customer ),
		async ( {
			classicCheckout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
			utils,
		} ) => {
			await utils.fillVisitorsCart( testOrder.products );
			await classicCheckout.visit();
			await classicCheckout.makeOrder( testOrder );
			// Expect Order Received page to be loaded
			await orderReceived.assertOrderDetails( testOrder );

			const orderId = await orderReceived.getOrderNumber();
			const orderJson = await wooCommerceApi.getOrder( orderId );

			const oxxoOrderId = await payPalApi.getOrderIdFromWooCommerce(
				orderJson
			);
			const oxxoOrder = await payPalApi.getOrder(
				oxxoOrderId,
				testOrder.merchant
			);
			const oxxoPaymentId = await payPalApi.getPaymentIdFromOrder(
				oxxoOrder,
				testOrder.payment
			);

			// await payPalApi.assertOrder( orderJson, testData );
			// await payPalApi.assertPayment( oxxoPaymentId, testData );
			// await wooCommerceOrderEdit.assertOrderDetails(
			// 	orderId,
			// 	testData
			// );
		}
	);
};
