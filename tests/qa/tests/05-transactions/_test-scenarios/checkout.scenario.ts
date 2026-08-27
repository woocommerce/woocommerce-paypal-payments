/**
 * Internal dependencies
 */
import { PayPalPaymentDetails, ShopOrder } from '../../../resources';
import { annotateVisitor, test, waitForOrderStatus } from '../../../utils';

export const transactionsOnCheckout = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant, orderStatus } = testOrder;

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
			const { title: gatewayTitle } = payment.gateway;
			const isAsyncCaptureGateway =
				gatewayTitle === 'Pay upon Invoice' || gatewayTitle === 'OXXO';

			if ( isAsyncCaptureGateway ) {
				test.setTimeout( 3 * 60_000 ); // 3 minutes for PUI/OXXO async capture
			}

			// PUI/OXXO capture completion relies on an async PayPal webhook. This
			// used to be unreachable from the ephemeral CI environment, so CI
			// skipped waiting for it and finished assertions with the order still
			// in its synchronous, pre-capture status. Webhooks now reach CI via
			// the Cloudflare tunnel, so this restriction is no longer needed.
			// const skipCaptureWait = isAsyncCaptureGateway && !! process.env.CI;
			const skipCaptureWait = false;
			const syncOrderStatus = gatewayTitle === 'OXXO' ? 'pending' : 'on-hold';

			await test.step( `Add product(s) to the cart`, async () => {
				await utils.fillVisitorsCart( products );
			} );

			await test.step( `Visit Checkout, make payment with ${ gatewayTitle }`, async () => {
				await checkout.visit();
				await checkout.completeCheckoutDetails( testOrder );
				await checkout.payPalUi.makePayment( { merchant, payment } );
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

				await waitForOrderStatus( wooCommerceApi, orderId, {
					expectedStatus: orderStatus,
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
