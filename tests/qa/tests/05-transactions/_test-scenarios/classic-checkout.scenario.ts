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

			if( gatewayTitle === 'Pay upon Invoice' || gatewayTitle === 'OXXO' ) {
				test.setTimeout( 3 * 60_000 ); // 3 minutes for PUI/OXXO async capture
			}

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
				await wooCommerceOrderEdit.assertOrderDetails( testOrder, payPalPaymentDetails );
			} );
		}
	);
};
