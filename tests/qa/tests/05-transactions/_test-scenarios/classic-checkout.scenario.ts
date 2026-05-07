/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test, expect } from '../../../utils';

export const transactionsOnClassicCheckout = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant } = testOrder;

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
			await classicCheckout.payPalUi.makePayment( {
				merchant,
				payment,
				customer,
			} );
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

			await wooCommerceOrderEdit.visit( orderId );
			await wooCommerceOrderEdit.assertOrderDetails( testOrder, pcpData );
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

			// Open the PayPal sandbox popup via the thank-you page voucher button and simulate payment.
			const popupPromise = orderReceived.page.waitForEvent( 'popup' );
			await orderReceived.seeOXXOVoucherButton_1().click();
			const voucherPopup = await popupPromise;

			// Detach the popup from its opener so PayPal's sandbox JS cannot
			// redirect the thank-you page via window.opener after the simulation.
			await voucherPopup.evaluate( () => {
				window.opener = null;
			} );

			await voucherPopup
				.getByRole( 'button', { name: 'Test Successful Payment' } )
				.click();

			await voucherPopup
				.waitForEvent( 'close', { timeout: 15_000 } )
				.catch( () => voucherPopup.close() );

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
