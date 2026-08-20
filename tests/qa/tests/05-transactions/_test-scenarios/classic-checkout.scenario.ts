/**
 * Internal dependencies
 */
import { PayPalPaymentDetails, ShopOrder } from '../../../resources';
import {
	annotateVisitor,
	test,
	waitForOrderStatus,
} from '../../../utils';

export const transactionsOnClassicCheckout = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant, orderStatus } = testOrder;

	test(
		title,
		annotateVisitor( customer ),
		async ( {
			classicCheckout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
			utils,
		} ) => {
			const { title: gatewayTitle } = payment.gateway;
			const isAsyncCaptureGateway =
				gatewayTitle === 'Pay upon Invoice' || gatewayTitle === 'OXXO';

			if ( isAsyncCaptureGateway ) {
				test.setTimeout( 3 * 60_000 ); // 3 minutes for PUI/OXXO async capture
			}

			// PUI/OXXO capture completion relies on an async PayPal webhook. CI now
			// tunnels through ngrok, so the webhook can reach it and this no longer
			// needs to be skipped.
			// const skipCaptureWait = isAsyncCaptureGateway && !! process.env.CI;
			const skipCaptureWait = false;
			const syncOrderStatus = gatewayTitle === 'OXXO' ? 'pending' : 'on-hold';

			await test.step( `Add product(s) to the cart`, async () => {
				await utils.fillVisitorsCart( products );
			} );

			await test.step( `Visit Classic Checkout, make payment with ${ gatewayTitle }`, async () => {
				await classicCheckout.visit();
				await classicCheckout.completeCheckoutDetails( testOrder );
				await classicCheckout.payPalUi.makePayment( { merchant, payment, customer } );
			} );
				
			let orderId: number;
			let payPalPaymentDetails: PayPalPaymentDetails;

			await test.step( `Assert order received`, async () => {
				await orderReceived.assertOrderDetails( testOrder );
				await orderReceived.assertNoErrors();

				orderId = await orderReceived.getOrderNumber();

				if ( skipCaptureWait ) {
					return;
				}

				// TEMPORARY diagnostic for the ngrok webhook-delivery investigation.
				// Confirms whether PayPal generated a webhook event for this order
				// at all, independent of whether the tunnel could receive it.
				if ( isAsyncCaptureGateway ) {
					const paypalOrderId = await payPalApi.getOrderIdFromWooCommerce(
						await wooCommerceApi.getOrder( orderId )
					);
					if ( paypalOrderId ) {
						await payPalApi.logWebhookEventsForResource(
							merchant,
							paypalOrderId
						);
					}
				}

				await waitForOrderStatus( wooCommerceApi, orderId, {
					expectedStatus: orderStatus,
					timeout: isAsyncCaptureGateway ? 3 * 60_000 : undefined,
				} );
				const transactionId =
						( await wooCommerceApi.getOrder( orderId ) ).transaction_id;

				payPalPaymentDetails = await payPalApi.getPayPalPaymentDetails(
					transactionId,
					testOrder,
				);

				if ( payPalPaymentDetails && payPalPaymentDetails.amount !== '0' ) { // can be 0 for free trial or free orders; undefined for PUI
					await orderReceived.assertTotalEqualsPayPalTotal(
						payPalPaymentDetails.amount,
						testOrder.currency
					);
				}
			} );

			await test.step( `Assert details on order edit page`, async () => {
				await wooCommerceOrderEdit.visit( orderId );
				const orderEditData = skipCaptureWait
					? { ...testOrder, orderStatus: syncOrderStatus }
					: testOrder;
				await wooCommerceOrderEdit.assertOrderDetails( orderEditData, payPalPaymentDetails );
			} );
		}
	);
};
