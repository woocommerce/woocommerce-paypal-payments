/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnCheckout = ( testOrder: ShopOrder ) => {
	const { title, payment, products, customer, merchant } = testOrder;

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

			await test.step( `Add product(s) to the cart`, async () => {
				await utils.fillVisitorsCart( products );
			} );

			await test.step( `Visit Checkout, make payment with ${ gatewayTitle }`, async () => {
				await checkout.visit();
				await checkout.completeCheckoutDetails( testOrder );
				await checkout.payPalUi.makePayment( { merchant, payment } );
			} );
				
			let orderId: number;
			let pcpData: {
				transactionId: string;
				payPalTotal: string;
				payPalFee: string;
				payPalPayout: string;
			};

			await test.step( `Assert order received`, async () => {
				await orderReceived.assertOrderDetails( testOrder );
				await orderReceived.assertNoErrors();

				orderId = await orderReceived.getOrderNumber();				
				const transactionId =
						( await wooCommerceApi.getOrder( orderId ) ).transaction_id;

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
				await wooCommerceOrderEdit.visit( orderId );
				await wooCommerceOrderEdit.assertOrderDetails( testOrder, pcpData );
			} );
		}
	);
};
