/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
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
			await utils.fillVisitorsCart( products );
			await cart.visit();
			// Add coupons if needed
			for ( const coupon of coupons ?? [] ) {
				await cart.applyCoupon( coupon.code );
			}
			await cart.selectShippingMethod( shipping.settings.title );
			await cart.payPalUi.makePayment( { merchant, payment } );

			await checkout.fillCheckoutForm( customer );
			await checkout.placeOrder();

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
