/**
 * Internal dependencies
 */
import { ShopOrder } from '../../../resources';
import { annotateVisitor, test } from '../../../utils';

export const transactionsOnClassicCart = ( testOrder: ShopOrder ) => {
	const { products, payment, merchant, coupons, customer, shipping } =
		testOrder;

	test(
		testOrder.title,
		annotateVisitor( customer ),
		async ( {
			classicCart,
			classicCheckout,
			wooCommerceApi,
			orderReceived,
			payPalApi,
			wooCommerceOrderEdit,
			utils,
		} ) => {
			await utils.fillVisitorsCart( products );
			await classicCart.visit();
			// Add coupons if needed
			for ( const coupon of coupons ?? [] ) {
				await classicCart.applyCoupon( coupon.code );
			}
			await classicCart.selectShippingMethod( shipping.settings.title );
			await classicCart.payPalUi.makePayment( { merchant, payment } );

			await classicCheckout.fillCheckoutForm( customer );
			await classicCheckout.placeOrder();
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
