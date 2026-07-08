/**
 * Internal dependencies
 */
import { PayPalPaymentDetails, ShopOrder } from '../../../resources';
import {
	annotateVisitor,
	test,
	expect,
	OxxoVoucherPopup,
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

			if( gatewayTitle === 'Pay upon Invoice' ) {
				test.setTimeout( 3 * 60_000 ); // 3 minutes for PUI
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

				if ( payPalPaymentDetails && payPalPaymentDetails.amount !== '0' ) { // can be 0 for free trial or free orders, OXXO, PUI
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

export const transactionsOnClassicCheckoutOxxo = ( testOrder: ShopOrder ) => {
	const { payment, merchant } = testOrder;

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
			await classicCheckout.completeCheckoutDetails( testOrder );
			await classicCheckout.payPalUi.makePayment( { merchant, payment } );
			await orderReceived.assertOrderDetails( testOrder );

			const orderId = await orderReceived.getOrderNumber();
			const orderJson = await wooCommerceApi.getOrder( orderId );

			const oxxoOrderId = await payPalApi.getOrderIdFromWooCommerce(
				orderJson
			);

			await expect(
				orderReceived.seeOXXOVoucherButton_1(),
				'Assert OXXO voucher button is visible on order-received page'
			).toBeVisible();
			const popupPromise = orderReceived.page.waitForEvent( 'popup' );
			await orderReceived.seeOXXOVoucherButton_1().click();
			const voucherPopup = new OxxoVoucherPopup( await popupPromise );
			await voucherPopup.simulate();

			// Poll until the real webhook is processed and order status updates
			await expect.poll(
				async () => {
					const order = await wooCommerceApi.getOrder( orderId );
					return order.status;
				},
				{
					message: 'Assert OXXO order status is processing after capture webhook',
					timeout: 60_000,
					intervals: [ 2_000, 3_000, 5_000 ],
				}
			).toEqual( 'processing' );

			const oxxoOrder = await payPalApi.getOrder(
				oxxoOrderId,
				testOrder.merchant
			);
			const oxxoPaymentId = await payPalApi.getPaymentIdFromOrder(
				oxxoOrder,
				testOrder.payment
			);
			const pcpData = { transactionId: oxxoPaymentId };

			await wooCommerceOrderEdit.visit( orderId );
			await wooCommerceOrderEdit.assertOrderDetails( testOrder, pcpData );
		}
	);
};
