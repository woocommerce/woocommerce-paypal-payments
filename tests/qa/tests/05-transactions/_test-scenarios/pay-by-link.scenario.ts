/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { test, expect, annotateVisitor } from '../../../utils';

export const transactionsOnPayByLink = ( testOrder: ShopOrder ) => {
	const { payment, merchant } = testOrder;

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

			await test.step( `Precondition: create order via API (dashboard)`, async () => {
				order = await wooCommerceUtils.createApiOrder( testOrder );
			} );

			await test.step( `Visit Pay for Order, make payment with ${ gatewayTitle }`, async () => {
				await payForOrder.visit( order.id, order.order_key );
				await payForOrder.payPalUi.makePayment( { merchant, payment } );
			} );

			let pcpData: {
				transactionId: string;
				payPalTotal: string;
				payPalFee: string;
				payPalPayout: string;
			};

			await test.step( `Assert order received`, async () => {
				await orderReceived.assertOrderDetails( testOrder );
				await orderReceived.assertNoErrors();

				const orderNumber = await orderReceived.getOrderNumber();
				await expect(
					order.id,
					`Assert order ID (${ order.id }) matches order number on Order Received page`
				).toEqual( orderNumber );				
				
				const transactionId =
						( await wooCommerceApi.getOrder( order.id ) ).transaction_id;

				const payPalSellerReceivableBreakdown =
					await payPalApi.getSellerReceivableBreakdown(
						transactionId,
						testOrder
					);
					
				const payPalTotal = payPalSellerReceivableBreakdown?.gross_amount?.value;
				const payPalFee = payPalSellerReceivableBreakdown?.paypal_fee?.value;
				const payPalPayout = payPalSellerReceivableBreakdown?.net_amount?.value;

				pcpData = {
					transactionId,
					payPalTotal,
					payPalFee,
					payPalPayout,
				};

				
				if( payPalTotal ) { // can be 0 for free trial or free orders
					await orderReceived.assertTotalEqualsPayPalTotal( payPalTotal, testOrder.currency );
				}
			} );

			await test.step( `Assert details on order edit page`, async () => {
				await wooCommerceOrderEdit.visit( order.id );
				await wooCommerceOrderEdit.assertOrderDetails( testOrder, pcpData );
			} );
		}
	);
};
