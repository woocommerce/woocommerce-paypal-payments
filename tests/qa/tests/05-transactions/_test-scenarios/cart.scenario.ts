/**
 * Internal dependencies
 */
import { PayPalPaymentDetails, ShopOrder } from '../../../resources';
import { test, annotateVisitor } from '../../../utils';

export const transactionsOnCart = ( testOrder: ShopOrder ) => {
	const { products, payment, merchant, coupons, customer, shipping } =
		testOrder;

	test(
		testOrder.title,
		annotateVisitor( testOrder.customer ),
		async ( {
			cart,
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

			await test.step( `Visit Cart, make payment with ${ gatewayTitle }`, async () => {
				await cart.visit();
				// Add coupons if needed
				for ( const coupon of coupons ?? [] ) {
					await cart.applyCoupon( coupon.code );
				}
				await cart.selectShippingMethod( shipping.settings.title );
				await cart.payPalUi.makePayment( { merchant, payment } );

				await checkout.fillCheckoutForm( customer );
				await checkout.placeOrder();
			} );
				
			let orderId: number;
			let payPalPaymentDetails: PayPalPaymentDetails;

			await test.step( `Assert order received`, async () => {
				await orderReceived.assertOrderDetails( testOrder );
				await orderReceived.assertNoErrors();

				orderId = await orderReceived.getOrderNumber();				
				const transactionId =
						( await wooCommerceApi.getOrder( orderId ) ).transaction_id;

				payPalPaymentDetails = await payPalApi.getPayPalPaymentDetails(
					transactionId,
					testOrder,
				);

				if( payPalPaymentDetails.amount !== '0' ) { // can be 0 for free trial or free orders
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
