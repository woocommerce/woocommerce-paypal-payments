/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnClassicCheckout = ( testsData: ShopOrder[] ) => {
	for ( const testData of testsData ) {
		test(
			testData.title,
			annotateVisitor( testData.customer ),
			async ( {
				classicCheckout,
				wooCommerceApi,
				orderReceived,
				payPalApi,
				wooCommerceOrderEdit,
				utils,
			} ) => {
				await utils.fillVisitorsCart( testData.products );

				await classicCheckout.makeOrder( testData );
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
				await payPalApi.assertPayment(
					orderJson.transaction_id,
					testData
				);
				await wooCommerceOrderEdit.assertOrderDetails(
					orderId,
					testData,
					pcpData
				);
			}
		);
	}
};

export const transactionsOnClassicCheckoutOxxo = ( testsData ) => {
	for ( const testData of testsData ) {
		test(
			testData.title,
			annotateVisitor( testData.customer ),
			async ( {
				classicCheckout,
				wooCommerceApi,
				orderReceived,
				payPalApi,
				wooCommerceOrderEdit,
				utils,
			} ) => {
				await utils.fillVisitorsCart( testData.products );

				await classicCheckout.makeOrder( testData );
				// Expect Order Received page to be loaded
				await orderReceived.assertOrderDetails( testData );

				const orderId = await orderReceived.getOrderNumber();
				const orderJson = await wooCommerceApi.getOrder( orderId );

				const oxxoOrderId = await payPalApi.getOrderIdFromWooCommerce(
					orderJson
				);
				const oxxoOrder = await payPalApi.getOrder(
					oxxoOrderId,
					testData.merchant
				);
				const oxxoPaymentId = await payPalApi.getPaymentIdFromOrder(
					oxxoOrder,
					testData.payment
				);

				await payPalApi.assertOrder( orderJson, testData );
				await payPalApi.assertPayment( oxxoPaymentId, testData );
				await wooCommerceOrderEdit.assertOrderDetails(
					orderId,
					testData
				);
			}
		);
	}
};
