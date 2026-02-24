/**
 * Internal dependencies
 */
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnClassicCheckout = ( tests ) => {
	for ( const tested of tests ) {
		test(
			tested.title,
			annotateVisitor( tested.customer ),
			async ( {
				classicCheckout,
				wooCommerceApi,
				orderReceived,
				ppapi,
				wooCommerceOrderEdit,
				utils,
			} ) => {
				await utils.fillVisitorsCart( tested.products );

				await classicCheckout.makeOrder( tested );
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

export const transactionsOnClassicCheckoutOxxo = ( tests ) => {
	for ( const tested of tests ) {
		test(
			tested.title,
			annotateVisitor( tested.customer ),
			async ( {
				wooCommerceUtils,
				classicCheckout,
				wooCommerceApi,
				orderReceived,
				ppapi,
				wooCommerceOrderEdit,
				utils,
			} ) => {
				await utils.fillVisitorsCart( tested.products );

				await classicCheckout.makeOrder( tested );
				// Expect Order Received page to be loaded
				await orderReceived.assertOrderDetails( tested );

				const orderId = await orderReceived.getOrderNumber();
				const orderJson = await wooCommerceApi.getOrder( orderId );

				const oxxoOrderId = await ppapi.getOrderIdFromWooCommerce(
					orderJson
				);
				const oxxoOrder = await ppapi.getOrder(
					oxxoOrderId,
					tested.merchant
				);
				const oxxoPaymentId = await ppapi.getPaymentIdFromOrder(
					oxxoOrder,
					tested.payment
				);

				await ppapi.assertOrder( orderJson, tested );
				await ppapi.assertPayment( oxxoPaymentId, tested );
				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertOrderDetails(
					tested
				);
			}
		);
	}
};
