/**
 * Internal dependencies
 */
import {
	storeConfigSubscriptionUsa,
	orders,
	payPal,
	acdc,
} from '../../../resources';
import { test, expect, annotateVisitor } from '../../../utils';

const customer = storeConfigSubscriptionUsa.customer;
const initOrders: { [ key: string ]: WooCommerce.ShopOrder } = {
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

export const subscriptiontransactionsOnClassicCheckout = ( tests ) => {
	for ( const tested of tests ) {
		const paymentMethod = tested.payment.method;
		const initOrder = initOrders[ paymentMethod ];

		test(
			tested.title,
			annotateVisitor( tested.customer ),
			async ( {
				customerSubscriptions,
				customerPaymentMethods,
				utils,
				classicCheckout,
				wooCommerceApi,
				orderReceived,
				ppapi,
				wooCommerceOrderEdit,
				wooCommerceSubscriptionEdit,
			} ) => {
				// Make initial order to save payment method
				// TODO: currently can't be moved to beforeAll to preserve PayPal session
				if (
					! ( await customerPaymentMethods.isSavedPaymentMethod(
						initOrder.payment
					) )
				) {
					await utils.completeOrderOnClassicCheckout( initOrder );
				}

				// Make tested order:
				await utils.fillVisitorsCart( tested.products );
				await classicCheckout.makeOrder( tested );

				// Assert Order Details page
				await orderReceived.assertOrderDetails( tested );
				const orderId = await orderReceived.getOrderNumber();
				const subscriptionId =
					await orderReceived.getSubscriptionNumber();
				await expect(
					orderReceived.subscriptionStatusCell()
				).toHaveText( 'Active' );
				await expect(
					orderReceived.viewSubscriptionButton()
				).toBeVisible();

				// Assert My Subscriptions page
				await customerSubscriptions.visit( subscriptionId );
				await expect(
					customerSubscriptions.paymentMethod()
				).toHaveText( `Via ${ tested.payment.gatewayName }` );

				// Assert PayPal API
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
				// Assert Subscription in the dashboard
				await wooCommerceSubscriptionEdit.visit( subscriptionId );
				await wooCommerceSubscriptionEdit.assertSubscriptionDetails(
					{
						...tested,
						transactionId: orderJson.transaction_id,
					}
				);
			}
		);
	}
};
