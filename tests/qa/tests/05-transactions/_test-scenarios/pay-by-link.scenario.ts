/**
 * Internal dependencies
 */
import { PayPalPaymentDetails, ShopOrder } from '../../../resources';
import {
	test,
	expect,
	annotateVisitor,
	waitForOrderStatus,
} from '../../../utils';

export const transactionsOnPayByLink = ( testOrder: ShopOrder ) => {
	const { payment, merchant, orderStatus } = testOrder;

	test(
		testOrder.title,
		annotateVisitor( testOrder.customer ),
		async ( {
			wooCommerceUtils,
			payForOrder,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
		} ) => {
			let order: WooCommerce.Order;
			const { title: gatewayTitle } = payment.gateway;

			if( gatewayTitle === 'Pay upon Invoice' ) {
				test.setTimeout( 3 * 60_000 ); // 3 minutes for PUI
			}

			await test.step( `Precondition: create order via API (dashboard)`, async () => {
				order = await wooCommerceUtils.createApiOrder( testOrder );
			} );

			await test.step( `Visit Pay for Order, make payment with ${ gatewayTitle }`, async () => {
				await payForOrder.visit( order.id, order.order_key );
				await payForOrder.payPalUi.makePayment( { merchant, payment } );
			} );

			let payPalPaymentDetails: PayPalPaymentDetails;

			await test.step( `Assert order received`, async () => {
				await orderReceived.assertOrderDetails( testOrder );
				await orderReceived.assertNoErrors();

				const orderNumber = await orderReceived.getOrderNumber();
				await expect(
					order.id,
					`Assert order ID (${ order.id }) matches order number on Order Received page`
				).toEqual( orderNumber );				
				
				await waitForOrderStatus( wooCommerceApi, order.id, {
					expectedStatus: orderStatus,
				} );
				const transactionId =
						( await wooCommerceApi.getOrder( order.id ) ).transaction_id;

				payPalPaymentDetails = await payPalApi.getPayPalPaymentDetails(
					transactionId,
					testOrder,
				);

				if ( payPalPaymentDetails && payPalPaymentDetails.amount !== '0' ) { // can be 0 for free trial or free orders, OXXO, PUI
					await orderReceived.assertTotalEqualsPayPalTotal(
						payPalPaymentDetails.amount,
						testOrder.currency
					);
				}
			} );

			await test.step( `Assert details on order edit page`, async () => {
				await wooCommerceOrderEdit.visit( order.id );
				await wooCommerceOrderEdit.assertOrderDetails( testOrder, payPalPaymentDetails );
			} );
		}
	);
};
