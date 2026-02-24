/**
 * Internal dependencies
 */
import { orders, payPal, acdc, storeConfigUsa } from '../../../resources';
import { annotateVisitor, test } from '../../../utils';

const customer = storeConfigUsa.customer;
const initOrders: { [ key: string ]: any } = {
	PayPal: {
		...orders.default,
		payment: payPal,
		customer,
	},
	ACDC: {
		...orders.default,
		payment: acdc,
		customer,
	},
};

export const vaultingtransactionsOnClassicCheckout = ( tests ) => {
	test.describe( tests.gateway.method, () => {
		for ( const tested of tests.tests ) {
			const paymentMethod = tested.payment.method;
			const initOrder = initOrders[ paymentMethod ];

			// test.fixme(
			// 	tested.title,
			// 	annotateVisitor( tested.customer ),
			// 	async ( {
			// 		utils,
			// 		wooCommerceUtils,
			// 		customerPaymentMethods,
			// 		classicCheckout,
			// 		wooCommerceApi,
			// 		orderReceived,
			// 		ppapi,
			// 		wooCommerceOrderEdit,
			// 	} ) => {
			// 		// Make initial order to save payment method
			// 		// TODO: currently can't be moved to beforeAll to preserve PayPal session
			// 		if (
			// 			! ( await customerPaymentMethods.isSavedPaymentMethod(
			// 				initOrder.payment
			// 			) )
			// 		) {
			// 			await utils.completeOrderOnClassicCheckout( initOrder );
			// 		}

			// 		// Make actual tested order:
			// 		await utils.fillVisitorsCart(
			// 			tested.products
			// 		);

			// 		await classicCheckout.makeOrder( tested );
			// 		await orderReceived.assertOrderDetails( tested );

			// 		const orderId = await orderReceived.getOrderNumber();
			// 		const orderJson = await wooCommerceApi.getOrder( orderId );

			// 		const pcpData = {
			// 			transactionId: orderJson.transaction_id,
			// 			payPalFee: await ppapi.getFee(
			// 				orderJson.transaction_id,
			// 				tested
			// 			),
			// 			payPalPayout: await ppapi.getPayout(
			// 				orderJson.transaction_id,
			// 				tested
			// 			),
			// 		};

			// 		await ppapi.assertOrder( orderJson, tested );
			// 		await ppapi.assertPayment(
			// 			orderJson.transaction_id,
			// 			tested
			// 		);
				// await wooCommerceOrderEdit.visit( orderId );
			// 		await wooCommerceOrderEdit.assertOrderDetails(
			// 			tested,
			// 			pcpData
			// 		);
			// 	}
			// );
		}
	} );
};
